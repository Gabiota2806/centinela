"""Módulo de entrenamiento, serialización e inferencia Random Forest (CEN-56).

Expone carga perezosa vía `__getattr__` para permitir ejecutar
`python -m backend_analytics.ml.train_random_forest` sin advertencias de `runpy`.
"""

from __future__ import annotations

from importlib import import_module
from typing import Any

__all__ = [
    "DEFAULT_MODEL_PATH",
    "MODEL_VERSION",
    "RISK_CLASS_LABELS",
    "assign_risk_labels",
    "chronological_train_test_split",
    "load_model_artifact",
    "predict_batch_risks",
    "predict_neighborhood_risk",
    "save_model_artifact",
    "train_from_synthetic_data",
    "train_risk_classifier",
]


def __getattr__(name: str) -> Any:
    if name in __all__:
        module = import_module("backend_analytics.ml.train_random_forest")
        return getattr(module, name)
    raise AttributeError(f"module {__name__!r} has no attribute {name!r}")
