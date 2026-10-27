<?php

namespace App\Enums;

enum UserRole: string
{
    case Admision = 'admision';
    case Triage = 'triage';
    case Medico = 'medico';
    case Ministerio = 'ministerio';

    public function label(): string
    {
        return match($this) {
            self::Admision => 'Personal de Admisión',
            self::Triage => 'Enfermería de Triage MTS',
            self::Medico => 'Profesional Médico de Guardia',
            self::Ministerio => 'Autoridad Sanitaria / Nivel Central',
        };
    }
}
