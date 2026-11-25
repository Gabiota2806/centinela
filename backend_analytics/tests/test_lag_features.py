"""Pruebas unitarias para el modelado de retardo temporal lluvia-casos (CEN-55)."""

from __future__ import annotations

import numpy as np
import pandas as pd
import pytest

from backend_analytics.pipeline.dataset_builder import (
    build_daily_series,
    build_epiweek_dataset,
)
from backend_analytics.pipeline.lag_features import (
    FEATURE_COLUMNS,
    build_daily_lag_features,
    build_epiweek_lag_features,
    compute_rain_case_lag_correlation,
    compute_vector_suitability_index,
)
from backend_analytics.pipeline.synthetic_generator import generate_synthetic_formosa_dataset


def _make_two_neighborhood_epiweek_df() -> pd.DataFrame:
    """Serie controlada de 4 EpiWeeks para 2 barrios con valores exactos conocidos."""
    weeks = pd.date_range("2026-01-05", periods=4, freq="7D")
    rows = []
    # Barrio 1: lluvia [10, 50, 20, 0], temp [26, 28, 30, 32], febriles [1, 2, 5, 8]
    b1_rain = [10.0, 50.0, 20.0, 0.0]
    b1_temp = [26.0, 28.0, 30.0, 32.0]
    b1_feb = [1, 2, 5, 8]
    for idx, ws in enumerate(weeks):
        rows.append(
            {
                "neighborhood_id": 1,
                "epi_year": 2026,
                "epi_week": idx + 2,
                "week_start_date": ws,
                "days_in_week": 7,
                "total_consultations": 10,
                "febrile_cases": b1_feb[idx],
                "dengue_suspect_cases": max(0, b1_feb[idx] - 1),
                "ira_cases": 0,
                "high_priority_cases": 1,
                "febrile_ratio": b1_feb[idx] / 10.0,
                "dengue_ratio": max(0, b1_feb[idx] - 1) / 10.0,
                "mean_temperature_c": 38.5,
                "precipitation_sum": b1_rain[idx],
                "precipitation_max_daily": b1_rain[idx] * 0.6,
                "rainy_days": 2 if b1_rain[idx] >= 20 else 0,
                "temperature_max_mean": b1_temp[idx] + 5.0,
                "temperature_min_mean": b1_temp[idx] - 5.0,
                "temperature_mean": b1_temp[idx],
                "relative_humidity_mean": 75.0 + idx,
            }
        )

    # Barrio 14: lluvia [80, 5, 60, 15], temp [29, 31, 27, 25], febriles [0, 3, 1, 6]
    b14_rain = [80.0, 5.0, 60.0, 15.0]
    b14_temp = [29.0, 31.0, 27.0, 25.0]
    b14_feb = [0, 3, 1, 6]
    for idx, ws in enumerate(weeks):
        rows.append(
            {
                "neighborhood_id": 14,
                "epi_year": 2026,
                "epi_week": idx + 2,
                "week_start_date": ws,
                "days_in_week": 7,
                "total_consultations": 10,
                "febrile_cases": b14_feb[idx],
                "dengue_suspect_cases": b14_feb[idx] // 2,
                "ira_cases": 0,
                "high_priority_cases": 1,
                "febrile_ratio": b14_feb[idx] / 10.0,
                "dengue_ratio": (b14_feb[idx] // 2) / 10.0,
                "mean_temperature_c": 38.8,
                "precipitation_sum": b14_rain[idx],
                "precipitation_max_daily": b14_rain[idx] * 0.7,
                "rainy_days": 3 if b14_rain[idx] >= 50 else 1,
                "temperature_max_mean": b14_temp[idx] + 5.0,
                "temperature_min_mean": b14_temp[idx] - 5.0,
                "temperature_mean": b14_temp[idx],
                "relative_humidity_mean": 82.0 - idx,
            }
        )

    # Desordenamos a propósito para verificar que la función ordene internamente antes del shift
    return pd.DataFrame(rows).sample(frac=1.0, random_state=7).reset_index(drop=True)


def test_epiweek_lag_features_exact_values_and_neighborhood_isolation() -> None:
    epi_df = _make_two_neighborhood_epiweek_df()
    result = build_epiweek_lag_features(epi_df, rain_threshold_mm=40.0, drop_incomplete=True)

    # 4 semanas por barrio -> se descartan las 2 primeras (sin lag 14d), quedan 2 por barrio = 4 filas
    assert len(result) == 4
    assert list(FEATURE_COLUMNS) == [col for col in FEATURE_COLUMNS if col in result.columns]
    assert result[list(FEATURE_COLUMNS)].isna().sum().sum() == 0

    # Verificar exactitud en Barrio 1 semana 4 (índice 2: epi_week=4)
    b1_w4 = result[(result["neighborhood_id"] == 1) & (result["epi_week"] == 4)].iloc[0]
    assert b1_w4["rain_lag_7d"] == pytest.approx(50.0)
    assert b1_w4["rain_lag_14d"] == pytest.approx(10.0)
    assert b1_w4["rain_accum_7_14d"] == pytest.approx(60.0)
    assert b1_w4["rain_above_threshold_lag_7d"] == 1.0
    assert b1_w4["temp_mean_lag_7d"] == pytest.approx(28.0)
    assert b1_w4["temp_mean_lag_14d"] == pytest.approx(26.0)
    assert b1_w4["febrile_cases_lag_7d"] == 2.0
    assert b1_w4["febrile_cases_lag_14d"] == 1.0
    assert b1_w4["febrile_rolling_4w_lag_7d"] == pytest.approx(1.5)
    assert b1_w4["febrile_growth_rate"] == pytest.approx((2.0 - 1.0) / (1.0 + 1.0))

    # Verificar aislamiento total en Barrio 14 (no contaminado por Barrio 1)
    b14_w4 = result[(result["neighborhood_id"] == 14) & (result["epi_week"] == 4)].iloc[0]
    assert b14_w4["rain_lag_7d"] == pytest.approx(5.0)
    assert b14_w4["rain_lag_14d"] == pytest.approx(80.0)
    assert b14_w4["rain_above_threshold_lag_7d"] == 0.0
    assert b14_w4["temp_mean_lag_7d"] == pytest.approx(31.0)
    assert b14_w4["febrile_cases_lag_7d"] == 3.0
    assert b14_w4["febrile_cases_lag_14d"] == 0.0


def test_epiweek_lag_features_has_zero_data_leakage_from_current_week() -> None:
    epi_df = _make_two_neighborhood_epiweek_df()
    baseline = build_epiweek_lag_features(epi_df, drop_incomplete=True)

    # Alteramos drásticamente los valores de la última semana (epi_week=5) en ambos barrios
    mutated = epi_df.copy()
    mask_last = mutated["epi_week"] == 5
    mutated.loc[mask_last, "febrile_cases"] = 999
    mutated.loc[mask_last, "dengue_suspect_cases"] = 888
    mutated.loc[mask_last, "precipitation_sum"] = 777.0
    mutated.loc[mask_last, "temperature_mean"] = 45.0

    mutated_features = build_epiweek_lag_features(mutated, drop_incomplete=True)

    base_last = baseline[baseline["epi_week"] == 5][list(FEATURE_COLUMNS)].reset_index(drop=True)
    mut_last = mutated_features[mutated_features["epi_week"] == 5][list(FEATURE_COLUMNS)].reset_index(drop=True)

    # Ningún feature de la semana t debe cambiar al modificar las observaciones de la semana t
    pd.testing.assert_frame_equal(base_last, mut_last)


def test_daily_lag_features_exact_7_10_14_day_shifts() -> None:
    dates = pd.date_range("2026-01-01", periods=20, freq="D")
    daily_df = pd.DataFrame(
        {
            "neighborhood_id": [14] * 20,
            "recorded_date": dates,
            "precipitation_sum": [float(i * 3) for i in range(20)],
            "temperature_mean": [20.0 + float(i) * 0.5 for i in range(20)],
            "relative_humidity_mean": [60.0 + float(i) for i in range(20)],
            "febrile_cases": [i % 4 for i in range(20)],
        }
    )

    lagged = build_daily_lag_features(daily_df, lag_days=(7, 10, 14), drop_incomplete=True)

    # Con max lag 14 días, el primer día completo es índice 14 (2026-01-15) -> 6 filas
    assert len(lagged) == 6
    first_row = lagged.iloc[0]
    assert first_row["recorded_date"] == pd.Timestamp("2026-01-15")
    assert first_row["rain_lag_7d"] == pytest.approx(7 * 3.0)
    assert first_row["rain_lag_10d"] == pytest.approx(4 * 3.0)
    assert first_row["rain_lag_14d"] == pytest.approx(0.0)
    assert first_row["temp_mean_lag_7d"] == pytest.approx(20.0 + 7 * 0.5)
    assert first_row["humidity_mean_lag_7d"] == pytest.approx(67.0)


def test_vector_suitability_index_responds_biologically_to_climate() -> None:
    idx = compute_vector_suitability_index(
        rain_accum_mm=pd.Series([0.0, 90.0, 90.0]),
        temp_mean_c=pd.Series([15.0, 15.0, 29.0]),
        humidity_mean_pct=pd.Series([50.0, 80.0, 82.0]),
        rain_threshold_mm=40.0,
    )

    assert ((idx >= 0.0) & (idx <= 1.0)).all()
    # Invierno frío (15 °C) inhibe al vector aunque haya lluvia; verano lluvioso (29 °C + 90 mm) dispara el índice
    assert idx.iloc[2] > 0.75
    assert idx.iloc[1] < 0.20
    assert idx.iloc[0] < idx.iloc[2]


def test_synthetic_formosa_dataset_exhibits_positive_7_to_14d_rain_febrile_correlation() -> None:
    telemetry_df, weather_df = generate_synthetic_formosa_dataset(
        n_weeks=52, n_neighborhoods=10, seed=42
    )
    daily_df = build_daily_series(telemetry_df, weather_df)
    corr_df = compute_rain_case_lag_correlation(daily_df, min_lag=0, max_lag=21)

    assert {"lag_days", "pearson_r", "spearman_rho"}.issubset(set(corr_df.columns))
    window_7_14 = corr_df[(corr_df["lag_days"] >= 7) & (corr_df["lag_days"] <= 14)]
    # La correlación entre lluvia acumulada rezagada 7-14 días y casos febriles debe ser claramente positiva
    assert window_7_14["pearson_r"].max() > 0.15
    assert window_7_14["spearman_rho"].max() > 0.15

    # Y el pipeline completo EpiWeek + lags produce matriz limpia sin NaN ni infinitos
    epi_df = build_epiweek_dataset(telemetry_df, weather_df)
    features_df = build_epiweek_lag_features(epi_df, drop_incomplete=True)
    matrix = features_df[list(FEATURE_COLUMNS)].to_numpy()
    assert np.isfinite(matrix).all()
    assert len(features_df) == (52 - 2) * 10


def test_lag_features_validation_errors_and_keep_incomplete_option() -> None:
    epi_df = _make_two_neighborhood_epiweek_df()

    kept = build_epiweek_lag_features(epi_df, drop_incomplete=False)
    assert len(kept) == len(epi_df)
    assert kept["rain_lag_14d"].isna().sum() == 4  # 2 primeras semanas x 2 barrios

    with pytest.raises(ValueError, match="obligatorias"):
        build_epiweek_lag_features(pd.DataFrame([{"neighborhood_id": 1}]))

    with pytest.raises(ValueError, match="mayor a 0"):
        build_epiweek_lag_features(epi_df, rain_threshold_mm=0.0)

    with pytest.raises(ValueError, match="entero positivo"):
        build_daily_lag_features(
            pd.DataFrame(
                [
                    {
                        "neighborhood_id": 1,
                        "recorded_date": "2026-01-01",
                        "precipitation_sum": 0.0,
                        "temperature_mean": 25.0,
                        "relative_humidity_mean": 70.0,
                        "febrile_cases": 0,
                    }
                ]
            ),
            lag_days=(0, 7),
        )
