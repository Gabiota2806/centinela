"""Pruebas de contrato, seguridad y privacidad (Ley N° 25.326) para POST /api/v1/telemetry (CEN-52)."""

from __future__ import annotations

import pytest
from fastapi.testclient import TestClient

from backend_analytics.config import HubSettings, get_settings
from backend_analytics.database import InMemoryTelemetryRepository, get_telemetry_repository
from backend_analytics.main import create_app


VALID_API_KEY = "test-caps-secret-key-2026"


def _valid_payload(event_uuid: str = "550e8400-e29b-41d4-a716-446655440000") -> dict:
    return {
        "event_uuid": event_uuid,
        "caps_code": "CAPS-CODDA-01",
        "neighborhood_id": 14,
        "neighborhood_name": "San Miguel",
        "age_group": "adulto",
        "triage_priority": "yellow",
        "presumptive_diagnosis": "sospecha_dengue",
        "symptoms_summary": "fiebre_mialgias_cefalea",
        "temperature_c": 39.2,
        "recorded_at": "2026-11-24T15:30:00-03:00",
    }


@pytest.fixture()
def client_and_repo() -> tuple[TestClient, InMemoryTelemetryRepository]:
    repo = InMemoryTelemetryRepository()
    settings = HubSettings(
        caps_api_key=VALID_API_KEY,
        database_url="postgresql://hub_user:hub_secret@postgres:5432/centinela_hub",
        db_schema="hub",
        auto_init_db=False,
    )
    app = create_app(settings=settings, repository=repo)
    app.dependency_overrides[get_settings] = lambda: settings
    app.dependency_overrides[get_telemetry_repository] = lambda: repo

    with TestClient(app) as client:
        yield client, repo


def test_health_endpoint_responde_ok(
    client_and_repo: tuple[TestClient, InMemoryTelemetryRepository],
) -> None:
    client, _ = client_and_repo
    response = client.get("/health")

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert body["service"] == "centinela-hub-analytics"


def test_rechaza_401_cuando_falta_cabecera_x_caps_key(
    client_and_repo: tuple[TestClient, InMemoryTelemetryRepository],
) -> None:
    client, repo = client_and_repo
    response = client.post("/api/v1/telemetry", json=_valid_payload())

    assert response.status_code == 401
    assert repo.count_events() == 0


def test_rechaza_401_cuando_x_caps_key_es_invalida(
    client_and_repo: tuple[TestClient, InMemoryTelemetryRepository],
) -> None:
    client, repo = client_and_repo
    response = client.post(
        "/api/v1/telemetry",
        json=_valid_payload(),
        headers={"X-CAPS-Key": "clave-incorrecta"},
    )

    assert response.status_code == 401
    assert repo.count_events() == 0


@pytest.mark.parametrize(
    "pii_field,pii_value",
    [
        ("dni", "38123456"),
        ("first_name", "Juan"),
        ("last_name", "Pérez"),
        ("full_name", "Juan Pérez"),
        ("phone", "+54 370 4998877"),
        ("address", "Av. 25 de Mayo 1450"),
        ("birth_date", "1990-05-10"),
        ("campo_desconocido", "valor_extra"),
    ],
)
def test_rechaza_422_si_payload_contiene_pii_o_campos_extra(
    client_and_repo: tuple[TestClient, InMemoryTelemetryRepository],
    pii_field: str,
    pii_value: str,
) -> None:
    client, repo = client_and_repo
    payload = _valid_payload()
    payload[pii_field] = pii_value

    response = client.post(
        "/api/v1/telemetry",
        json=payload,
        headers={"X-CAPS-Key": VALID_API_KEY},
    )

    assert response.status_code == 422
    assert repo.count_events() == 0


@pytest.mark.parametrize(
    "field,invalid_value",
    [
        ("age_group", "anciano"),
        ("triage_priority", "purple"),
        ("presumptive_diagnosis", "diagnostico_libre_no_catalogado"),
        ("neighborhood_id", 0),
        ("temperature_c", 49.5),
        ("event_uuid", "no-es-un-uuid"),
    ],
)
def test_rechaza_422_ante_valores_fuera_de_catalogo_o_rango(
    client_and_repo: tuple[TestClient, InMemoryTelemetryRepository],
    field: str,
    invalid_value: object,
) -> None:
    client, repo = client_and_repo
    payload = _valid_payload()
    payload[field] = invalid_value

    response = client.post(
        "/api/v1/telemetry",
        json=payload,
        headers={"X-CAPS-Key": VALID_API_KEY},
    )

    assert response.status_code == 422
    assert repo.count_events() == 0


def test_acepta_202_y_persiste_idempotentemente_por_event_uuid(
    client_and_repo: tuple[TestClient, InMemoryTelemetryRepository],
) -> None:
    client, repo = client_and_repo
    payload = _valid_payload()

    first = client.post(
        "/api/v1/telemetry",
        json=payload,
        headers={"X-CAPS-Key": VALID_API_KEY},
    )
    assert first.status_code == 202
    assert first.json() == {
        "status": "accepted",
        "event_uuid": payload["event_uuid"],
    }
    assert repo.count_events() == 1

    # Reenvío idempotente con el mismo event_uuid (ej. tras reintento de Outbox)
    second = client.post(
        "/api/v1/telemetry",
        json=payload,
        headers={"X-CAPS-Key": VALID_API_KEY},
    )
    assert second.status_code == 202
    assert second.json() == {
        "status": "accepted",
        "event_uuid": payload["event_uuid"],
    }
    assert repo.count_events() == 1


def test_acepta_temperatura_nula_cuando_no_fue_registrada(
    client_and_repo: tuple[TestClient, InMemoryTelemetryRepository],
) -> None:
    client, repo = client_and_repo
    payload = _valid_payload("550e8400-e29b-41d4-a716-446655440099")
    payload["temperature_c"] = None

    response = client.post(
        "/api/v1/telemetry",
        json=payload,
        headers={"X-CAPS-Key": VALID_API_KEY},
    )

    assert response.status_code == 202
    stored = repo.get_by_uuid(payload["event_uuid"])
    assert stored is not None
    assert stored["temperature_c"] is None
