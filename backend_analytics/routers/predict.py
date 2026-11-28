"""Endpoints FastAPI de inferencia analítica de riesgo barrial < 200 ms (CEN-57 / RF-GEO-08).

Expone:
- `GET /api/v1/neighborhoods/risks` (inferencia batch para sincronización eficiente)
- `GET /api/v1/neighborhoods/{neighborhood_id}/risk` (inferencia individual por barrio)

Mantiene el artefacto `risk_classifier.pkl` residente en memoria y valida la cabecera
`X-CAPS-Key` conforme a `docs/specs/00_contracts.md` §4.2 y Ley N° 25.326.
"""

from __future__ import annotations

import os
import secrets
from collections.abc import AsyncIterator, Mapping, Sequence
from contextlib import asynccontextmanager
from pathlib import Path
from threading import Lock
from typing import Any, Protocol

import pandas as pd
from fastapi import APIRouter, Depends, FastAPI, Header, HTTPException, Path as FastApiPath, Query, Request, status

from backend_analytics.ml.train_random_forest import (
    DEFAULT_MODEL_PATH,
    load_model_artifact,
    predict_batch_risks,
    predict_neighborhood_risk,
)
from backend_analytics.pipeline.dataset_builder import build_epiweek_dataset
from backend_analytics.pipeline.lag_features import FEATURE_COLUMNS, build_epiweek_lag_features
from backend_analytics.pipeline.synthetic_generator import (
    FORMOSA_NEIGHBORHOODS,
    generate_synthetic_formosa_dataset,
)
from backend_analytics.schemas.predict import BatchNeighborhoodRiskOut, NeighborhoodRiskOut


class FeatureProvider(Protocol):
    """Proveedor de matriz de features epidemiológico-climáticas rezagadas por barrio."""

    def get_neighborhood_features(self, neighborhood_id: int) -> dict[str, float] | None: ...

    def get_all_neighborhood_features(
        self,
        neighborhood_ids: Sequence[int] | None = None,
    ) -> pd.DataFrame: ...


class StaticFeatureProvider:
    """Proveedor inmutable en memoria a partir de un mapa {neighborhood_id: features}."""

    def __init__(self, rows_by_neighborhood: Mapping[int, Mapping[str, float]]) -> None:
        self._rows: dict[int, dict[str, float]] = {}
        records: list[dict[str, Any]] = []

        for nid in sorted(rows_by_neighborhood.keys()):
            int_id = int(nid)
            row = {col: float(rows_by_neighborhood[nid][col]) for col in FEATURE_COLUMNS}
            self._rows[int_id] = row
            records.append({"neighborhood_id": int_id, **row})

        if records:
            self._frame = pd.DataFrame(records)
        else:
            self._frame = pd.DataFrame(columns=["neighborhood_id", *FEATURE_COLUMNS])

    def get_neighborhood_features(self, neighborhood_id: int) -> dict[str, float] | None:
        row = self._rows.get(int(neighborhood_id))
        return dict(row) if row is not None else None

    def get_all_neighborhood_features(
        self,
        neighborhood_ids: Sequence[int] | None = None,
    ) -> pd.DataFrame:
        if neighborhood_ids is None:
            return self._frame.copy()

        requested = {int(nid) for nid in neighborhood_ids}
        return self._frame[self._frame["neighborhood_id"].isin(requested)].reset_index(drop=True)


class CachedBaselineFeatureProvider:
    """Genera y cachea en memoria el último corte semanal de features de Formosa Capital.

    Garantiza inferencia determinística y latencia < 200 ms incluso cuando el Hub aún
    no recibió suficiente histórico de telemetría real en producción o en modo demo offline.
    """

    def __init__(
        self,
        *,
        n_weeks: int = 12,
        n_neighborhoods: int = len(FORMOSA_NEIGHBORHOODS),
        rain_threshold_mm: float = 40.0,
        seed: int = 42,
    ) -> None:
        self._n_weeks = n_weeks
        self._n_neighborhoods = n_neighborhoods
        self._rain_threshold_mm = rain_threshold_mm
        self._seed = seed
        self._lock = Lock()
        self._static: StaticFeatureProvider | None = None

    def _ensure_loaded(self) -> StaticFeatureProvider:
        if self._static is not None:
            return self._static

        with self._lock:
            if self._static is None:
                telemetry_df, weather_df = generate_synthetic_formosa_dataset(
                    n_weeks=self._n_weeks,
                    n_neighborhoods=self._n_neighborhoods,
                    seed=self._seed,
                )
                epiweek_df = build_epiweek_dataset(telemetry_df, weather_df)
                lag_df = build_epiweek_lag_features(
                    epiweek_df,
                    rain_threshold_mm=self._rain_threshold_mm,
                    drop_incomplete=True,
                )
                latest_per_neighborhood = (
                    lag_df.sort_values(["neighborhood_id", "week_start_date"])
                    .groupby("neighborhood_id", as_index=False)
                    .last()
                )
                rows: dict[int, dict[str, float]] = {}
                for record in latest_per_neighborhood.to_dict(orient="records"):
                    nid = int(record["neighborhood_id"])
                    rows[nid] = {col: float(record[col]) for col in FEATURE_COLUMNS}

                self._static = StaticFeatureProvider(rows)

        return self._static

    def warmup(self) -> None:
        self._ensure_loaded()

    def get_neighborhood_features(self, neighborhood_id: int) -> dict[str, float] | None:
        return self._ensure_loaded().get_neighborhood_features(neighborhood_id)

    def get_all_neighborhood_features(
        self,
        neighborhood_ids: Sequence[int] | None = None,
    ) -> pd.DataFrame:
        return self._ensure_loaded().get_all_neighborhood_features(neighborhood_ids)


class RiskInferenceService:
    """Servicio thread-safe de inferencia con el clasificador Random Forest residente en memoria."""

    def __init__(
        self,
        *,
        model_path: str | Path = DEFAULT_MODEL_PATH,
        feature_provider: FeatureProvider | None = None,
    ) -> None:
        self._model_path = Path(model_path)
        self._feature_provider: FeatureProvider = feature_provider or CachedBaselineFeatureProvider()
        self._artifact: dict[str, Any] | None = None
        self._lock = Lock()

    def _get_artifact(self) -> dict[str, Any]:
        if self._artifact is not None:
            return self._artifact

        with self._lock:
            if self._artifact is None:
                self._artifact = load_model_artifact(self._model_path)

        return self._artifact

    def warmup(self) -> None:
        """Precarga modelo serializado y features base en memoria al iniciar el servicio."""
        self._get_artifact()
        warmup_fn = getattr(self._feature_provider, "warmup", None)
        if callable(warmup_fn):
            warmup_fn()

    def predict_single(
        self,
        neighborhood_id: int,
        *,
        high_risk_threshold: float | None = None,
    ) -> dict[str, Any]:
        features = self._feature_provider.get_neighborhood_features(neighborhood_id)
        if features is None:
            raise KeyError(f"No se encontraron features para el barrio {neighborhood_id}.")

        artifact = self._get_artifact()
        return predict_neighborhood_risk(
            artifact,
            neighborhood_id,
            features,
            high_risk_threshold=high_risk_threshold,
        )

    def predict_all(
        self,
        *,
        neighborhood_ids: Sequence[int] | None = None,
        high_risk_threshold: float | None = None,
    ) -> dict[str, Any]:
        features_df = self._feature_provider.get_all_neighborhood_features(neighborhood_ids)
        artifact = self._get_artifact()
        return predict_batch_risks(
            artifact,
            features_df,
            high_risk_threshold=high_risk_threshold,
        )


_DEFAULT_SERVICE_LOCK = Lock()
_DEFAULT_SERVICE: RiskInferenceService | None = None


def get_default_inference_service() -> RiskInferenceService:
    """Retorna el singleton por defecto cuando no se inyecta servicio en app.state."""
    global _DEFAULT_SERVICE
    if _DEFAULT_SERVICE is not None:
        return _DEFAULT_SERVICE

    with _DEFAULT_SERVICE_LOCK:
        if _DEFAULT_SERVICE is None:
            model_path = os.getenv("HUB_MODEL_PATH", str(DEFAULT_MODEL_PATH)).strip() or str(
                DEFAULT_MODEL_PATH
            )
            _DEFAULT_SERVICE = RiskInferenceService(model_path=model_path)

    return _DEFAULT_SERVICE


def get_inference_service(request: Request) -> RiskInferenceService:
    """Dependencia FastAPI que resuelve el servicio de inferencia activo."""
    service = getattr(request.app.state, "risk_inference_service", None)
    if isinstance(service, RiskInferenceService):
        return service

    default_service = get_default_inference_service()
    request.app.state.risk_inference_service = default_service
    return default_service


def _resolve_expected_caps_key(request: Request) -> str:
    direct_key = getattr(request.app.state, "caps_api_key", None)
    if isinstance(direct_key, str) and direct_key.strip():
        return direct_key.strip()

    settings = getattr(request.app.state, "settings", None)
    settings_key = getattr(settings, "caps_api_key", None)
    if isinstance(settings_key, str) and settings_key.strip():
        return settings_key.strip()

    return os.getenv("CENTINELA_CAPS_API_KEY", "").strip()


def verify_predict_caps_key(
    request: Request,
    x_caps_key: str | None = Header(default=None, alias="X-CAPS-Key"),
) -> str:
    """Valida en tiempo constante la cabecera X-CAPS-Key (HTTP 401 si ausente o inválida)."""
    expected = _resolve_expected_caps_key(request)
    if not expected or not x_caps_key or not secrets.compare_digest(x_caps_key, expected):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Cabecera X-CAPS-Key ausente o inválida.",
        )

    return x_caps_key


def _parse_neighborhood_ids_query(raw: str | None) -> list[int] | None:
    if raw is None or not raw.strip():
        return None

    parsed: list[int] = []
    for token in raw.split(","):
        cleaned = token.strip()
        if not cleaned:
            continue
        if not cleaned.isdigit() or int(cleaned) < 1:
            raise HTTPException(
                status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
                detail="El parámetro 'neighborhood_ids' debe contener enteros positivos separados por coma.",
            )
        parsed.append(int(cleaned))

    if not parsed:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="El parámetro 'neighborhood_ids' no contiene identificadores válidos.",
        )

    return parsed


router = APIRouter(prefix="/api/v1/neighborhoods", tags=["predictions"])


@router.get(
    "/risks",
    response_model=BatchNeighborhoodRiskOut,
    summary="Inferencia batch de riesgo epidemiológico para todos los barrios",
)
def get_batch_neighborhood_risks(
    neighborhood_ids: str | None = Query(
        default=None,
        description="Lista opcional de IDs de barrio separados por coma (ej. 1,14,44).",
    ),
    high_risk_threshold: float | None = Query(
        default=None,
        gt=0.0,
        le=1.0,
        description="Umbral opcional de probabilidad para clasificar riesgo alto.",
    ),
    _caps_key: str = Depends(verify_predict_caps_key),
    service: RiskInferenceService = Depends(get_inference_service),
) -> BatchNeighborhoodRiskOut:
    ids = _parse_neighborhood_ids_query(neighborhood_ids)
    try:
        payload = service.predict_all(
            neighborhood_ids=ids,
            high_risk_threshold=high_risk_threshold,
        )
    except FileNotFoundError as exc:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="El artefacto del modelo predictivo no se encuentra disponible.",
        ) from exc

    return BatchNeighborhoodRiskOut.model_validate(payload)


@router.get(
    "/{neighborhood_id}/risk",
    response_model=NeighborhoodRiskOut,
    summary="Inferencia individual de riesgo epidemiológico para un barrio",
)
def get_neighborhood_risk(
    neighborhood_id: int = FastApiPath(..., ge=1, description="Identificador numérico del barrio."),
    high_risk_threshold: float | None = Query(
        default=None,
        gt=0.0,
        le=1.0,
        description="Umbral opcional de probabilidad para clasificar riesgo alto.",
    ),
    _caps_key: str = Depends(verify_predict_caps_key),
    service: RiskInferenceService = Depends(get_inference_service),
) -> NeighborhoodRiskOut:
    try:
        payload = service.predict_single(
            neighborhood_id,
            high_risk_threshold=high_risk_threshold,
        )
    except KeyError as exc:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"No existen datos analíticos disponibles para el barrio {neighborhood_id}.",
        ) from exc
    except FileNotFoundError as exc:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="El artefacto del modelo predictivo no se encuentra disponible.",
        ) from exc

    return NeighborhoodRiskOut.model_validate(payload)


def create_predict_app(
    *,
    caps_api_key: str | None = None,
    inference_service: RiskInferenceService | None = None,
) -> FastAPI:
    """Construye una app FastAPI autocontenida para inferencia predictiva y pruebas."""
    resolved_service = inference_service or get_default_inference_service()
    resolved_key = (
        caps_api_key.strip()
        if isinstance(caps_api_key, str)
        else os.getenv("CENTINELA_CAPS_API_KEY", "").strip()
    )

    @asynccontextmanager
    async def lifespan(application: FastAPI) -> AsyncIterator[None]:
        application.state.caps_api_key = resolved_key
        application.state.risk_inference_service = resolved_service
        resolved_service.warmup()
        yield

    application = FastAPI(
        title="Centinela Hub Analytics — Inference API",
        description="Servicio de inferencia predictiva de riesgo epidemiológico barrial (RF-GEO-08).",
        version="1.0.0",
        lifespan=lifespan,
    )
    application.state.caps_api_key = resolved_key
    application.state.risk_inference_service = resolved_service

    @application.get("/health", tags=["health"])
    def healthcheck() -> dict[str, str]:
        return {"status": "ok", "service": "centinela-hub-predict"}

    application.include_router(router)
    return application
