<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados del ciclo de vida de eventos en la tabla Outbox de telemetría epidemiológica.
 * Referencia: docs/specs/00_contracts.md §2.
 */
enum OutboxTelemetryStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
