"""Configuración de entorno del Hub Analítico Central de Centinela (CEN-52)."""

from __future__ import annotations

import os
from dataclasses import dataclass
from functools import lru_cache


@dataclass(frozen=True)
class HubSettings:
    """Parámetros de conexión y seguridad del microservicio FastAPI."""

    caps_api_key: str
    database_url: str
    db_schema: str = "hub"
    auto_init_db: bool = True

    @classmethod
    def from_env(cls) -> HubSettings:
        auto_init_raw = os.getenv("HUB_AUTO_INIT_DB", "true").strip().lower()
        return cls(
            caps_api_key=os.getenv("CENTINELA_CAPS_API_KEY", "").strip(),
            database_url=os.getenv(
                "HUB_DATABASE_URL",
                "postgresql://hub_user:hub_secret@postgres:5432/centinela_hub",
            ).strip(),
            db_schema=os.getenv("HUB_DB_SCHEMA", "hub").strip() or "hub",
            auto_init_db=auto_init_raw not in {"0", "false", "no"},
        )


@lru_cache(maxsize=1)
def get_settings() -> HubSettings:
    """Retorna la configuración cargada desde variables de entorno."""
    return HubSettings.from_env()
