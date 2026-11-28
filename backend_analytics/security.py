"""Autenticación de nodos emisores CAPS mediante cabecera X-CAPS-Key (CEN-52)."""

from __future__ import annotations

import secrets

from fastapi import Depends, Header, HTTPException, status

from backend_analytics.config import HubSettings, get_settings


def verify_caps_key(
    x_caps_key: str | None = Header(default=None, alias="X-CAPS-Key"),
    settings: HubSettings = Depends(get_settings),
) -> str:
    """Valida en tiempo constante la API Key del nodo CAPS emisor."""
    expected = settings.caps_api_key
    if not expected or not x_caps_key or not secrets.compare_digest(x_caps_key, expected):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Cabecera X-CAPS-Key ausente o inválida.",
        )

    return x_caps_key
