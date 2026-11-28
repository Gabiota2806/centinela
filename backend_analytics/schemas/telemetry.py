"""Esquemas Pydantic v2 estrictos para ingesta de telemetría disociada (Ley N° 25.326)."""

from __future__ import annotations

from datetime import datetime
from typing import Literal
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field

AgeGroup = Literal["infante", "adolescente", "adulto", "mayor"]
TriagePriority = Literal["red", "orange", "yellow", "green", "blue"]
PresumptiveDiagnosis = Literal[
    "sindrome_febril_inespecifico",
    "sospecha_dengue",
    "ira",
    "gastroenteritis",
    "traumatismo_herida",
    "hipertension_cardiovascular",
    "otro_clinico",
]


class TelemetryEventIn(BaseModel):
    """Payload estrictamente disociado conforme a docs/specs/00_contracts.md §4.1.

    El modo `extra="forbid"` garantiza el rechazo inmediato (HTTP 422) ante
    cualquier clave identificatoria (dni, first_name, last_name, full_name,
    phone, address, birth_date) o campo fuera del contrato congelado.
    """

    model_config = ConfigDict(extra="forbid", str_strip_whitespace=True)

    event_uuid: UUID
    caps_code: str = Field(..., min_length=2, max_length=64)
    neighborhood_id: int = Field(..., gt=0)
    neighborhood_name: str = Field(..., min_length=1, max_length=120)
    age_group: AgeGroup
    triage_priority: TriagePriority
    presumptive_diagnosis: PresumptiveDiagnosis
    symptoms_summary: str = Field(..., min_length=1, max_length=255)
    temperature_c: float | None = Field(default=None, ge=34.0, le=43.0)
    recorded_at: datetime


class TelemetryAckOut(BaseModel):
    """Respuesta canónica de aceptación idempotente de evento de telemetría."""

    status: Literal["accepted"] = "accepted"
    event_uuid: str
