"""Pruebas unitarias para el procesamiento de series temporales y generador sintético (CEN-54)."""

from __future__ import annotations

import pandas as pd
import pytest

from backend_analytics.pipeline.dataset_builder import (
    FEBRILE_DIAGNOSES,
    FORBIDDEN_PII_COLUMNS,
    build_daily_series,
    build_epiweek_dataset,
    clean_telemetry_df,
    clean_weather_df,
)
from backend_analytics.pipeline.synthetic_generator import (
    FORMOSA_NEIGHBORHOODS,
    generate_synthetic_formosa_dataset,
)


def _sample_telemetry() -> pd.DataFrame:
    return pd.DataFrame(
        [
            {
                "event_uuid": "550e8400-e29b-41d4-a716-446655440001",
                "caps_code": "CAPS-CODDA-01",
                "neighborhood_id": 14,
                "neighborhood_name": "Lote 4",
                "age_group": "adulto",
                "triage_priority": "yellow",
                "presumptive_diagnosis": "sospecha_dengue",
                "symptoms_summary": "fiebre_mialgias_cefalea",
                "temperature_c": 39.2,
                "recorded_at": "2026-01-06T10:30:00-03:00",
            },
            # Duplicado idempotente por event_uuid (debe eliminarse)
            {
                "event_uuid": "550e8400-e29b-41d4-a716-446655440001",
                "caps_code": "CAPS-CODDA-01",
                "neighborhood_id": 14,
                "neighborhood_name": "Lote 4",
                "age_group": "adulto",
                "triage_priority": "yellow",
                "presumptive_diagnosis": "sospecha_dengue",
                "symptoms_summary": "fiebre_mialgias_cefalea",
                "temperature_c": 39.2,
                "recorded_at": "2026-01-06T10:30:00-03:00",
            },
            {
                "event_uuid": "550e8400-e29b-41d4-a716-446655440002",
                "caps_code": "CAPS-CODDA-01",
                "neighborhood_id": 14,
                "neighborhood_name": "Lote 4",
                "age_group": "infante",
                "triage_priority": "orange",
                "presumptive_diagnosis": "sindrome_febril_inespecifico",
                "symptoms_summary": "fiebre_decaimiento",
                "temperature_c": 38.6,
                "recorded_at": "2026-01-07T15:00:00-03:00",
            },
            # Consulta no febril con temperatura fuera de rango fisiológico (debe sanearse a NaN)
            {
                "event_uuid": "550e8400-e29b-41d4-a716-446655440003",
                "caps_code": "CAPS-CODDA-01",
                "neighborhood_id": 14,
                "neighborhood_name": "Lote 4",
                "age_group": "mayor",
                "triage_priority": "green",
                "presumptive_diagnosis": "traumatismo_herida",
                "symptoms_summary": "herida_leve",
                "temperature_c": 99.9,
                "recorded_at": "2026-01-08T09:15:00-03:00",
            },
            # Barrio 1 en semana siguiente (EpiWeek 3)
            {
                "event_uuid": "550e8400-e29b-41d4-a716-446655440004",
                "caps_code": "CAPS-CODDA-01",
                "neighborhood_id": 1,
                "neighborhood_name": "Centro",
                "age_group": "adulto",
                "triage_priority": "red",
                "presumptive_diagnosis": "ira",
                "symptoms_summary": "disnea_fiebre",
                "temperature_c": 38.4,
                "recorded_at": "2026-01-13T18:00:00-03:00",
            },
        ]
    )


def _sample_weather() -> pd.DataFrame:
    dates = pd.date_range("2026-01-05", "2026-01-18", freq="D")
    rows = []
    for idx, day in enumerate(dates):
        rows.append(
            {
                "recorded_date": day.strftime("%Y-%m-%d"),
                "temperature_max": 36.0 if idx != 3 else None,  # Faltante a imputar
                "temperature_min": 24.0,
                "precipitation_sum": 45.0 if idx in (1, 8) else 2.0,
                "relative_humidity_mean": 78.0 if idx != 5 else None,
            }
        )
    return pd.DataFrame(rows)


@pytest.mark.parametrize(
    "pii_column",
    ["dni", "first_name", "last_name", "full_name", "phone", "address", "birth_date"],
)
def test_clean_telemetry_rejects_pii_columns_ley_25326(pii_column: str) -> None:
    df = _sample_telemetry()
    df[pii_column] = "dato_sensible"

    with pytest.raises(ValueError, match="25.326"):
        clean_telemetry_df(df)


def test_clean_telemetry_deduplicates_sanitizes_and_classifies_febrile() -> None:
    cleaned = clean_telemetry_df(_sample_telemetry())

    assert len(cleaned) == 4
    assert cleaned["event_uuid"].is_unique
    assert cleaned["is_febrile"].sum() == 3  # dengue + sfi + ira
    assert cleaned["is_dengue_suspect"].sum() == 1
    assert cleaned["is_ira"].sum() == 1
    assert cleaned["is_high_priority"].sum() == 2  # orange + red

    # Temperatura 99.9 fuera de rango fisiológico debe convertirse a NaN
    trauma_row = cleaned.loc[cleaned["presumptive_diagnosis"] == "traumatismo_herida"].iloc[0]
    assert pd.isna(trauma_row["temperature_c"])

    # Diagnósticos febriles canónicos según 00_contracts.md §5.1
    assert FEBRILE_DIAGNOSES == {"sindrome_febril_inespecifico", "sospecha_dengue", "ira"}


def test_clean_weather_imputes_missing_days_and_clamps_physical_ranges() -> None:
    weather = _sample_weather()
    # Eliminamos un día intermedio e inyectamos precipitación negativa inválida
    weather = weather.drop(index=2).reset_index(drop=True)
    weather.loc[0, "precipitation_sum"] = -12.5

    cleaned = clean_weather_df(weather)

    assert len(cleaned) == 14  # Serie diaria continua 2026-01-05 .. 2026-01-18
    assert cleaned["recorded_date"].is_monotonic_increasing
    assert (cleaned["precipitation_sum"] >= 0.0).all()
    assert cleaned["temperature_max"].isna().sum() == 0
    assert cleaned["relative_humidity_mean"].isna().sum() == 0
    assert ((cleaned["relative_humidity_mean"] >= 0.0) & (cleaned["relative_humidity_mean"] <= 100.0)).all()
    assert "temperature_mean" in cleaned.columns


def test_build_daily_series_creates_complete_grid_for_all_neighborhoods() -> None:
    daily = build_daily_series(_sample_telemetry(), _sample_weather(), neighborhood_ids=[1, 14])

    assert len(daily) == 14 * 2
    assert set(daily["neighborhood_id"].unique()) == {1, 14}
    assert daily["febrile_cases"].sum() == 3
    assert daily["total_consultations"].sum() == 4
    assert daily["precipitation_sum"].isna().sum() == 0


def test_build_epiweek_dataset_aggregates_by_epiweek_and_neighborhood() -> None:
    epi = build_epiweek_dataset(_sample_telemetry(), _sample_weather(), neighborhood_ids=[1, 14])

    # 2026-01-05..2026-01-11 = ISO semana 2; 2026-01-12..2026-01-18 = ISO semana 3
    assert len(epi) == 4  # 2 barrios x 2 semanas epidemiológicas
    assert set(epi["epi_week"].unique()) == {2, 3}
    assert set(epi["epi_year"].unique()) == {2026}

    lote4_w2 = epi[(epi["neighborhood_id"] == 14) & (epi["epi_week"] == 2)].iloc[0]
    assert lote4_w2["total_consultations"] == 3
    assert lote4_w2["febrile_cases"] == 2
    assert lote4_w2["dengue_suspect_cases"] == 1
    assert lote4_w2["high_priority_cases"] == 1
    assert lote4_w2["febrile_ratio"] == pytest.approx(2 / 3, rel=1e-4)
    assert lote4_w2["precipitation_sum"] == pytest.approx(45.0 + 6 * 2.0, rel=1e-4)
    assert lote4_w2["precipitation_max_daily"] == pytest.approx(45.0, rel=1e-4)

    # Barrio 1 en semana 2 sin consultas debe existir con ceros y ratio 0.0
    centro_w2 = epi[(epi["neighborhood_id"] == 1) & (epi["epi_week"] == 2)].iloc[0]
    assert centro_w2["total_consultations"] == 0
    assert centro_w2["febrile_cases"] == 0
    assert centro_w2["febrile_ratio"] == 0.0

    # Barrio 1 en semana 3 con 1 caso IRA rojo
    centro_w3 = epi[(epi["neighborhood_id"] == 1) & (epi["epi_week"] == 3)].iloc[0]
    assert centro_w3["ira_cases"] == 1
    assert centro_w3["febrile_cases"] == 1
    assert centro_w3["high_priority_cases"] == 1


def test_synthetic_formosa_generator_is_reproducible_and_privacy_compliant() -> None:
    telemetry_a, weather_a = generate_synthetic_formosa_dataset(n_weeks=12, n_neighborhoods=6, seed=42)
    telemetry_b, weather_b = generate_synthetic_formosa_dataset(n_weeks=12, n_neighborhoods=6, seed=42)

    assert len(FORMOSA_NEIGHBORHOODS) >= 24
    assert len(weather_a) == 12 * 7
    assert len(telemetry_a) > 0
    assert telemetry_a.equals(telemetry_b)
    assert weather_a.equals(weather_b)

    # Cero columnas PII en la telemetría sintética
    lower_cols = {c.lower() for c in telemetry_a.columns}
    assert lower_cols.isdisjoint(FORBIDDEN_PII_COLUMNS)

    # Contrato §4.1 completo
    expected_cols = {
        "event_uuid",
        "caps_code",
        "neighborhood_id",
        "neighborhood_name",
        "age_group",
        "triage_priority",
        "presumptive_diagnosis",
        "symptoms_summary",
        "temperature_c",
        "recorded_at",
    }
    assert expected_cols.issubset(set(telemetry_a.columns))
    assert telemetry_a["event_uuid"].is_unique

    # Compatible directo con build_epiweek_dataset
    epi = build_epiweek_dataset(telemetry_a, weather_a)
    assert len(epi) == 12 * 6
    assert epi["febrile_cases"].sum() > 0


def test_clean_telemetry_handles_empty_and_timezone_boundary_correctly() -> None:
    empty_df = pd.DataFrame(
        columns=["event_uuid", "neighborhood_id", "presumptive_diagnosis", "recorded_at"]
    )
    cleaned_empty = clean_telemetry_df(empty_df)
    assert cleaned_empty.empty

    # 2026-01-12T01:30:00Z corresponde a 2026-01-11T22:30:00-03:00 en Formosa (domingo EpiWeek 2)
    boundary_df = pd.DataFrame(
        [
            {
                "event_uuid": "550e8400-e29b-41d4-a716-446655440099",
                "neighborhood_id": 14,
                "presumptive_diagnosis": "sospecha_dengue",
                "recorded_at": "2026-01-12T01:30:00Z",
            }
        ]
    )
    cleaned = clean_telemetry_df(boundary_df)
    assert cleaned.iloc[0]["recorded_date"] == pd.Timestamp("2026-01-11")
    assert cleaned.iloc[0]["epi_week"] == 2

    epi = build_epiweek_dataset(empty_df, _sample_weather(), neighborhood_ids=[4, 14])
    assert len(epi) == 4
    assert (epi["total_consultations"] == 0).all()


def test_clean_functions_raise_on_missing_required_columns() -> None:
    with pytest.raises(ValueError, match="obligatorias"):
        clean_telemetry_df(pd.DataFrame([{"event_uuid": "abc"}]))

    with pytest.raises(ValueError, match="obligatorias"):
        clean_weather_df(pd.DataFrame([{"recorded_date": "2026-01-01"}]))
