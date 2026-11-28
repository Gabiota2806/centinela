"""Capa de persistencia aislada en la base centinela_hub (esquema hub, rol hub_user)."""

from __future__ import annotations

import re
from threading import Lock
from typing import Any, Protocol

import psycopg
from fastapi import Request
from psycopg import sql

from backend_analytics.schemas.telemetry import TelemetryEventIn

_SAFE_SCHEMA_RE = re.compile(r"^[a-zA-Z_][a-zA-Z0-9_]*$")


class TelemetryRepository(Protocol):
    """Contrato de persistencia idempotente para eventos de telemetría anonimizada."""

    def init_schema(self) -> None: ...

    def save_event(self, event: TelemetryEventIn) -> bool: ...

    def count_events(self) -> int: ...

    def get_by_uuid(self, event_uuid: str) -> dict[str, Any] | None: ...


class PostgresTelemetryRepository:
    """Repositorio PostgreSQL exclusivo para la base aislada centinela_hub."""

    def __init__(self, database_url: str, schema: str = "hub") -> None:
        if not _SAFE_SCHEMA_RE.match(schema):
            raise ValueError(f"Nombre de esquema PostgreSQL inválido: {schema!r}")
        self._database_url = database_url
        self._schema = schema

    def _connect(self) -> psycopg.Connection[Any]:
        return psycopg.connect(self._database_url, autocommit=True)

    def init_schema(self) -> None:
        schema_ident = sql.Identifier(self._schema)
        table_ident = sql.Identifier(self._schema, "telemetry_events")
        idx_neighborhood = sql.Identifier("idx_hub_telemetry_neighborhood_recorded")
        idx_diagnosis = sql.Identifier("idx_hub_telemetry_diagnosis_recorded")

        with self._connect() as conn, conn.cursor() as cur:
            cur.execute(sql.SQL("CREATE SCHEMA IF NOT EXISTS {}").format(schema_ident))
            cur.execute(
                sql.SQL(
                    """
                    CREATE TABLE IF NOT EXISTS {} (
                        event_uuid UUID PRIMARY KEY,
                        caps_code VARCHAR(64) NOT NULL,
                        neighborhood_id INTEGER NOT NULL,
                        neighborhood_name VARCHAR(120) NOT NULL,
                        age_group VARCHAR(20) NOT NULL,
                        triage_priority VARCHAR(16) NOT NULL,
                        presumptive_diagnosis VARCHAR(64) NOT NULL,
                        symptoms_summary VARCHAR(255) NOT NULL,
                        temperature_c NUMERIC(4, 1) NULL,
                        recorded_at TIMESTAMPTZ NOT NULL,
                        received_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                    )
                    """
                ).format(table_ident)
            )
            cur.execute(
                sql.SQL(
                    "CREATE INDEX IF NOT EXISTS {} ON {} (neighborhood_id, recorded_at)"
                ).format(idx_neighborhood, table_ident)
            )
            cur.execute(
                sql.SQL(
                    "CREATE INDEX IF NOT EXISTS {} ON {} (presumptive_diagnosis, recorded_at)"
                ).format(idx_diagnosis, table_ident)
            )

    def save_event(self, event: TelemetryEventIn) -> bool:
        table_ident = sql.Identifier(self._schema, "telemetry_events")
        query = sql.SQL(
            """
            INSERT INTO {} (
                event_uuid,
                caps_code,
                neighborhood_id,
                neighborhood_name,
                age_group,
                triage_priority,
                presumptive_diagnosis,
                symptoms_summary,
                temperature_c,
                recorded_at
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            ON CONFLICT (event_uuid) DO NOTHING
            """
        ).format(table_ident)

        params = (
            event.event_uuid,
            event.caps_code,
            event.neighborhood_id,
            event.neighborhood_name,
            event.age_group,
            event.triage_priority,
            event.presumptive_diagnosis,
            event.symptoms_summary,
            event.temperature_c,
            event.recorded_at,
        )

        with self._connect() as conn, conn.cursor() as cur:
            cur.execute(query, params)
            return cur.rowcount == 1

    def count_events(self) -> int:
        table_ident = sql.Identifier(self._schema, "telemetry_events")
        with self._connect() as conn, conn.cursor() as cur:
            cur.execute(sql.SQL("SELECT COUNT(*) FROM {}").format(table_ident))
            row = cur.fetchone()
            return int(row[0]) if row else 0

    def get_by_uuid(self, event_uuid: str) -> dict[str, Any] | None:
        table_ident = sql.Identifier(self._schema, "telemetry_events")
        query = sql.SQL(
            """
            SELECT
                event_uuid::text,
                caps_code,
                neighborhood_id,
                neighborhood_name,
                age_group,
                triage_priority,
                presumptive_diagnosis,
                symptoms_summary,
                temperature_c,
                recorded_at
            FROM {}
            WHERE event_uuid = %s::uuid
            """
        ).format(table_ident)

        with self._connect() as conn, conn.cursor() as cur:
            cur.execute(query, (event_uuid,))
            row = cur.fetchone()
            if row is None:
                return None
            return {
                "event_uuid": row[0],
                "caps_code": row[1],
                "neighborhood_id": row[2],
                "neighborhood_name": row[3],
                "age_group": row[4],
                "triage_priority": row[5],
                "presumptive_diagnosis": row[6],
                "symptoms_summary": row[7],
                "temperature_c": float(row[8]) if row[8] is not None else None,
                "recorded_at": row[9],
            }


class InMemoryTelemetryRepository:
    """Repositorio idempotente en memoria para pruebas unitarias y de contrato."""

    def __init__(self) -> None:
        self._lock = Lock()
        self._events: dict[str, dict[str, Any]] = {}

    def init_schema(self) -> None:
        return None

    def save_event(self, event: TelemetryEventIn) -> bool:
        key = str(event.event_uuid)
        with self._lock:
            if key in self._events:
                return False
            self._events[key] = event.model_dump(mode="json")
            return True

    def count_events(self) -> int:
        with self._lock:
            return len(self._events)

    def get_by_uuid(self, event_uuid: str) -> dict[str, Any] | None:
        with self._lock:
            record = self._events.get(event_uuid)
            return dict(record) if record is not None else None


def get_telemetry_repository(request: Request) -> TelemetryRepository:
    """Dependencia FastAPI que expone el repositorio activo del Hub."""
    return request.app.state.telemetry_repository
