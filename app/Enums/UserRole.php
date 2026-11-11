<?php

namespace App\Enums;

/**
 * Roles del personal del nodo CAPS (docs/specs/auth/spec.md §4).
 */
enum UserRole: string
{
    case ADMISSION = 'admission';         // Mesa de entrada / Recepción
    case TRIAGE_NURSE = 'triage_nurse';   // Enfermero de Triage MTS
    case DOCTOR = 'doctor';               // Médico de Guardia
    case DIRECTOR = 'director';           // Director de CAPS / Autoridad Sanitaria

    public function label(): string
    {
        return match ($this) {
            self::ADMISSION => 'Admisión / Mesa de Entrada',
            self::TRIAGE_NURSE => 'Enfermero de Triage',
            self::DOCTOR => 'Médico de Guardia',
            self::DIRECTOR => 'Dirección de CAPS',
        };
    }

    /**
     * Nombre de la ruta a la que se redirige el rol después de iniciar sesión.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::ADMISSION => 'patients.index',
            self::TRIAGE_NURSE => 'triage.index',
            self::DOCTOR => 'consultations.index',
            self::DIRECTOR => 'dashboard',
        };
    }
}
