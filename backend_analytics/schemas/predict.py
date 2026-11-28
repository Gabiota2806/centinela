"""Esquemas Pydantic v2 para respuestas de inferencia de riesgo barrial (CEN-57)."""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field


class RiskProbabilitiesOut(BaseModel):
    """Vector de probabilidades por clase de riesgo epidemiológico."""

    model_config = ConfigDict(extra="forbid")

    low: float = Field(..., ge=0.0, le=1.0)
    moderate: float = Field(..., ge=0.0, le=1.0)
    high: float = Field(..., ge=0.0, le=1.0)


class NeighborhoodRiskOut(BaseModel):
    """Respuesta canónica de inferencia individual conforme a 00_contracts.md §4.2."""

    model_config = ConfigDict(extra="forbid")

    neighborhood_id: int = Field(..., ge=1)
    risk_class: int = Field(..., ge=0, le=2)
    risk_level: Literal["low", "moderate", "high"]
    risk_score: float = Field(..., ge=0.0, le=1.0)
    probabilities: RiskProbabilitiesOut
    model_version: str = Field(..., min_length=1)
    predicted_at: str = Field(..., min_length=1)


class BatchNeighborhoodRiskOut(BaseModel):
    """Respuesta canónica de inferencia en lote conforme a 00_contracts.md §4.2."""

    model_config = ConfigDict(extra="forbid")

    items: list[NeighborhoodRiskOut]
    model_version: str = Field(..., min_length=1)
    predicted_at: str = Field(..., min_length=1)
