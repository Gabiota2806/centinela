<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados de la consulta médica en guardia de CAPS.
 * Referencia: docs/backlog.md §CEN-14.
 */
enum ConsultationStatus: string
{
    case IN_PROGRESS = 'in_progress';         // Consulta abierta en curso
    case COMPLETED = 'completed';             // Consulta concluida con alta o tratamiento
    case REFERRED_SIPEC = 'referred_sipec';   // Derivación urgente a hospital de referencia vía SIPEC

    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'En curso',
            self::COMPLETED => 'Finalizada',
            self::REFERRED_SIPEC => 'Derivado SIPEC',
        };
    }
}
