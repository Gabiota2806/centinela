"""Entrenamiento, validación temporal y serialización del modelo Random Forest (CEN-56).

Clasifica el riesgo de brote epidemiológico por barrio de Formosa Capital en 3 clases:
- `0` -> `low` (Riesgo Bajo)
- `1` -> `moderate` (Riesgo Moderado)
- `2` -> `high` (Riesgo Alto / Alerta Temprana)

Exporta el artefacto versionado en `backend_analytics/models/risk_classifier.pkl` con `joblib`
y provee utilidades de inferencia alineadas con `docs/specs/00_contracts.md` §4.2.
"""

from __future__ import annotations

import argparse
import json
from collections.abc import Mapping
from datetime import datetime
from pathlib import Path
from typing import Any

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, confusion_matrix, f1_score, precision_recall_fscore_support

from backend_analytics.pipeline.dataset_builder import ARGENTINA_TZ, build_epiweek_dataset
from backend_analytics.pipeline.lag_features import FEATURE_COLUMNS, build_epiweek_lag_features
from backend_analytics.pipeline.synthetic_generator import generate_synthetic_formosa_dataset

MODEL_VERSION = "rf-v1.0"
DEFAULT_MODEL_PATH = Path(__file__).resolve().parents[1] / "models" / "risk_classifier.pkl"
RISK_CLASS_LABELS: dict[int, str] = {0: "low", 1: "moderate", 2: "high"}
CANONICAL_CLASSES: tuple[int, int, int] = (0, 1, 2)

_HYPERPARAM_CANDIDATES: tuple[dict[str, Any], ...] = (
    {
        "n_estimators": 120,
        "max_depth": 6,
        "min_samples_leaf": 6,
        "max_features": "sqrt",
        "class_weight": "balanced",
    },
    {
        "n_estimators": 120,
        "max_depth": 8,
        "min_samples_leaf": 5,
        "max_features": "sqrt",
        "class_weight": "balanced",
    },
    {
        "n_estimators": 120,
        "max_depth": 9,
        "min_samples_leaf": 4,
        "max_features": "sqrt",
        "class_weight": "balanced_subsample",
    },
    {
        "n_estimators": 120,
        "max_depth": 8,
        "min_samples_leaf": 6,
        "max_features": "sqrt",
        "class_weight": None,
    },
)


def assign_risk_labels(
    df: pd.DataFrame,
    *,
    febrile_alert_threshold: int = 5,
) -> pd.Series:
    """Etiqueta cada barrio-semana en las 3 clases de riesgo epidemiológico (0, 1, 2).

    La etiqueta se deriva exclusivamente de la incidencia clínica observada en la semana `t`
    (sin utilizar variables rezagadas de entrada), evitando toda circularidad en el entrenamiento.
    """
    if febrile_alert_threshold < 2:
        raise ValueError("febrile_alert_threshold debe ser mayor o igual a 2.")

    required = {"febrile_cases", "dengue_suspect_cases", "febrile_ratio"}
    missing = required - set(df.columns)
    if missing:
        raise ValueError(f"Columnas obligatorias faltantes para etiquetado: {sorted(missing)}")

    febrile = pd.to_numeric(df["febrile_cases"], errors="coerce").fillna(0).to_numpy()
    dengue = pd.to_numeric(df["dengue_suspect_cases"], errors="coerce").fillna(0).to_numpy()
    ratio = pd.to_numeric(df["febrile_ratio"], errors="coerce").fillna(0.0).to_numpy()

    high_cond = (
        (febrile >= febrile_alert_threshold)
        | (dengue >= 3)
        | ((febrile >= max(3, febrile_alert_threshold - 1)) & (ratio >= 0.45))
    )
    moderate_cond = (~high_cond) & ((febrile >= 2) | (dengue >= 1))

    labels = np.select([high_cond, moderate_cond], [2, 1], default=0).astype("int64")
    return pd.Series(labels, index=df.index, name="risk_class", dtype="int64")


def chronological_train_test_split(
    df: pd.DataFrame,
    *,
    test_ratio: float = 0.33,
) -> tuple[pd.DataFrame, pd.DataFrame]:
    """Divide la serie estrictamente por horizonte temporal (`week_start_date`) sin mezcla aleatoria."""
    if not (0.1 <= test_ratio <= 0.5):
        raise ValueError("test_ratio debe estar en el rango [0.1, 0.5].")
    if "week_start_date" not in df.columns:
        raise ValueError("La columna 'week_start_date' es obligatoria para el split temporal.")

    ordered = df.copy()
    ordered["week_start_date"] = pd.to_datetime(ordered["week_start_date"])
    unique_weeks = np.sort(ordered["week_start_date"].unique())

    if len(unique_weeks) < 6:
        raise ValueError(
            "Datos temporales insuficientes: se requieren al menos 6 semanas epidemiológicas."
        )

    n_test_weeks = max(2, int(round(len(unique_weeks) * test_ratio)))
    split_idx = len(unique_weeks) - n_test_weeks
    if split_idx < 4:
        raise ValueError("Datos temporales insuficientes para construir particiones train/test.")

    cutoff_week = unique_weeks[split_idx]
    train_df = (
        ordered[ordered["week_start_date"] < cutoff_week]
        .sort_values(["week_start_date", "neighborhood_id"])
        .reset_index(drop=True)
    )
    test_df = (
        ordered[ordered["week_start_date"] >= cutoff_week]
        .sort_values(["week_start_date", "neighborhood_id"])
        .reset_index(drop=True)
    )
    return train_df, test_df


def _temporal_cv_macro_f1(
    train_df: pd.DataFrame,
    y_train: pd.Series,
    params: dict[str, Any],
    *,
    n_splits: int = 3,
    random_state: int = 42,
) -> float:
    """Evalúa un candidato mediante validación cruzada temporal expansiva por semana.

    Garantiza que la ventana inicial de entrenamiento cubra al menos un ciclo estacional
    completo (52 semanas cuando haya histórico suficiente, o 50 % de las semanas de train),
    evitando evaluar un fold invernal con un modelo que solo observó verano.
    """
    unique_weeks = np.sort(train_df["week_start_date"].unique())
    n_weeks = len(unique_weeks)
    min_train_weeks = min(52, max(4, n_weeks // 2)) if n_weeks >= 60 else max(4, n_weeks // 2)
    remaining_weeks = n_weeks - min_train_weeks
    effective_splits = min(n_splits, max(1, remaining_weeks // 4))
    val_step = max(2, remaining_weeks // effective_splits)

    oof_true: list[np.ndarray] = []
    oof_pred: list[np.ndarray] = []
    for fold in range(effective_splits):
        train_end_idx = min_train_weeks + fold * val_step
        val_end_idx = (
            n_weeks if fold == effective_splits - 1 else min(n_weeks, train_end_idx + val_step)
        )
        if train_end_idx >= n_weeks or val_end_idx <= train_end_idx:
            break

        tr_weeks = unique_weeks[:train_end_idx]
        va_weeks = unique_weeks[train_end_idx:val_end_idx]

        tr_mask = train_df["week_start_date"].isin(tr_weeks)
        va_mask = train_df["week_start_date"].isin(va_weeks)

        y_tr = y_train.loc[tr_mask]
        y_va = y_train.loc[va_mask]
        if y_tr.nunique() < 2 or len(y_va) == 0:
            continue

        clf = RandomForestClassifier(random_state=random_state, n_jobs=-1, **params)
        clf.fit(train_df.loc[tr_mask, list(FEATURE_COLUMNS)], y_tr)
        preds = clf.predict(train_df.loc[va_mask, list(FEATURE_COLUMNS)])
        oof_true.append(y_va.to_numpy())
        oof_pred.append(preds)

    if not oof_true:
        return 0.0

    y_oof_true = np.concatenate(oof_true)
    y_oof_pred = np.concatenate(oof_pred)
    return float(
        f1_score(
            y_oof_true,
            y_oof_pred,
            labels=list(CANONICAL_CLASSES),
            average="macro",
            zero_division=0,
        )
    )


def train_risk_classifier(
    features_df: pd.DataFrame,
    *,
    febrile_alert_threshold: int = 5,
    rain_threshold_mm: float = 40.0,
    test_ratio: float = 0.33,
    random_state: int = 42,
) -> dict[str, Any]:
    """Entrena y evalúa el clasificador Random Forest de riesgo barrial en 3 clases.

    Ejecuta split temporal estricto (sin fuga de semanas futuras), selección de hiperparámetros
    mediante validación cruzada temporal expansiva y cálculo integral de métricas.
    """
    missing_features = set(FEATURE_COLUMNS) - set(features_df.columns)
    if missing_features:
        raise ValueError(f"Features obligatorios faltantes: {sorted(missing_features)}")

    clean_df = features_df.dropna(subset=list(FEATURE_COLUMNS)).copy()
    if len(clean_df) < 24:
        raise ValueError("Datos temporales insuficientes para entrenar RandomForestClassifier.")

    clean_df["risk_class"] = assign_risk_labels(
        clean_df, febrile_alert_threshold=febrile_alert_threshold
    )

    train_df, test_df = chronological_train_test_split(clean_df, test_ratio=test_ratio)
    y_train = train_df["risk_class"]
    y_test = test_df["risk_class"]

    if set(y_train.unique().tolist()) != set(CANONICAL_CLASSES):
        raise ValueError(
            "El conjunto de entrenamiento debe contener observaciones de las 3 clases (0, 1, 2)."
        )

    best_params = dict(_HYPERPARAM_CANDIDATES[0])
    best_cv_f1 = -1.0

    for candidate in _HYPERPARAM_CANDIDATES:
        cv_score = _temporal_cv_macro_f1(
            train_df,
            y_train,
            candidate,
            n_splits=4,
            random_state=random_state,
        )
        if cv_score > best_cv_f1:
            best_cv_f1 = cv_score
            best_params = dict(candidate)

    x_train = train_df[list(FEATURE_COLUMNS)]
    x_test = test_df[list(FEATURE_COLUMNS)]

    model = RandomForestClassifier(random_state=random_state, n_jobs=-1, **best_params)
    model.fit(x_train, y_train)
    y_pred = model.predict(x_test)

    accuracy = float(accuracy_score(y_test, y_pred))
    macro_f1 = float(
        f1_score(y_test, y_pred, labels=list(CANONICAL_CLASSES), average="macro", zero_division=0)
    )
    weighted_f1 = float(
        f1_score(
            y_test, y_pred, labels=list(CANONICAL_CLASSES), average="weighted", zero_division=0
        )
    )
    precisions, recalls, f1s, supports = precision_recall_fscore_support(
        y_test,
        y_pred,
        labels=list(CANONICAL_CLASSES),
        zero_division=0,
    )
    cm = confusion_matrix(y_test, y_pred, labels=list(CANONICAL_CLASSES))

    per_class_precision = {
        RISK_CLASS_LABELS[c]: round(float(precisions[idx]), 4)
        for idx, c in enumerate(CANONICAL_CLASSES)
    }
    per_class_recall = {
        RISK_CLASS_LABELS[c]: round(float(recalls[idx]), 4)
        for idx, c in enumerate(CANONICAL_CLASSES)
    }
    per_class_f1 = {
        RISK_CLASS_LABELS[c]: round(float(f1s[idx]), 4)
        for idx, c in enumerate(CANONICAL_CLASSES)
    }
    per_class_support = {
        RISK_CLASS_LABELS[c]: int(supports[idx]) for idx, c in enumerate(CANONICAL_CLASSES)
    }

    raw_importances = dict(
        zip(FEATURE_COLUMNS, [round(float(v), 4) for v in model.feature_importances_], strict=True)
    )
    feature_importances = dict(
        sorted(raw_importances.items(), key=lambda kv: kv[1], reverse=True)
    )

    metrics = {
        "accuracy": round(accuracy, 4),
        "macro_f1": round(macro_f1, 4),
        "weighted_f1": round(weighted_f1, 4),
        "cv_macro_f1_mean": round(best_cv_f1, 4),
        "per_class_precision": per_class_precision,
        "per_class_recall": per_class_recall,
        "per_class_f1": per_class_f1,
        "per_class_support": per_class_support,
        "confusion_matrix": cm.tolist(),
        "class_distribution_train": {
            RISK_CLASS_LABELS[c]: int((y_train == c).sum()) for c in CANONICAL_CLASSES
        },
        "class_distribution_test": {
            RISK_CLASS_LABELS[c]: int((y_test == c).sum()) for c in CANONICAL_CLASSES
        },
    }

    return {
        "model": model,
        "model_version": MODEL_VERSION,
        "feature_columns": list(FEATURE_COLUMNS),
        "class_labels": dict(RISK_CLASS_LABELS),
        "hyperparameters": best_params,
        "metrics": metrics,
        "feature_importances": feature_importances,
        "training_config": {
            "rain_threshold_mm": float(rain_threshold_mm),
            "febrile_alert_threshold": int(febrile_alert_threshold),
            "test_ratio": float(test_ratio),
            "random_state": int(random_state),
            "n_train_samples": int(len(train_df)),
            "n_test_samples": int(len(test_df)),
            "train_date_range": [
                str(train_df["week_start_date"].min().date()),
                str(train_df["week_start_date"].max().date()),
            ],
            "test_date_range": [
                str(test_df["week_start_date"].min().date()),
                str(test_df["week_start_date"].max().date()),
            ],
        },
        "trained_at": datetime.now(ARGENTINA_TZ).isoformat(timespec="seconds"),
    }


def train_from_synthetic_data(
    *,
    n_weeks: int = 156,
    n_neighborhoods: int = 24,
    start_date: str = "2023-11-27",
    rain_threshold_mm: float = 40.0,
    febrile_alert_threshold: int = 5,
    test_ratio: float = 0.33,
    seed: int = 42,
) -> dict[str, Any]:
    """Ejecuta el pipeline completo end-to-end sobre el generador sintético de Formosa Capital."""
    telemetry_df, weather_df = generate_synthetic_formosa_dataset(
        n_weeks=n_weeks,
        n_neighborhoods=n_neighborhoods,
        start_date=start_date,
        seed=seed,
    )
    epiweek_df = build_epiweek_dataset(telemetry_df, weather_df)
    features_df = build_epiweek_lag_features(
        epiweek_df,
        rain_threshold_mm=rain_threshold_mm,
        drop_incomplete=True,
    )
    artifact = train_risk_classifier(
        features_df,
        febrile_alert_threshold=febrile_alert_threshold,
        rain_threshold_mm=rain_threshold_mm,
        test_ratio=test_ratio,
        random_state=seed,
    )
    artifact["training_config"]["data_source"] = "synthetic-formosa"
    artifact["training_config"]["n_weeks"] = int(n_weeks)
    artifact["training_config"]["n_neighborhoods"] = int(n_neighborhoods)
    return artifact


def save_model_artifact(
    artifact: dict[str, Any],
    output_path: str | Path = DEFAULT_MODEL_PATH,
) -> Path:
    """Serializa el artefacto versionado del clasificador con `joblib`."""
    path = Path(output_path)
    path.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(artifact, path, compress=3)
    return path


def load_model_artifact(model_path: str | Path = DEFAULT_MODEL_PATH) -> dict[str, Any]:
    """Carga y valida la integridad del artefacto serializado con `joblib`."""
    path = Path(model_path)
    if not path.exists():
        raise FileNotFoundError(f"No se encontró el artefacto del modelo en: {path}")

    artifact = joblib.load(path)
    required_keys = {"model", "model_version", "feature_columns", "class_labels"}
    if not isinstance(artifact, dict) or not required_keys.issubset(set(artifact.keys())):
        raise ValueError("El artefacto serializado no cumple la estructura esperada de Centinela.")
    return artifact


def _extract_ordered_feature_frame(
    features: Mapping[str, float] | pd.DataFrame,
    feature_columns: list[str],
) -> pd.DataFrame:
    if isinstance(features, pd.DataFrame):
        df = features.copy()
    else:
        df = pd.DataFrame([dict(features)])

    missing = [col for col in feature_columns if col not in df.columns]
    if missing:
        raise ValueError(f"Features faltantes para inferencia: {missing}")

    ordered = df[feature_columns].apply(pd.to_numeric, errors="coerce")
    matrix = ordered.to_numpy(dtype=np.float64)
    if not np.isfinite(matrix).all():
        raise ValueError("Todos los valores de entrada para inferencia deben ser números finitos.")

    return ordered


def _format_probability_payload(
    raw_proba: np.ndarray,
    model_classes: np.ndarray,
    class_labels: Mapping[int, str],
    *,
    high_risk_threshold: float | None = None,
) -> tuple[int, str, float, dict[str, float]]:
    class_to_prob = {int(c): float(p) for c, p in zip(model_classes, raw_proba, strict=True)}
    p_low = round(class_to_prob.get(0, 0.0), 4)
    p_mod = round(class_to_prob.get(1, 0.0), 4)
    p_high = round(max(0.0, min(1.0, 1.0 - p_low - p_mod)), 4)

    probabilities = {
        "low": p_low,
        "moderate": p_mod,
        "high": p_high,
    }

    if high_risk_threshold is not None:
        if p_high >= high_risk_threshold:
            risk_class = 2
        elif (p_mod + p_high) >= 0.45:
            risk_class = 1
        else:
            risk_class = 0
    else:
        risk_class = int(np.argmax([p_low, p_mod, p_high]))

    risk_level = str(class_labels[risk_class])
    # risk_score representa la probabilidad continua de riesgo alto [0.0000, 1.0000],
    # compatible con neighborhoods.risk_score decimal(5,4) y 00_contracts.md §4.2
    risk_score = round(p_high, 4)
    return risk_class, risk_level, risk_score, probabilities


def predict_neighborhood_risk(
    artifact: Mapping[str, Any],
    neighborhood_id: int,
    features: Mapping[str, float] | pd.DataFrame,
    *,
    high_risk_threshold: float | None = None,
) -> dict[str, Any]:
    """Ejecuta inferencia individual conforme al contrato JSON de `00_contracts.md` §4.2."""
    feature_columns = list(artifact["feature_columns"])
    x_df = _extract_ordered_feature_frame(features, feature_columns)
    if len(x_df) != 1:
        raise ValueError("predict_neighborhood_risk requiere exactamente una fila de features.")

    model: RandomForestClassifier = artifact["model"]
    raw_proba = model.predict_proba(x_df)[0]
    risk_class, risk_level, risk_score, probabilities = _format_probability_payload(
        raw_proba,
        model.classes_,
        artifact["class_labels"],
        high_risk_threshold=high_risk_threshold,
    )

    return {
        "neighborhood_id": int(neighborhood_id),
        "risk_class": risk_class,
        "risk_level": risk_level,
        "risk_score": risk_score,
        "probabilities": probabilities,
        "model_version": str(artifact["model_version"]),
        "predicted_at": datetime.now(ARGENTINA_TZ).isoformat(timespec="seconds"),
    }


def predict_batch_risks(
    artifact: Mapping[str, Any],
    features_df: pd.DataFrame,
    *,
    high_risk_threshold: float | None = None,
) -> dict[str, Any]:
    """Ejecuta inferencia vectorizada en lote conforme al contrato batch de `00_contracts.md` §4.2."""
    if "neighborhood_id" not in features_df.columns:
        raise ValueError("La columna 'neighborhood_id' es obligatoria para predicción batch.")

    predicted_at = datetime.now(ARGENTINA_TZ).isoformat(timespec="seconds")
    model_version = str(artifact["model_version"])

    if features_df.empty:
        return {
            "items": [],
            "model_version": model_version,
            "predicted_at": predicted_at,
        }

    feature_columns = list(artifact["feature_columns"])
    x_df = _extract_ordered_feature_frame(features_df, feature_columns)
    model: RandomForestClassifier = artifact["model"]
    all_probas = model.predict_proba(x_df)

    items: list[dict[str, Any]] = []
    neighborhood_ids = features_df["neighborhood_id"].astype("int64").tolist()
    for nid, raw_proba in zip(neighborhood_ids, all_probas, strict=True):
        risk_class, risk_level, risk_score, probabilities = _format_probability_payload(
            raw_proba,
            model.classes_,
            artifact["class_labels"],
            high_risk_threshold=high_risk_threshold,
        )
        items.append(
            {
                "neighborhood_id": int(nid),
                "risk_class": risk_class,
                "risk_level": risk_level,
                "risk_score": risk_score,
                "probabilities": probabilities,
                "model_version": model_version,
                "predicted_at": predicted_at,
            }
        )

    return {
        "items": items,
        "model_version": model_version,
        "predicted_at": predicted_at,
    }


def _parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Entrena y serializa el clasificador Random Forest de riesgo epidemiológico."
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=DEFAULT_MODEL_PATH,
        help="Ruta de salida para el artefacto joblib (.pkl).",
    )
    parser.add_argument(
        "--weeks",
        type=int,
        default=156,
        help="Cantidad de semanas epidemiológicas sintéticas a simular (default: 156 = 3 años).",
    )
    parser.add_argument(
        "--neighborhoods",
        type=int,
        default=24,
        help="Cantidad de barrios de Formosa Capital a simular (1..30).",
    )
    parser.add_argument(
        "--seed",
        type=int,
        default=42,
        help="Semilla determinística de reproducibilidad.",
    )
    return parser.parse_args()


def main() -> None:
    args = _parse_args()
    artifact = train_from_synthetic_data(
        n_weeks=args.weeks,
        n_neighborhoods=args.neighborhoods,
        seed=args.seed,
    )
    saved_path = save_model_artifact(artifact, args.output)
    summary = {
        "model_path": str(saved_path),
        "model_size_kb": round(saved_path.stat().st_size / 1024.0, 2),
        "model_version": artifact["model_version"],
        "hyperparameters": artifact["hyperparameters"],
        "training_config": artifact["training_config"],
        "metrics": artifact["metrics"],
        "top_features": dict(list(artifact["feature_importances"].items())[:8]),
        "trained_at": artifact["trained_at"],
    }
    print(json.dumps(summary, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
