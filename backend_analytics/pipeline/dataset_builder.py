"""Procesamiento y limpieza de series temporales epidemiológicas y climáticas con Pandas (CEN-54).

Transforma eventos de telemetría estrictamente disociados (Ley N° 25.326) y registros
meteorológicos diarios de Formosa Capital en series temporales limpias agrupadas por
día y por semana epidemiológica (EpiWeek ISO) y barrio.
"""

from __future__ import annotations

from collections.abc import Iterable
from datetime import timedelta, timezone

import numpy as np
import pandas as pd

# Zona horaria oficial de Formosa, Argentina (UTC-03:00 sin horario de verano)
ARGENTINA_TZ = timezone(timedelta(hours=-3))

# Catálogos canónicos definidos en docs/specs/00_contracts.md §5
FEBRILE_DIAGNOSES: frozenset[str] = frozenset(
    {
        "sindrome_febril_inespecifico",
        "sospecha_dengue",
        "ira",
    }
)
DENGUE_DIAGNOSES: frozenset[str] = frozenset({"sospecha_dengue"})
NON_FEBRILE_DIAGNOSES: frozenset[str] = frozenset(
    {
        "gastroenteritis",
        "traumatismo_herida",
        "hipertension_cardiovascular",
        "otro_clinico",
    }
)
VALID_DIAGNOSES: frozenset[str] = FEBRILE_DIAGNOSES | NON_FEBRILE_DIAGNOSES
VALID_AGE_GROUPS: frozenset[str] = frozenset({"infante", "adolescente", "adulto", "mayor"})
HIGH_PRIORITIES: frozenset[str] = frozenset({"red", "orange"})

# Claves identificatorias prohibidas por Ley Nacional N° 25.326 (Privacy by Design)
FORBIDDEN_PII_COLUMNS: frozenset[str] = frozenset(
    {
        "dni",
        "first_name",
        "last_name",
        "full_name",
        "phone",
        "address",
        "birth_date",
        "documento",
        "nombre",
        "apellido",
        "telefono",
        "domicilio",
        "fecha_nacimiento",
        "email",
    }
)

REQUIRED_TELEMETRY_COLUMNS: frozenset[str] = frozenset(
    {
        "event_uuid",
        "neighborhood_id",
        "presumptive_diagnosis",
        "recorded_at",
    }
)

REQUIRED_WEATHER_COLUMNS: frozenset[str] = frozenset(
    {
        "recorded_date",
        "temperature_max",
        "temperature_min",
        "precipitation_sum",
    }
)

CLINICAL_TEMP_MIN_C = 34.0
CLINICAL_TEMP_MAX_C = 43.0
RAINY_DAY_THRESHOLD_MM = 5.0


def validate_no_pii_columns(df: pd.DataFrame) -> None:
    """Valida que el DataFrame no contenga columnas identificatorias (Ley N° 25.326)."""
    present_pii = sorted(
        col for col in df.columns if str(col).strip().lower() in FORBIDDEN_PII_COLUMNS
    )
    if present_pii:
        joined = ", ".join(present_pii)
        raise ValueError(
            f"Violación Ley N° 25.326: columnas identificatorias prohibidas detectadas: {joined}"
        )


def _attach_epiweek_columns(df: pd.DataFrame, date_col: str) -> pd.DataFrame:
    """Agrega epi_year, epi_week y week_start_date (lunes ISO) a partir de una columna fecha."""
    out = df.copy()
    dt = pd.to_datetime(out[date_col])
    iso = dt.dt.isocalendar()
    out["epi_year"] = iso.year.astype("int64")
    out["epi_week"] = iso.week.astype("int64")
    out["week_start_date"] = dt - pd.to_timedelta(dt.dt.weekday, unit="D")
    return out


def clean_telemetry_df(telemetry_df: pd.DataFrame) -> pd.DataFrame:
    """Limpia, valida y normaliza el DataFrame de telemetría clínica anonimizada.

    - Rechaza cualquier columna PII prohibida por Ley N° 25.326.
    - Deduplica idempotentemente por `event_uuid`.
    - Normaliza timestamps a horario local de Formosa (UTC-03:00).
    - Sanea temperaturas fuera de rango fisiológico [34.0, 43.0] °C a NaN.
    - Etiqueta indicadores booleanos clínicos según `docs/specs/00_contracts.md` §5.1.
    """
    validate_no_pii_columns(telemetry_df)

    missing = REQUIRED_TELEMETRY_COLUMNS - set(telemetry_df.columns)
    if missing:
        raise ValueError(f"Columnas obligatorias faltantes en telemetría: {sorted(missing)}")

    if telemetry_df.empty:
        return pd.DataFrame(
            columns=[
                "event_uuid",
                "neighborhood_id",
                "presumptive_diagnosis",
                "triage_priority",
                "age_group",
                "temperature_c",
                "recorded_at",
                "recorded_date",
                "epi_year",
                "epi_week",
                "week_start_date",
                "is_febrile",
                "is_dengue_suspect",
                "is_ira",
                "is_high_priority",
            ]
        )

    df = telemetry_df.copy()
    df["event_uuid"] = df["event_uuid"].astype("string").str.strip()
    df = df[df["event_uuid"].notna() & (df["event_uuid"] != "")]
    df = df.drop_duplicates(subset=["event_uuid"], keep="first")

    df["neighborhood_id"] = pd.to_numeric(df["neighborhood_id"], errors="coerce")
    df = df[df["neighborhood_id"].notna() & (df["neighborhood_id"] > 0)].copy()
    df["neighborhood_id"] = df["neighborhood_id"].astype("int64")

    # Conversión robusta de timestamps con soporte para ISO-8601 con zona o UTC
    parsed_utc = pd.to_datetime(df["recorded_at"], utc=True, errors="coerce")
    df = df[parsed_utc.notna()].copy()
    local_ts = parsed_utc.loc[df.index].dt.tz_convert(ARGENTINA_TZ)
    df["recorded_at"] = local_ts
    df["recorded_date"] = local_ts.dt.tz_localize(None).dt.floor("D")

    df["presumptive_diagnosis"] = (
        df["presumptive_diagnosis"].astype("string").str.strip().str.lower().fillna("otro_clinico")
    )

    if "triage_priority" in df.columns:
        df["triage_priority"] = (
            df["triage_priority"].astype("string").str.strip().str.lower().fillna("green")
        )
    else:
        df["triage_priority"] = "green"

    if "age_group" in df.columns:
        df["age_group"] = df["age_group"].astype("string").str.strip().str.lower().fillna("adulto")
    else:
        df["age_group"] = "adulto"

    if "temperature_c" in df.columns:
        temps = pd.to_numeric(df["temperature_c"], errors="coerce")
        valid_temp_mask = temps.between(CLINICAL_TEMP_MIN_C, CLINICAL_TEMP_MAX_C, inclusive="both")
        df["temperature_c"] = temps.where(valid_temp_mask, np.nan)
    else:
        df["temperature_c"] = np.nan

    df["is_febrile"] = df["presumptive_diagnosis"].isin(FEBRILE_DIAGNOSES)
    df["is_dengue_suspect"] = df["presumptive_diagnosis"].isin(DENGUE_DIAGNOSES)
    df["is_ira"] = df["presumptive_diagnosis"] == "ira"
    df["is_high_priority"] = df["triage_priority"].isin(HIGH_PRIORITIES)

    df = _attach_epiweek_columns(df, "recorded_date")
    df = df.sort_values(["recorded_at", "event_uuid"]).reset_index(drop=True)
    return df


def clean_weather_df(weather_df: pd.DataFrame) -> pd.DataFrame:
    """Limpia, reindexa a frecuencia diaria continua e imputa faltantes climáticos.

    - Reindexa del día mínimo al máximo sin huecos en la serie diaria.
    - Asegura `precipitation_sum >= 0.0` (faltantes imputados como 0.0 mm).
    - Interpola linealmente `temperature_max`, `temperature_min` y `relative_humidity_mean`.
    - Calcula `temperature_mean` y acota humedad relativa al rango físico [0, 100] %.
    """
    missing = REQUIRED_WEATHER_COLUMNS - set(weather_df.columns)
    if missing:
        raise ValueError(f"Columnas obligatorias faltantes en clima: {sorted(missing)}")

    if weather_df.empty:
        raise ValueError("El DataFrame meteorológico no puede estar vacío.")

    df = weather_df.copy()
    df["recorded_date"] = pd.to_datetime(df["recorded_date"], errors="coerce").dt.floor("D")
    df = df[df["recorded_date"].notna()].copy()
    if df.empty:
        raise ValueError("El DataFrame meteorológico no contiene fechas válidas.")

    for col in ("temperature_max", "temperature_min", "precipitation_sum"):
        df[col] = pd.to_numeric(df[col], errors="coerce")

    if "relative_humidity_mean" in df.columns:
        df["relative_humidity_mean"] = pd.to_numeric(df["relative_humidity_mean"], errors="coerce")
    else:
        df["relative_humidity_mean"] = 70.0

    # Deduplicación idempotente por fecha y grilla diaria continua
    df = (
        df.sort_values("recorded_date")
        .drop_duplicates(subset=["recorded_date"], keep="last")
        .set_index("recorded_date")
    )
    full_range = pd.date_range(df.index.min(), df.index.max(), freq="D", name="recorded_date")
    df = df.reindex(full_range)

    # Precipitación negativa o faltante se normaliza a 0.0 mm (evita inventar lluvias)
    df["precipitation_sum"] = df["precipitation_sum"].fillna(0.0).clip(lower=0.0)

    # Interpolación lineal acotada para variables térmicas y humedad
    for col, fallback in (
        ("temperature_max", 28.0),
        ("temperature_min", 18.0),
        ("relative_humidity_mean", 70.0),
    ):
        series = df[col].interpolate(method="linear").ffill().bfill()
        df[col] = series.fillna(fallback)

    df["relative_humidity_mean"] = df["relative_humidity_mean"].clip(lower=0.0, upper=100.0)

    # Consistencia física: Tmax >= Tmin
    tmax = np.maximum(df["temperature_max"].to_numpy(), df["temperature_min"].to_numpy())
    tmin = np.minimum(df["temperature_max"].to_numpy(), df["temperature_min"].to_numpy())
    df["temperature_max"] = tmax
    df["temperature_min"] = tmin
    df["temperature_mean"] = (df["temperature_max"] + df["temperature_min"]) / 2.0

    df = df.reset_index()
    keep_cols = [
        "recorded_date",
        "precipitation_sum",
        "temperature_max",
        "temperature_min",
        "temperature_mean",
        "relative_humidity_mean",
    ]
    return _attach_epiweek_columns(df[keep_cols], "recorded_date")


def build_daily_series(
    telemetry_df: pd.DataFrame,
    weather_df: pd.DataFrame,
    neighborhood_ids: Iterable[int] | None = None,
) -> pd.DataFrame:
    """Construye la serie temporal diaria completa (barrio × fecha) integrando telemetría y clima."""
    clean_tel = clean_telemetry_df(telemetry_df)
    clean_wx = clean_weather_df(weather_df)

    if neighborhood_ids is not None:
        resolved_ids = sorted({int(nid) for nid in neighborhood_ids if int(nid) > 0})
    else:
        resolved_ids = sorted(clean_tel["neighborhood_id"].unique().tolist())

    if not resolved_ids:
        raise ValueError(
            "No se encontraron barrios válidos para construir la serie temporal."
        )

    dates = clean_wx["recorded_date"].unique()
    grid = pd.MultiIndex.from_product(
        [resolved_ids, dates], names=["neighborhood_id", "recorded_date"]
    ).to_frame(index=False)

    if clean_tel.empty:
        daily_tel = pd.DataFrame(
            {
                "neighborhood_id": pd.Series(dtype="int64"),
                "recorded_date": pd.Series(dtype="datetime64[ns]"),
                "total_consultations": pd.Series(dtype="int64"),
                "febrile_cases": pd.Series(dtype="int64"),
                "dengue_suspect_cases": pd.Series(dtype="int64"),
                "ira_cases": pd.Series(dtype="int64"),
                "high_priority_cases": pd.Series(dtype="int64"),
                "temp_sum": pd.Series(dtype="float64"),
                "temp_count": pd.Series(dtype="int64"),
            }
        )
    else:
        filtered_tel = clean_tel[clean_tel["neighborhood_id"].isin(resolved_ids)]
        daily_tel = (
            filtered_tel.groupby(["neighborhood_id", "recorded_date"], as_index=False)
            .agg(
                total_consultations=("event_uuid", "count"),
                febrile_cases=("is_febrile", "sum"),
                dengue_suspect_cases=("is_dengue_suspect", "sum"),
                ira_cases=("is_ira", "sum"),
                high_priority_cases=("is_high_priority", "sum"),
                temp_sum=("temperature_c", "sum"),
                temp_count=("temperature_c", "count"),
            )
        )

    merged = grid.merge(daily_tel, on=["neighborhood_id", "recorded_date"], how="left")
    count_cols = [
        "total_consultations",
        "febrile_cases",
        "dengue_suspect_cases",
        "ira_cases",
        "high_priority_cases",
        "temp_count",
    ]
    for col in count_cols:
        merged[col] = merged[col].fillna(0).astype("int64")
    merged["temp_sum"] = merged["temp_sum"].fillna(0.0).astype("float64")
    merged["mean_temperature_c"] = np.where(
        merged["temp_count"] > 0, merged["temp_sum"] / merged["temp_count"], np.nan
    )

    merged = merged.merge(clean_wx, on="recorded_date", how="left")
    merged["febrile_ratio"] = np.where(
        merged["total_consultations"] > 0,
        merged["febrile_cases"] / merged["total_consultations"],
        0.0,
    )
    merged["dengue_ratio"] = np.where(
        merged["total_consultations"] > 0,
        merged["dengue_suspect_cases"] / merged["total_consultations"],
        0.0,
    )

    ordered_cols = [
        "neighborhood_id",
        "recorded_date",
        "epi_year",
        "epi_week",
        "week_start_date",
        "total_consultations",
        "febrile_cases",
        "dengue_suspect_cases",
        "ira_cases",
        "high_priority_cases",
        "febrile_ratio",
        "dengue_ratio",
        "mean_temperature_c",
        "temp_sum",
        "temp_count",
        "precipitation_sum",
        "temperature_max",
        "temperature_min",
        "temperature_mean",
        "relative_humidity_mean",
    ]
    return (
        merged[ordered_cols]
        .sort_values(["neighborhood_id", "recorded_date"])
        .reset_index(drop=True)
    )


def build_epiweek_dataset(
    telemetry_df: pd.DataFrame,
    weather_df: pd.DataFrame,
    neighborhood_ids: Iterable[int] | None = None,
) -> pd.DataFrame:
    """Agrupa las series temporales limpias por barrio y semana epidemiológica (EpiWeek ISO).

    Garantiza continuidad temporal sin huecos para cada barrio, conservando semanas sin
    consultas con conteos en 0 y variables meteorológicas consolidadas.
    """
    daily = build_daily_series(
        telemetry_df=telemetry_df,
        weather_df=weather_df,
        neighborhood_ids=neighborhood_ids,
    )
    daily["is_rainy_day"] = (daily["precipitation_sum"] >= RAINY_DAY_THRESHOLD_MM).astype("int64")

    epi = (
        daily.groupby(
            ["neighborhood_id", "epi_year", "epi_week", "week_start_date"],
            as_index=False,
        )
        .agg(
            days_in_week=("recorded_date", "count"),
            total_consultations=("total_consultations", "sum"),
            febrile_cases=("febrile_cases", "sum"),
            dengue_suspect_cases=("dengue_suspect_cases", "sum"),
            ira_cases=("ira_cases", "sum"),
            high_priority_cases=("high_priority_cases", "sum"),
            temp_sum=("temp_sum", "sum"),
            temp_count=("temp_count", "sum"),
            precipitation_sum=("precipitation_sum", "sum"),
            precipitation_max_daily=("precipitation_sum", "max"),
            rainy_days=("is_rainy_day", "sum"),
            temperature_max_mean=("temperature_max", "mean"),
            temperature_min_mean=("temperature_min", "mean"),
            temperature_mean=("temperature_mean", "mean"),
            relative_humidity_mean=("relative_humidity_mean", "mean"),
        )
    )

    epi["febrile_ratio"] = np.where(
        epi["total_consultations"] > 0,
        epi["febrile_cases"] / epi["total_consultations"],
        0.0,
    )
    epi["dengue_ratio"] = np.where(
        epi["total_consultations"] > 0,
        epi["dengue_suspect_cases"] / epi["total_consultations"],
        0.0,
    )
    epi["mean_temperature_c"] = np.where(
        epi["temp_count"] > 0,
        epi["temp_sum"] / epi["temp_count"],
        np.nan,
    )

    epi = epi.drop(columns=["temp_sum", "temp_count"])
    ordered_cols = [
        "neighborhood_id",
        "epi_year",
        "epi_week",
        "week_start_date",
        "days_in_week",
        "total_consultations",
        "febrile_cases",
        "dengue_suspect_cases",
        "ira_cases",
        "high_priority_cases",
        "febrile_ratio",
        "dengue_ratio",
        "mean_temperature_c",
        "precipitation_sum",
        "precipitation_max_daily",
        "rainy_days",
        "temperature_max_mean",
        "temperature_min_mean",
        "temperature_mean",
        "relative_humidity_mean",
    ]
    return (
        epi[ordered_cols]
        .sort_values(["neighborhood_id", "week_start_date"])
        .reset_index(drop=True)
    )
