"""Router de ingesta de telemetría epidemiológica anonimizada (POST /api/v1/telemetry)."""

from __future__ import annotations

from fastapi import APIRouter, Depends, status

from backend_analytics.database import TelemetryRepository, get_telemetry_repository
from backend_analytics.schemas.telemetry import TelemetryAckOut, TelemetryEventIn
from backend_analytics.security import verify_caps_key

router = APIRouter(prefix="/api/v1", tags=["telemetry"])


@router.post(
    "/telemetry",
    response_model=TelemetryAckOut,
    status_code=status.HTTP_202_ACCEPTED,
    dependencies=[Depends(verify_caps_key)],
    summary="Ingesta idempotente de telemetría clínica anonimizada (Ley N° 25.326)",
)
def ingest_telemetry(
    payload: TelemetryEventIn,
    repository: TelemetryRepository = Depends(get_telemetry_repository),
) -> TelemetryAckOut:
    """Valida el esquema estricto sin PII y persiste el evento en centinela_hub."""
    repository.save_event(payload)

    return TelemetryAckOut(
        status="accepted",
        event_uuid=str(payload.event_uuid),
    )
