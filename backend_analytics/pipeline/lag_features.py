"""Modelado matemático del retardo temporal (time-lag de 7 a 14 días) lluvia-casos (CEN-55).

Genera variables con retardo alineadas al ciclo biológico del mosquito vector *Aedes aegypti*
(huevo → larva → pupa → adulto infectivo ≈ 7 a 14 días tras precipitaciones y temperaturas cálidas)
sin fuga de datos futuros (*data leakage*) y con aislamiento estricto por barrio.
"""

from __future__ import annotations

import numpy as np
import pandas as pd

# Orden canónico de features consumido por entrenamiento (CEN-56) e inferencia (T6.4)
FEATURE_COLUMNS: tuple[str, ...] = (
    "rain_lag_7d",
    "rain_lag_14d",
    "rain_accum_7_14d",
    "rain_max_daily_lag_7d",
    "rainy_days_lag_7d",
    "rain_above_threshold_lag_7d",
    "temp_mean_lag_7d",
    "temp_mean_lag_14d",
    "humidity_mean_lag_7d",
    "vector_suitability_lag_7_14d",
    "febrile_cases_lag_7d",
    "febrile_cases_lag_14d",
    "febrile_rolling_4w_lag_7d",
    "dengue_cases_lag_7d",
    "febrile_ratio_lag_7d",
    "febrile_growth_rate",
    "epi_week_sin",
    "epi_week_cos",
)

REQUIRED_EPIWEEK_COLUMNS: frozenset[str] = frozenset(
    {
        "neighborhood_id",
        "epi_week",
        "week_start_date",
        "precipitation_sum",
        "precipitation_max_daily",
        "rainy_days",
        "temperature_mean",
        "relative_humidity_mean",
        "febrile_cases",
        "dengue_suspect_cases",
        "febrile_ratio",
    }
)

REQUIRED_DAILY_COLUMNS: frozenset[str] = frozenset(
    {
        "neighborhood_id",
        "recorded_date",
        "precipitation_sum",
        "temperature_mean",
        "relative_humidity_mean",
        "febrile_cases",
    }
)


def compute_vector_suitability_index(
    rain_accum_mm: pd.Series,
    temp_mean_c: pd.Series,
    humidity_mean_pct: pd.Series,
    rain_threshold_mm: float = 40.0,
) -> pd.Series:
    """Calcula el índice bioclimático compuesto [0.0, 1.0] de aptitud del vector *Aedes aegypti*.

    Modela la interacción no lineal de tres factores físicos rezagados:
    - Precipitación acumulada (disponibilidad de criaderos al superar el umbral operativo).
    - Temperatura media (umbral biológico inferior ~18 °C, óptimo 26-32 °C, estrés térmico >37 °C).
    - Humedad relativa media (supervivencia del mosquito adulto en clima subtropical de Formosa).
    """
    if rain_threshold_mm <= 0:
        raise ValueError("rain_threshold_mm debe ser mayor a 0.")

    rain = pd.to_numeric(rain_accum_mm, errors="coerce").fillna(0.0).to_numpy(dtype=np.float64)
    temp = pd.to_numeric(temp_mean_c, errors="coerce").fillna(22.0).to_numpy(dtype=np.float64)
    hum = pd.to_numeric(humidity_mean_pct, errors="coerce").fillna(65.0).to_numpy(dtype=np.float64)

    # Curva logística centrada en el umbral de lluvia configurable (por defecto 40 mm)
    rain_scale = max(rain_threshold_mm * 0.35, 10.0)
    rain_factor = 1.0 / (1.0 + np.exp(-(rain - rain_threshold_mm) / rain_scale))

    # Respuesta térmica unimodal: nula bajo 17 °C, óptima entre 26 y 33 °C, cae sobre 37 °C
    temp_rise = np.clip((temp - 17.0) / 10.5, 0.0, 1.0)
    temp_heat_penalty = np.clip((42.0 - temp) / 6.0, 0.25, 1.0)
    temp_factor = temp_rise * temp_heat_penalty

    # Humedad relativa: óptima >= 70 %, limitada debajo de 45 %
    hum_factor = np.clip((hum - 40.0) / 40.0, 0.15, 1.0)

    suitability = np.clip(rain_factor * temp_factor * hum_factor, 0.0, 1.0)
    return pd.Series(suitability, index=rain_accum_mm.index, dtype="float64")


def build_epiweek_lag_features(
    epiweek_df: pd.DataFrame,
    *,
    rain_threshold_mm: float = 40.0,
    drop_incomplete: bool = True,
) -> pd.DataFrame:
    """Genera la matriz de variables con retardo (7 y 14 días) sobre series semanales EpiWeek.

    Todas las variables climáticas y epidemiológicas se desplazan estrictamente hacia el pasado
    (`shift(1)` = 7 días, `shift(2)` = 14 días) dentro de cada `neighborhood_id` ordenado
    cronológicamente, impidiendo fuga de datos del barrio vecino o de la semana objetivo `t`.
    """
    missing = REQUIRED_EPIWEEK_COLUMNS - set(epiweek_df.columns)
    if missing:
        raise ValueError(
            f"Columnas obligatorias faltantes en serie EpiWeek: {sorted(missing)}"
        )

    if rain_threshold_mm <= 0:
        raise ValueError("rain_threshold_mm debe ser mayor a 0.")

    df = epiweek_df.copy()
    df["week_start_date"] = pd.to_datetime(df["week_start_date"])
    df = df.sort_values(["neighborhood_id", "week_start_date"]).reset_index(drop=True)

    grouped = df.groupby("neighborhood_id", sort=False)

    # Retardo meteorológico 7 días (1 EpiWeek) y 14 días (2 EpiWeeks)
    df["rain_lag_7d"] = grouped["precipitation_sum"].shift(1).astype("float64")
    df["rain_lag_14d"] = grouped["precipitation_sum"].shift(2).astype("float64")
    df["rain_accum_7_14d"] = df["rain_lag_7d"] + df["rain_lag_14d"]
    df["rain_max_daily_lag_7d"] = grouped["precipitation_max_daily"].shift(1).astype("float64")
    df["rainy_days_lag_7d"] = grouped["rainy_days"].shift(1).astype("float64")
    df["rain_above_threshold_lag_7d"] = np.where(
        df["rain_lag_7d"].notna(),
        (df["rain_lag_7d"] >= rain_threshold_mm).astype("float64"),
        np.nan,
    )

    df["temp_mean_lag_7d"] = grouped["temperature_mean"].shift(1).astype("float64")
    df["temp_mean_lag_14d"] = grouped["temperature_mean"].shift(2).astype("float64")
    df["humidity_mean_lag_7d"] = grouped["relative_humidity_mean"].shift(1).astype("float64")

    # Índice compuesto bioclimático estrictamente con variables rezagadas
    suitability = compute_vector_suitability_index(
        rain_accum_mm=df["rain_accum_7_14d"],
        temp_mean_c=df["temp_mean_lag_7d"],
        humidity_mean_pct=df["humidity_mean_lag_7d"],
        rain_threshold_mm=rain_threshold_mm,
    )
    incomplete_mask = (
        df["rain_accum_7_14d"].isna()
        | df["temp_mean_lag_7d"].isna()
        | df["humidity_mean_lag_7d"].isna()
    )
    df["vector_suitability_lag_7_14d"] = suitability.mask(incomplete_mask, np.nan)

    # Inercia epidemiológica estrictamente rezagada (1 y 2 semanas atrás + promedio móvil 4 semanas)
    df["febrile_cases_lag_7d"] = grouped["febrile_cases"].shift(1).astype("float64")
    df["febrile_cases_lag_14d"] = grouped["febrile_cases"].shift(2).astype("float64")
    df["febrile_rolling_4w_lag_7d"] = (
        grouped["febrile_cases"]
        .transform(lambda s: s.shift(1).rolling(4, min_periods=1).mean())
        .astype("float64")
    )
    df["dengue_cases_lag_7d"] = grouped["dengue_suspect_cases"].shift(1).astype("float64")
    df["febrile_ratio_lag_7d"] = grouped["febrile_ratio"].shift(1).astype("float64")
    df["febrile_growth_rate"] = (
        df["febrile_cases_lag_7d"] - df["febrile_cases_lag_14d"]
    ) / (df["febrile_cases_lag_14d"] + 1.0)

    # Estacionalidad cíclica (calendario determinístico sin discontinuidad semana 52 -> 1)
    radians = 2.0 * np.pi * df["epi_week"].astype("float64") / 52.0
    df["epi_week_sin"] = np.sin(radians)
    df["epi_week_cos"] = np.cos(radians)

    if drop_incomplete:
        df = df.dropna(subset=list(FEATURE_COLUMNS)).reset_index(drop=True)

    return df


def build_daily_lag_features(
    daily_df: pd.DataFrame,
    *,
    lag_days: tuple[int, ...] = (7, 10, 14),
    rolling_window_days: int = 7,
    drop_incomplete: bool = True,
) -> pd.DataFrame:
    """Genera variables con retardo exacto en días (p. ej. 7, 10 y 14 días) sobre series diarias.

    Soporta la ventana configurable `time_lag_days` (7 a 14 días) definida en `epidemiology_settings`.
    """
    missing = REQUIRED_DAILY_COLUMNS - set(daily_df.columns)
    if missing:
        raise ValueError(f"Columnas obligatorias faltantes en serie diaria: {sorted(missing)}")

    if not lag_days or any(int(d) <= 0 for d in lag_days):
        raise ValueError("lag_days debe contener al menos un entero positivo.")
    if rolling_window_days <= 0:
        raise ValueError("rolling_window_days debe ser mayor a 0.")

    df = daily_df.copy()
    df["recorded_date"] = pd.to_datetime(df["recorded_date"])
    df = df.sort_values(["neighborhood_id", "recorded_date"]).reset_index(drop=True)

    grouped = df.groupby("neighborhood_id", sort=False)
    generated_cols: list[str] = []

    for lag in sorted({int(d) for d in lag_days}):
        rain_col = f"rain_lag_{lag}d"
        df[rain_col] = grouped["precipitation_sum"].shift(lag).astype("float64")
        generated_cols.append(rain_col)

        rolling_col = f"rain_rolling_{rolling_window_days}d_lag_{lag}d"
        df[rolling_col] = (
            grouped["precipitation_sum"]
            .transform(
                lambda s, w=rolling_window_days, k=lag: s.rolling(w, min_periods=1).sum().shift(k)
            )
            .astype("float64")
        )
        generated_cols.append(rolling_col)

    base_lag = 7 if 7 in lag_days else min(lag_days)
    df[f"temp_mean_lag_{base_lag}d"] = grouped["temperature_mean"].shift(base_lag).astype("float64")
    df[f"humidity_mean_lag_{base_lag}d"] = (
        grouped["relative_humidity_mean"].shift(base_lag).astype("float64")
    )
    generated_cols.extend([f"temp_mean_lag_{base_lag}d", f"humidity_mean_lag_{base_lag}d"])

    if drop_incomplete:
        df = df.dropna(subset=generated_cols).reset_index(drop=True)

    return df


def compute_rain_case_lag_correlation(
    daily_df: pd.DataFrame,
    *,
    min_lag: int = 0,
    max_lag: int = 21,
    rain_window_days: int = 7,
) -> pd.DataFrame:
    """Evalúa la correlación cruzada (Pearson y Spearman) entre lluvia acumulada rezagada y casos febriles.

    Permite auditar empíricamente el desfase biológico de 7 a 14 días entre eventos de lluvia
    y el aumento de consultas febriles por barrio en Formosa Capital.
    """
    missing = {"neighborhood_id", "recorded_date", "precipitation_sum", "febrile_cases"} - set(
        daily_df.columns
    )
    if missing:
        raise ValueError(f"Columnas obligatorias faltantes para correlación: {sorted(missing)}")
    if min_lag < 0 or max_lag < min_lag:
        raise ValueError("Rango de retardos inválido (debe cumplir 0 <= min_lag <= max_lag).")

    df = daily_df.copy()
    df["recorded_date"] = pd.to_datetime(df["recorded_date"])
    df = df.sort_values(["neighborhood_id", "recorded_date"]).reset_index(drop=True)

    # Agregado diario de ciudad para evaluar señal climática global frente a casos febriles totales
    city_daily = (
        df.groupby("recorded_date", as_index=False)
        .agg(
            precipitation_sum=("precipitation_sum", "mean"),
            febrile_cases=("febrile_cases", "sum"),
        )
        .sort_values("recorded_date")
        .reset_index(drop=True)
    )

    rain_roll = city_daily["precipitation_sum"].rolling(rain_window_days, min_periods=1).sum()
    febrile_roll = city_daily["febrile_cases"].rolling(rain_window_days, min_periods=1).sum()

    rows: list[dict[str, float | int]] = []
    for lag in range(min_lag, max_lag + 1):
        shifted_rain = rain_roll.shift(lag)
        valid = pd.DataFrame({"rain": shifted_rain, "febrile": febrile_roll}).dropna()
        if len(valid) < 3 or valid["rain"].std() == 0 or valid["febrile"].std() == 0:
            pearson_r = 0.0
            spearman_rho = 0.0
        else:
            pearson_r = float(valid["rain"].corr(valid["febrile"], method="pearson"))
            spearman_rho = float(valid["rain"].corr(valid["febrile"], method="spearman"))

        rows.append(
            {
                "lag_days": lag,
                "pearson_r": round(pearson_r, 4),
                "spearman_rho": round(spearman_rho, 4),
                "n_observations": int(len(valid)),
            }
        )

    return pd.DataFrame(rows)
