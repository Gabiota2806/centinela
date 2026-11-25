"""Generador reproducible de dataset sintético epidemiológico de Formosa Capital (CEN-54).

Simula series climáticas subtropicales húmedas realistas para Formosa Capital
(lat=-26.18, lon=-58.17) y eventos de telemetría clínica estrictamente anonimizados
(Ley N° 25.326 / docs/specs/00_contracts.md §4.1) cuyos casos febriles y sospechas
de dengue responden al retardo biológico de 7 a 14 días tras lluvias y calor.
"""

from __future__ import annotations

import uuid
from dataclasses import dataclass

import numpy as np
import pandas as pd


@dataclass(frozen=True)
class NeighborhoodProfile:
    """Perfil epidemiológico base de un barrio oficial de Formosa Capital."""

    neighborhood_id: int
    name: str
    vulnerability: float  # Factor relativo de vulnerabilidad hídrica / densidad (0.6 - 1.8)
    caps_code: str


# Catálogo representativo alineado con los IDs y nombres de database/seeders/NeighborhoodSeeder.php
FORMOSA_NEIGHBORHOODS: tuple[NeighborhoodProfile, ...] = (
    NeighborhoodProfile(1, "Centro", 0.65, "CAPS-CENTRO-01"),
    NeighborhoodProfile(2, "San Martín", 0.70, "CAPS-CENTRO-01"),
    NeighborhoodProfile(3, "Don Bosco", 0.75, "CAPS-CENTRO-01"),
    NeighborhoodProfile(4, "San Miguel", 1.30, "CAPS-CODDA-01"),
    NeighborhoodProfile(5, "San Pedro", 0.90, "CAPS-CODDA-01"),
    NeighborhoodProfile(6, "Belgrano", 0.80, "CAPS-CENTRO-01"),
    NeighborhoodProfile(7, "Bernardino Rivadavia", 1.15, "CAPS-SUR-04"),
    NeighborhoodProfile(8, "Mariano Moreno", 0.95, "CAPS-CODDA-01"),
    NeighborhoodProfile(9, "Obrero", 1.25, "CAPS-SUR-04"),
    NeighborhoodProfile(10, "Villa Hermosa", 1.10, "CAPS-SUR-04"),
    NeighborhoodProfile(11, "La Pilar", 1.05, "CAPS-SUR-04"),
    NeighborhoodProfile(12, "San Francisco", 1.00, "CAPS-CODDA-01"),
    NeighborhoodProfile(14, "Lote 4", 1.75, "CAPS-CODDA-01"),
    NeighborhoodProfile(22, "Fontana", 1.10, "CAPS-CODDA-01"),
    NeighborhoodProfile(34, "Santa Rosa", 1.20, "CAPS-CODDA-01"),
    NeighborhoodProfile(44, "Circuito Cinco", 1.55, "CAPS-C5-03"),
    NeighborhoodProfile(45, "Eva Perón", 1.50, "CAPS-C5-03"),
    NeighborhoodProfile(46, "República Argentina", 1.40, "CAPS-C5-03"),
    NeighborhoodProfile(48, "Antenor Gauna", 1.60, "CAPS-C5-03"),
    NeighborhoodProfile(56, "Namqom", 1.70, "CAPS-NAMQOM-02"),
    NeighborhoodProfile(80, "Liborsi", 0.95, "CAPS-SUR-04"),
    NeighborhoodProfile(81, "El Pucú", 1.05, "CAPS-SUR-04"),
    NeighborhoodProfile(82, "Villa del Carmen", 1.35, "CAPS-SUR-04"),
    NeighborhoodProfile(83, "La Nueva Formosa", 1.25, "CAPS-SUR-04"),
    NeighborhoodProfile(88, "Guadalupe", 0.85, "CAPS-CODDA-01"),
    NeighborhoodProfile(91, "Lote 110", 1.65, "CAPS-SUR-04"),
    NeighborhoodProfile(92, "Lote 111", 1.70, "CAPS-SUR-04"),
    NeighborhoodProfile(97, "Villa del Rosario", 1.20, "CAPS-SUR-04"),
    NeighborhoodProfile(100, "San Isidro Labrador", 1.30, "CAPS-SUR-04"),
    NeighborhoodProfile(104, "El Mistol", 1.40, "CAPS-SUR-04"),
)

_AGE_GROUPS = np.array(["infante", "adolescente", "adulto", "mayor"])
_AGE_PROBS = np.array([0.22, 0.14, 0.48, 0.16])

_NON_FEBRILE_DIAGNOSES = np.array(
    [
        "gastroenteritis",
        "traumatismo_herida",
        "hipertension_cardiovascular",
        "otro_clinico",
    ]
)
_NON_FEBRILE_PROBS = np.array([0.24, 0.22, 0.20, 0.34])

_SYMPTOMS_BY_DIAGNOSIS: dict[str, str] = {
    "sospecha_dengue": "fiebre_mialgias_cefalea_retroocular",
    "sindrome_febril_inespecifico": "fiebre_decaimiento_escalofrios",
    "ira": "fiebre_tos_odinofagia",
    "gastroenteritis": "dolor_abdominal_diarrea",
    "traumatismo_herida": "traumatismo_herida_superficial",
    "hipertension_cardiovascular": "cefalea_hipertension_arterial",
    "otro_clinico": "control_clinico_general",
}


def _sigmoid(x: np.ndarray | float) -> np.ndarray | float:
    return 1.0 / (1.0 + np.exp(-x))


def _generate_formosa_weather(
    start_date: str,
    n_days: int,
    rng: np.random.Generator,
) -> pd.DataFrame:
    """Genera serie meteorológica diaria realista para Formosa Capital (-26.18, -58.17)."""
    dates = pd.date_range(start=start_date, periods=n_days, freq="D")
    day_of_year = dates.dayofyear.to_numpy(dtype=np.float64)

    # Estacionalidad térmica del hemisferio sur: pico fines de enero (doy ~ 25)
    seasonal_phase = np.cos(2.0 * np.pi * (day_of_year - 25.0) / 365.25)

    # Anomalía sinóptica persistente AR(1) de frentes cálidos/fríos
    temp_anom = np.zeros(n_days, dtype=np.float64)
    for t in range(1, n_days):
        temp_anom[t] = 0.72 * temp_anom[t - 1] + rng.normal(0.0, 2.1)

    temp_mean = 23.2 + 6.6 * seasonal_phase + temp_anom
    diurnal_range = np.clip(rng.normal(10.8, 2.2, size=n_days), 5.5, 16.5)
    temp_max = np.clip(temp_mean + diurnal_range / 2.0, 12.0, 43.0)
    temp_min = np.clip(temp_mean - diurnal_range / 2.0, 3.0, 31.0)

    # Eventos convectivos y frentes húmedos más frecuentes en primavera/verano
    storm_prob = np.clip(0.11 + 0.14 * (seasonal_phase + 1.0) / 2.0, 0.05, 0.30)
    is_storm = rng.random(n_days) < storm_prob
    # Frentes lluviosos de 2 días consecutivos frecuentes en el NEA
    second_day_storm = np.roll(is_storm, 1) & (rng.random(n_days) < 0.35)
    second_day_storm[0] = False
    rain_event = is_storm | second_day_storm

    precip = np.zeros(n_days, dtype=np.float64)
    storm_intensities = rng.gamma(shape=1.6, scale=18.5 + 10.0 * np.maximum(seasonal_phase, 0.0))
    precip[rain_event] = np.clip(storm_intensities[rain_event], 2.5, 145.0)
    # Lloviznas leves ocasionales
    drizzle = (~rain_event) & (rng.random(n_days) < 0.08)
    precip[drizzle] = rng.uniform(0.5, 4.0, size=int(drizzle.sum()))

    # Humedad relativa subtropical de Formosa (60-92%, sube con precipitaciones)
    hum_base = 69.0 + 7.5 * seasonal_phase + 0.22 * np.minimum(precip, 60.0)
    hum_noise = rng.normal(0.0, 6.0, size=n_days)
    humidity = np.clip(hum_base + hum_noise, 38.0, 98.0)

    return pd.DataFrame(
        {
            "recorded_date": dates.strftime("%Y-%m-%d"),
            "latitude": -26.1800,
            "longitude": -58.1700,
            "temperature_max": np.round(temp_max, 2),
            "temperature_min": np.round(temp_min, 2),
            "precipitation_sum": np.round(precip, 2),
            "relative_humidity_mean": np.round(humidity, 2),
            "source": "synthetic-formosa",
        }
    )


def generate_synthetic_formosa_dataset(
    n_weeks: int = 104,
    n_neighborhoods: int = 24,
    start_date: str = "2024-11-25",
    seed: int = 42,
) -> tuple[pd.DataFrame, pd.DataFrame]:
    """Genera telemetría anonimizada y registros climáticos sintéticos para Formosa Capital.

    Parameters
    ----------
    n_weeks:
        Cantidad de semanas epidemiológicas completas a simular (por defecto 104 = 2 años).
    n_neighborhoods:
        Cantidad de barrios del catálogo oficial de Formosa Capital a simular (1..30).
    start_date:
        Fecha inicial (lunes ISO para alinear exactamente semanas epidemiológicas).
    seed:
        Semilla determinística para reproducibilidad absoluta.

    Returns
    -------
    tuple[pd.DataFrame, pd.DataFrame]
        `(telemetry_df, weather_df)` conformes con `docs/specs/00_contracts.md` §§2 y 4.1.
    """
    if n_weeks < 4:
        raise ValueError("n_weeks debe ser al menos 4 semanas.")
    if not (1 <= n_neighborhoods <= len(FORMOSA_NEIGHBORHOODS)):
        raise ValueError(
            f"n_neighborhoods debe estar entre 1 y {len(FORMOSA_NEIGHBORHOODS)}."
        )

    rng = np.random.default_rng(seed)
    n_days = n_weeks * 7
    weather_df = _generate_formosa_weather(start_date=start_date, n_days=n_days, rng=rng)

    dates = pd.to_datetime(weather_df["recorded_date"])
    precip = weather_df["precipitation_sum"].to_numpy(dtype=np.float64)
    temp_mean = (
        weather_df["temperature_max"].to_numpy(dtype=np.float64)
        + weather_df["temperature_min"].to_numpy(dtype=np.float64)
    ) / 2.0
    humidity = weather_df["relative_humidity_mean"].to_numpy(dtype=np.float64)
    day_of_year = dates.dt.dayofyear.to_numpy(dtype=np.float64)

    # Cálculo vectorizado de señales ambientales con retardo biológico de 7 a 14 días
    rain_window_7_14 = np.zeros(n_days, dtype=np.float64)
    temp_lag_7_10 = np.full(n_days, float(temp_mean[0]), dtype=np.float64)
    hum_lag_7_10 = np.full(n_days, float(humidity[0]), dtype=np.float64)

    for t in range(n_days):
        r_start = max(0, t - 14)
        r_end = max(1, t - 6)
        rain_window_7_14[t] = precip[r_start:r_end].sum() if t >= 7 else 0.0

        t_start = max(0, t - 10)
        t_end = max(1, t - 6)
        temp_lag_7_10[t] = temp_mean[t_start:t_end].mean() if t >= 7 else temp_mean[t]
        hum_lag_7_10[t] = humidity[t_start:t_end].mean() if t >= 7 else humidity[t]

    # Aptitud biológica del vector Aedes aegypti (lluvia >= ~40mm con lag 7-14d + calor >= 21°C)
    # Escala continua desde 0 en sequía hasta saturación tras tormentas intensas de verano
    rain_base = np.clip(rain_window_7_14 / 65.0, 0.0, 1.0)
    rain_surge = _sigmoid((rain_window_7_14 - 38.0) / 10.0) * (rain_window_7_14 > 5.0)
    rain_signal = 0.35 * rain_base + 0.65 * rain_surge
    temp_signal = np.clip((temp_lag_7_10 - 18.0) / 10.5, 0.0, 1.15)
    hum_signal = np.clip((hum_lag_7_10 - 50.0) / 32.0, 0.20, 1.15)
    vector_suitability = rain_signal * temp_signal * hum_signal

    # Estacionalidad invernal moderada para IRA (pico junio-julio)
    winter_factor = np.clip(0.5 * (1.0 - np.cos(2.0 * np.pi * (day_of_year - 25.0) / 365.25)), 0.0, 1.0)

    selected_neighborhoods = FORMOSA_NEIGHBORHOODS[:n_neighborhoods]
    records: list[dict[str, object]] = []
    event_counter = 0

    for nb in selected_neighborhoods:
        # Inercia epidemiológica por barrio (transmisión local desde la semana previa)
        daily_febrile_history = np.zeros(n_days, dtype=np.float64)

        for t in range(n_days):
            prev_7d_febrile = (
                daily_febrile_history[max(0, t - 11) : max(0, t - 4)].sum() if t >= 5 else 0.0
            )
            momentum = np.clip(prev_7d_febrile / 10.0, 0.0, 1.0)

            # Régimen epidemiológico realista:
            # - Seco/frío (vector_suitability ~ 0): ~0.35 febriles/semana (riesgo bajo)
            # - Transición cálida / lluvia moderada: ~2.5-3.8 febriles/semana (riesgo moderado)
            # - Post-tormenta cálida (>= 40 mm lag 7-14d) + circulación: ~7-13 febriles/semana (brote alto)
            outbreak_boost = (vector_suitability[t] ** 1.35) * (0.95 + 0.70 * momentum)
            moderate_warm_activity = 0.20 * temp_signal[t] * (0.35 + 0.65 * rain_base[t])

            lambda_dengue = (
                0.002 * temp_signal[t]
                + nb.vulnerability * (0.72 * outbreak_boost + 0.14 * moderate_warm_activity)
            )
            lambda_sfi = (
                0.018
                + nb.vulnerability * (0.36 * outbreak_boost + 0.18 * moderate_warm_activity)
            )
            lambda_ira = 0.015 + 0.035 * winter_factor[t] * (0.8 + 0.2 * nb.vulnerability)
            # Demanda basal no febril de guardia CAPS
            lambda_non_febrile = 0.35 + 0.18 * nb.vulnerability

            n_dengue = int(rng.poisson(lambda_dengue))
            n_sfi = int(rng.poisson(lambda_sfi))
            n_ira = int(rng.poisson(lambda_ira))
            n_non_feb = int(rng.poisson(lambda_non_febrile))

            daily_febrile_history[t] = float(n_dengue + n_sfi + n_ira)
            date_str = dates[t].strftime("%Y-%m-%d")

            diagnosis_batch: list[str] = (
                ["sospecha_dengue"] * n_dengue
                + ["sindrome_febril_inespecifico"] * n_sfi
                + ["ira"] * n_ira
            )
            if n_non_feb > 0:
                diagnosis_batch.extend(
                    rng.choice(_NON_FEBRILE_DIAGNOSES, size=n_non_feb, p=_NON_FEBRILE_PROBS).tolist()
                )

            if not diagnosis_batch:
                continue

            batch_size = len(diagnosis_batch)
            age_batch = rng.choice(_AGE_GROUPS, size=batch_size, p=_AGE_PROBS)
            hours = rng.integers(7, 23, size=batch_size)
            minutes = rng.integers(0, 60, size=batch_size)

            for idx, diag in enumerate(diagnosis_batch):
                event_counter += 1
                event_uuid = str(uuid.uuid5(uuid.NAMESPACE_DNS, f"centinela-synth-{seed}-{event_counter}"))

                if diag == "sospecha_dengue":
                    priority = str(rng.choice(["orange", "yellow", "green"], p=[0.22, 0.58, 0.20]))
                    temp_c = float(np.round(np.clip(rng.normal(39.1, 0.55), 37.8, 41.2), 1))
                elif diag == "sindrome_febril_inespecifico":
                    priority = str(rng.choice(["orange", "yellow", "green"], p=[0.12, 0.52, 0.36]))
                    temp_c = float(np.round(np.clip(rng.normal(38.5, 0.50), 37.5, 40.5), 1))
                elif diag == "ira":
                    priority = str(rng.choice(["red", "orange", "yellow", "green"], p=[0.04, 0.16, 0.42, 0.38]))
                    temp_c = float(np.round(np.clip(rng.normal(38.2, 0.55), 37.0, 40.2), 1))
                else:
                    priority = str(rng.choice(["orange", "yellow", "green", "blue"], p=[0.05, 0.25, 0.48, 0.22]))
                    temp_c = float(np.round(np.clip(rng.normal(36.7, 0.35), 35.8, 37.6), 1))

                records.append(
                    {
                        "event_uuid": event_uuid,
                        "caps_code": nb.caps_code,
                        "neighborhood_id": nb.neighborhood_id,
                        "neighborhood_name": nb.name,
                        "age_group": str(age_batch[idx]),
                        "triage_priority": priority,
                        "presumptive_diagnosis": diag,
                        "symptoms_summary": _SYMPTOMS_BY_DIAGNOSIS[diag],
                        "temperature_c": temp_c,
                        "recorded_at": f"{date_str}T{int(hours[idx]):02d}:{int(minutes[idx]):02d}:00-03:00",
                    }
                )

    telemetry_df = pd.DataFrame(records)
    return telemetry_df, weather_df
