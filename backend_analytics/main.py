"""Aplicación principal FastAPI del Hub Analítico Central de Centinela (CEN-52)."""

from __future__ import annotations

import logging
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager

from fastapi import FastAPI

from backend_analytics.config import HubSettings, get_settings
from backend_analytics.database import PostgresTelemetryRepository, TelemetryRepository
from backend_analytics.routers.telemetry import router as telemetry_router

logger = logging.getLogger(__name__)


def create_app(
    settings: HubSettings | None = None,
    repository: TelemetryRepository | None = None,
) -> FastAPI:
    """Construye la aplicación FastAPI con soporte de inyección para pruebas."""
    resolved_settings = settings or get_settings()
    resolved_repo = repository or PostgresTelemetryRepository(
        database_url=resolved_settings.database_url,
        schema=resolved_settings.db_schema,
    )

    @asynccontextmanager
    async def lifespan(application: FastAPI) -> AsyncIterator[None]:
        application.state.settings = resolved_settings
        application.state.telemetry_repository = resolved_repo

        if resolved_settings.auto_init_db:
            try:
                resolved_repo.init_schema()
            except Exception as exc:  # pragma: no cover - contingencia arranque sin BD lista
                logger.warning("No se pudo inicializar el esquema hub en el arranque: %s", exc)

        yield

    application = FastAPI(
        title="Centinela Hub Analytics",
        description="Microservicio analítico aislado para telemetría epidemiológica anonimizada (Ley N° 25.326).",
        version="1.0.0",
        lifespan=lifespan,
    )

    application.state.settings = resolved_settings
    application.state.telemetry_repository = resolved_repo

    @application.get("/health", tags=["health"])
    def healthcheck() -> dict[str, str]:
        return {"status": "ok", "service": "centinela-hub-analytics"}

    application.include_router(telemetry_router)

    return application


app = create_app()
