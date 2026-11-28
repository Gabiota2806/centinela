<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepción de dominio lanzada cuando el Hub analítico FastAPI no está disponible
 * o devuelve una respuesta de inferencia inválida (RF-GEO-09).
 */
class PredictiveRiskException extends RuntimeException {}
