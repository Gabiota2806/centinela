"""Configuración de pruebas pytest para backend_analytics (CEN-57)."""

from __future__ import annotations

import pytest


def pytest_configure(config: pytest.Config) -> None:
    """Silencia advertencia de terceros de Starlette 1.10 sobre httpx en TestClient."""
    config.addinivalue_line(
        "filterwarnings",
        "ignore:Using `httpx` with `starlette\\.testclient` is deprecated:Warning",
    )
