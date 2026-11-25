"""Pruebas unitarias e integración para el entrenamiento Random Forest y serialización (CEN-56)."""

from __future__ import annotations

import time
from pathlib import Path

import joblib
import pandas as pd
import pytest
from sklearn.ensemble import RandomForestClassifier

from backend_analytics.ml.train_random_forest import (
    DEFAULT_MODEL_PATH,
    MODEL_VERSION,
    RISK_CLASS_LABELS,
    assign_risk_labels,
    chronological_train_test_split,
    load_model_artifact,
    predict_batch_risks,
    predict_neighborhood_risk,
    save_model_artifact,
    train_from_synthetic_data,
    train_risk_classifier,
)
from backend_analytics.pipeline.dataset_builder import build_epiweek_dataset
from backend_analytics.pipeline.lag_features import FEATURE_COLUMNS, build_epiweek_lag_features
from backend_analytics.pipeline.synthetic_generator import generate_synthetic_formosa_dataset


def test_assign_risk_labels_maps_three_canonical_classes() -> None:
    df = pd.DataFrame(
        [
            # Bajo (0): <= 1 febril y 0 sospechas dengue
            {"febrile_cases": 0, "dengue_suspect_cases": 0, "febrile_ratio": 0.0},
            {"febrile_cases": 1, "dengue_suspect_cases": 0, "febrile_ratio": 0.2},
            # Moderado (1): >= 2 febriles o >= 1 sospecha dengue (sin superar umbral alto)
            {"febrile_cases": 2, "dengue_suspect_cases": 0, "febrile_ratio": 0.25},
            {"febrile_cases": 1, "dengue_suspect_cases": 1, "febrile_ratio": 0.20},
            # Alto (2): >= 5 febriles o >= 3 dengue o (>= 4 febriles con ratio >= 0.45)
            {"febrile_cases": 5, "dengue_suspect_cases": 1, "febrile_ratio": 0.35},
            {"febrile_cases": 3, "dengue_suspect_cases": 3, "febrile_ratio": 0.30},
            {"febrile_cases": 4, "dengue_suspect_cases": 1, "febrile_ratio": 0.50},
        ]
    )

    labels = assign_risk_labels(df, febrile_alert_threshold=5)

    assert labels.tolist() == [0, 0, 1, 1, 2, 2, 2]
    assert RISK_CLASS_LABELS == {0: "low", 1: "moderate", 2: "high"}


def test_chronological_train_test_split_prevents_temporal_leakage() -> None:
    telemetry_df, weather_df = generate_synthetic_formosa_dataset(
        n_weeks=30, n_neighborhoods=4, seed=42
    )
    epi_df = build_epiweek_dataset(telemetry_df, weather_df)
    features_df = build_epiweek_lag_features(epi_df, drop_incomplete=True)

    train_df, test_df = chronological_train_test_split(features_df, test_ratio=0.30)

    assert not train_df.empty
    assert not test_df.empty
    assert train_df["week_start_date"].max() < test_df["week_start_date"].min()
    assert len(train_df) + len(test_df) == len(features_df)


def test_train_risk_classifier_produces_valid_metrics_and_joblib_artifact(tmp_path: Path) -> None:
    artifact = train_from_synthetic_data(
        n_weeks=156,
        n_neighborhoods=12,
        seed=42,
    )

    model = artifact["model"]
    assert isinstance(model, RandomForestClassifier)
    assert artifact["model_version"] == MODEL_VERSION
    assert artifact["feature_columns"] == list(FEATURE_COLUMNS)
    assert artifact["class_labels"] == {0: "low", 1: "moderate", 2: "high"}
    assert set(model.classes_.tolist()) == {0, 1, 2}

    metrics = artifact["metrics"]
    assert metrics["accuracy"] >= 0.65
    assert metrics["macro_f1"] >= 0.60
    assert metrics["per_class_recall"]["high"] >= 0.60
    assert metrics["cv_macro_f1_mean"] >= 0.55

    # Las variables climáticas con retardo deben aportar importancia real al ensamble
    importances = artifact["feature_importances"]
    lag_climate_importance = sum(
        importances[col]
        for col in (
            "rain_lag_7d",
            "rain_lag_14d",
            "rain_accum_7_14d",
            "temp_mean_lag_7d",
            "temp_mean_lag_14d",
            "humidity_mean_lag_7d",
            "vector_suitability_lag_7_14d",
        )
    )
    assert lag_climate_importance > 0.25

    # Serialización y carga con joblib
    output_file = tmp_path / "risk_classifier.pkl"
    saved_path = save_model_artifact(artifact, output_file)
    assert saved_path.exists()
    assert saved_path.stat().st_size > 0

    reloaded = load_model_artifact(saved_path)
    assert isinstance(reloaded["model"], RandomForestClassifier)
    assert reloaded["model_version"] == MODEL_VERSION
    assert reloaded["feature_columns"] == list(FEATURE_COLUMNS)


def test_predict_contract_matches_00_contracts_and_meets_200ms_latency(tmp_path: Path) -> None:
    artifact = train_from_synthetic_data(n_weeks=104, n_neighborhoods=8, seed=42)
    model_file = tmp_path / "risk_classifier.pkl"
    save_model_artifact(artifact, model_file)
    loaded = load_model_artifact(model_file)

    high_risk_features = {
        "rain_lag_7d": 95.0,
        "rain_lag_14d": 72.0,
        "rain_accum_7_14d": 167.0,
        "rain_max_daily_lag_7d": 58.0,
        "rainy_days_lag_7d": 4.0,
        "rain_above_threshold_lag_7d": 1.0,
        "temp_mean_lag_7d": 30.5,
        "temp_mean_lag_14d": 29.8,
        "humidity_mean_lag_7d": 84.0,
        "vector_suitability_lag_7_14d": 0.94,
        "febrile_cases_lag_7d": 7.0,
        "febrile_cases_lag_14d": 5.0,
        "febrile_rolling_4w_lag_7d": 5.5,
        "dengue_cases_lag_7d": 4.0,
        "febrile_ratio_lag_7d": 0.65,
        "febrile_growth_rate": 0.33,
        "epi_week_sin": 0.56,
        "epi_week_cos": 0.82,
    }
    low_risk_features = {
        "rain_lag_7d": 0.0,
        "rain_lag_14d": 2.0,
        "rain_accum_7_14d": 2.0,
        "rain_max_daily_lag_7d": 0.0,
        "rainy_days_lag_7d": 0.0,
        "rain_above_threshold_lag_7d": 0.0,
        "temp_mean_lag_7d": 14.5,
        "temp_mean_lag_14d": 15.0,
        "humidity_mean_lag_7d": 55.0,
        "vector_suitability_lag_7_14d": 0.0,
        "febrile_cases_lag_7d": 0.0,
        "febrile_cases_lag_14d": 0.0,
        "febrile_rolling_4w_lag_7d": 0.25,
        "dengue_cases_lag_7d": 0.0,
        "febrile_ratio_lag_7d": 0.0,
        "febrile_growth_rate": 0.0,
        "epi_week_sin": -0.24,
        "epi_week_cos": -0.97,
    }

    t0 = time.perf_counter()
    high_pred = predict_neighborhood_risk(loaded, neighborhood_id=14, features=high_risk_features)
    elapsed_ms = (time.perf_counter() - t0) * 1000.0

    assert elapsed_ms < 200.0
    assert high_pred["neighborhood_id"] == 14
    assert high_pred["risk_class"] == 2
    assert high_pred["risk_level"] == "high"
    assert high_pred["model_version"] == "rf-v1.0"
    assert "predicted_at" in high_pred
    assert set(high_pred["probabilities"].keys()) == {"low", "moderate", "high"}
    assert sum(high_pred["probabilities"].values()) == pytest.approx(1.0, abs=1e-3)
    assert high_pred["risk_score"] == pytest.approx(high_pred["probabilities"]["high"], abs=1e-4)

    low_pred = predict_neighborhood_risk(loaded, neighborhood_id=1, features=low_risk_features)
    assert low_pred["risk_class"] == 0
    assert low_pred["risk_level"] == "low"
    assert low_pred["risk_score"] < high_pred["risk_score"]

    # Verificación de umbral configurable high_risk_threshold (epidemiology_settings = 0.70)
    strict_pred = predict_neighborhood_risk(
        loaded,
        neighborhood_id=14,
        features=high_risk_features,
        high_risk_threshold=0.99,
    )
    assert strict_pred["risk_class"] in (1, 2)
    if strict_pred["probabilities"]["high"] < 0.99:
        assert strict_pred["risk_level"] == "moderate"

    # Predicción batch conforme a 00_contracts.md §4.2
    batch_df = pd.DataFrame(
        [
            {"neighborhood_id": 14, **high_risk_features},
            {"neighborhood_id": 1, **low_risk_features},
        ]
    )
    t_batch = time.perf_counter()
    batch_resp = predict_batch_risks(loaded, batch_df)
    batch_ms = (time.perf_counter() - t_batch) * 1000.0

    assert batch_ms < 200.0
    assert batch_resp["model_version"] == "rf-v1.0"
    assert len(batch_resp["items"]) == 2
    assert batch_resp["items"][0]["risk_level"] == "high"
    assert batch_resp["items"][1]["risk_level"] == "low"


def test_predict_rejects_missing_or_non_finite_features(tmp_path: Path) -> None:
    artifact = train_from_synthetic_data(n_weeks=60, n_neighborhoods=6, seed=42)

    with pytest.raises(ValueError, match="faltantes"):
        predict_neighborhood_risk(artifact, neighborhood_id=14, features={"rain_lag_7d": 10.0})

    invalid = {col: 0.0 for col in FEATURE_COLUMNS}
    invalid["rain_lag_7d"] = float("nan")
    with pytest.raises(ValueError, match="finitos"):
        predict_neighborhood_risk(artifact, neighborhood_id=14, features=invalid)


def test_serialized_production_artifact_exists_and_predicts_validly() -> None:
    assert DEFAULT_MODEL_PATH.exists(), (
        f"El artefacto serializado {DEFAULT_MODEL_PATH} debe existir tras entrenar el modelo."
    )
    raw = joblib.load(DEFAULT_MODEL_PATH)
    assert isinstance(raw, dict)
    assert isinstance(raw["model"], RandomForestClassifier)
    assert raw["model_version"] == "rf-v1.0"
    assert raw["feature_columns"] == list(FEATURE_COLUMNS)

    # Verificar que train_risk_classifier valida datasets insuficientes
    tiny = pd.DataFrame(
        [
            {
                "neighborhood_id": 1,
                "week_start_date": pd.Timestamp("2026-01-05"),
                "febrile_cases": 0,
                "dengue_suspect_cases": 0,
                "febrile_ratio": 0.0,
                **{col: 0.0 for col in FEATURE_COLUMNS},
            }
        ]
    )
    with pytest.raises(ValueError, match="insuficientes"):
        train_risk_classifier(tiny)
