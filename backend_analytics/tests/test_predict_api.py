"""Pruebas de contrato, seguridad y latencia para inferencia FastAPI (CEN-57 / RF-GEO-08)."""

from __future__ import annotations

import time
from unittest.mock import patch

import pytest
from fastapi.testclient import TestClient

from backend_analytics.ml.train_random_forest import DEFAULT_MODEL_PATH, load_model_artifact
from backend_analytics.routers.predict import (
    RiskInferenceService,
    StaticFeatureProvider,
    create_predict_app,
)

TEST_CAPS_KEY = "test-caps-secret-key-2026"
AUTH_HEADERS = {"X-CAPS-Key": TEST_CAPS_KEY}


def _sample_feature_row(
    *,
    rain_7d: float,
    febrile_7d: float,
    dengue_7d: float,
    suitability: float,
) -> dict[str, float]:
    return {
        "rain_lag_7d": rain_7d,
        "rain_lag_14d": rain_7d * 0.8,
        "rain_accum_7_14d": rain_7d * 1.8,
        "rain_max_daily_lag_7d": rain_7d * 0.6,
        "rainy_days_lag_7d": 4.0 if rain_7d >= 40.0 else 1.0,
        "rain_above_threshold_lag_7d": 1.0 if rain_7d >= 40.0 else 0.0,
        "temp_mean_lag_7d": 30.5 if rain_7d >= 40.0 else 18.0,
        "temp_mean_lag_14d": 29.8 if rain_7d >= 40.0 else 17.5,
        "humidity_mean_lag_7d": 84.0 if rain_7d >= 40.0 else 52.0,
        "vector_suitability_lag_7_14d": suitability,
        "febrile_cases_lag_7d": febrile_7d,
        "febrile_cases_lag_14d": max(0.0, febrile_7d - 2.0),
        "febrile_rolling_4w_lag_7d": febrile_7d * 0.9,
        "dengue_cases_lag_7d": dengue_7d,
        "febrile_ratio_lag_7d": 0.68 if febrile_7d >= 6.0 else 0.08,
        "febrile_growth_rate": 0.60 if febrile_7d >= 6.0 else 0.0,
        "epi_week_sin": 0.50,
        "epi_week_cos": 0.86,
    }


@pytest.fixture()
def static_feature_provider() -> StaticFeatureProvider:
    rows = {
        1: _sample_feature_row(rain_7d=4.0, febrile_7d=0.0, dengue_7d=0.0, suitability=0.05),
        14: _sample_feature_row(rain_7d=95.0, febrile_7d=11.0, dengue_7d=6.0, suitability=0.92),
        44: _sample_feature_row(rain_7d=52.0, febrile_7d=3.0, dengue_7d=1.0, suitability=0.52),
    }
    return StaticFeatureProvider(rows)


@pytest.fixture()
def client(static_feature_provider: StaticFeatureProvider) -> TestClient:
    service = RiskInferenceService(
        model_path=DEFAULT_MODEL_PATH,
        feature_provider=static_feature_provider,
    )
    app = create_predict_app(
        caps_api_key=TEST_CAPS_KEY,
        inference_service=service,
    )
    with TestClient(app) as test_client:
        yield test_client


def test_individual_risk_rejects_missing_or_invalid_caps_key(client: TestClient) -> None:
    response_missing = client.get("/api/v1/neighborhoods/14/risk")
    assert response_missing.status_code == 401

    response_invalid = client.get(
        "/api/v1/neighborhoods/14/risk",
        headers={"X-CAPS-Key": "clave-incorrecta"},
    )
    assert response_invalid.status_code == 401


def test_batch_risks_rejects_missing_or_invalid_caps_key(client: TestClient) -> None:
    response_missing = client.get("/api/v1/neighborhoods/risks")
    assert response_missing.status_code == 401

    response_invalid = client.get(
        "/api/v1/neighborhoods/risks",
        headers={"X-CAPS-Key": "clave-incorrecta"},
    )
    assert response_invalid.status_code == 401


def test_individual_risk_returns_contract_compliant_payload(client: TestClient) -> None:
    response = client.get("/api/v1/neighborhoods/14/risk", headers=AUTH_HEADERS)

    assert response.status_code == 200
    payload = response.json()

    assert set(payload.keys()) == {
        "neighborhood_id",
        "risk_class",
        "risk_level",
        "risk_score",
        "probabilities",
        "model_version",
        "predicted_at",
    }
    assert payload["neighborhood_id"] == 14
    assert payload["risk_class"] in {0, 1, 2}
    assert payload["risk_level"] in {"low", "moderate", "high"}
    assert 0.0 <= payload["risk_score"] <= 1.0
    assert payload["model_version"] == "rf-v1.0"
    assert payload["predicted_at"].endswith("-03:00")

    probs = payload["probabilities"]
    assert set(probs.keys()) == {"low", "moderate", "high"}
    assert pytest.approx(sum(probs.values()), abs=0.001) == 1.0
    assert payload["risk_score"] == pytest.approx(probs["high"], abs=0.0001)


def test_high_risk_and_low_risk_profiles_are_distinguished(client: TestClient) -> None:
    low_resp = client.get("/api/v1/neighborhoods/1/risk", headers=AUTH_HEADERS)
    high_resp = client.get("/api/v1/neighborhoods/14/risk", headers=AUTH_HEADERS)

    assert low_resp.status_code == 200
    assert high_resp.status_code == 200

    low_data = low_resp.json()
    high_data = high_resp.json()

    assert low_data["risk_level"] == "low"
    assert high_data["risk_level"] == "high"
    assert high_data["risk_score"] > low_data["risk_score"]


def test_batch_risks_returns_all_neighborhoods_and_supports_filter(client: TestClient) -> None:
    response = client.get("/api/v1/neighborhoods/risks", headers=AUTH_HEADERS)

    assert response.status_code == 200
    payload = response.json()

    assert set(payload.keys()) == {"items", "model_version", "predicted_at"}
    assert payload["model_version"] == "rf-v1.0"
    assert payload["predicted_at"].endswith("-03:00")
    assert [item["neighborhood_id"] for item in payload["items"]] == [1, 14, 44]

    filtered = client.get(
        "/api/v1/neighborhoods/risks",
        params={"neighborhood_ids": "14,1"},
        headers=AUTH_HEADERS,
    )
    assert filtered.status_code == 200
    assert [item["neighborhood_id"] for item in filtered.json()["items"]] == [1, 14]


def test_individual_risk_returns_404_for_unknown_neighborhood_and_422_for_invalid_params(
    client: TestClient,
) -> None:
    not_found = client.get("/api/v1/neighborhoods/9999/risk", headers=AUTH_HEADERS)
    assert not_found.status_code == 404

    invalid_id = client.get("/api/v1/neighborhoods/0/risk", headers=AUTH_HEADERS)
    assert invalid_id.status_code == 422

    invalid_threshold = client.get(
        "/api/v1/neighborhoods/14/risk",
        params={"high_risk_threshold": 1.5},
        headers=AUTH_HEADERS,
    )
    assert invalid_threshold.status_code == 422


def test_high_risk_threshold_query_parameter_affects_classification(client: TestClient) -> None:
    strict = client.get(
        "/api/v1/neighborhoods/14/risk",
        params={"high_risk_threshold": 0.99},
        headers=AUTH_HEADERS,
    )
    sensitive = client.get(
        "/api/v1/neighborhoods/14/risk",
        params={"high_risk_threshold": 0.20},
        headers=AUTH_HEADERS,
    )

    assert strict.status_code == 200
    assert sensitive.status_code == 200
    assert sensitive.json()["risk_level"] == "high"
    assert strict.json()["risk_class"] <= sensitive.json()["risk_class"]


def test_model_is_loaded_once_in_memory_and_responds_under_200ms(
    static_feature_provider: StaticFeatureProvider,
) -> None:
    real_artifact = load_model_artifact(DEFAULT_MODEL_PATH)

    with patch(
        "backend_analytics.routers.predict.load_model_artifact",
        return_value=real_artifact,
    ) as mocked_loader:
        service = RiskInferenceService(
            model_path=DEFAULT_MODEL_PATH,
            feature_provider=static_feature_provider,
        )
        app = create_predict_app(caps_api_key=TEST_CAPS_KEY, inference_service=service)

        with TestClient(app) as test_client:
            # Warmup inicial del TestClient y verificacion de latencia real en memoria
            warmup = test_client.get("/api/v1/neighborhoods/14/risk", headers=AUTH_HEADERS)
            assert warmup.status_code == 200

            start = time.perf_counter()
            for _ in range(5):
                resp = test_client.get("/api/v1/neighborhoods/14/risk", headers=AUTH_HEADERS)
                assert resp.status_code == 200
            elapsed_ms = ((time.perf_counter() - start) / 5.0) * 1000.0

            batch_start = time.perf_counter()
            batch_resp = test_client.get("/api/v1/neighborhoods/risks", headers=AUTH_HEADERS)
            batch_ms = (time.perf_counter() - batch_start) * 1000.0

            assert batch_resp.status_code == 200
            assert mocked_loader.call_count == 1
            assert elapsed_ms < 200.0, f"Latencia individual excesiva: {elapsed_ms:.2f} ms"
            assert batch_ms < 200.0, f"Latencia batch excesiva: {batch_ms:.2f} ms"


def test_default_baseline_feature_provider_serves_formosa_neighborhoods_under_200ms() -> None:
    app = create_predict_app(caps_api_key=TEST_CAPS_KEY)

    with TestClient(app) as test_client:
        # Precarga y primera llamada batch sobre los 113 barrios oficiales de Formosa Capital
        batch_start = time.perf_counter()
        first = test_client.get("/api/v1/neighborhoods/risks", headers=AUTH_HEADERS)
        batch_ms = (time.perf_counter() - batch_start) * 1000.0
        assert first.status_code == 200
        items = first.json()["items"]
        assert len(items) == 113
        assert batch_ms < 200.0, f"Latencia batch 113 barrios excesiva: {batch_ms:.2f} ms"

        # Verificar estratificación realista en las 3 clases de riesgo
        levels = {item["risk_level"] for item in items}
        assert levels == {"low", "moderate", "high"}

        start = time.perf_counter()
        single = test_client.get("/api/v1/neighborhoods/14/risk", headers=AUTH_HEADERS)
        elapsed_ms = (time.perf_counter() - start) * 1000.0

        assert single.status_code == 200
        assert single.json()["neighborhood_id"] == 14
        assert elapsed_ms < 200.0, f"Latencia con proveedor base excesiva: {elapsed_ms:.2f} ms"
