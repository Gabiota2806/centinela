<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados del flujo de vida de un triage clínico en CAPS.
 * Referencia: docs/backlog.md §CEN-14.
 */
enum TriageStatus: string
{
    case PENDING_CONSULTATION = 'pending_consultation'; // En espera de llamada médica
    case IN_CONSULTATION = 'in_consultation';           // Siendo atendido por médico de guardia
    case COMPLETED = 'completed';                       // Consulta finalizada
    case CANCELLED = 'cancelled';                       // Cancelado o retirado sin atención

    public function label(): string
    {
        return match ($this) {
            self::PENDING_CONSULTATION => 'En espera de consulta',
            self::IN_CONSULTATION => 'En consulta',
            self::COMPLETED => 'Completado',
            self::CANCELLED => 'Cancelado',
        };
    }
}
