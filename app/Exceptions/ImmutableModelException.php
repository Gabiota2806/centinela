<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/**
 * Excepción lanzada al intentar mutar o eliminar un modelo estrictamente inmutable
 * en Centinela (ej. registros de auditoría clínica en triage_audit_logs).
 */
class ImmutableModelException extends LogicException
{
    public static function cannotUpdate(): self
    {
        return new self(
            'Los registros de auditoría de Clinical Override son estrictamente inmutables y no pueden modificarse.'
        );
    }

    public static function cannotDelete(): self
    {
        return new self(
            'Los registros de auditoría de Clinical Override son estrictamente inmutables y no pueden eliminarse.'
        );
    }
}
