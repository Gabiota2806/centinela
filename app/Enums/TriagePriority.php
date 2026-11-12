<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Niveles estandarizados de prioridad según el Sistema Manchester de Triage (MTS).
 * Referencia: docs/specs/global_spec.md §2.B y docs/backlog.md §CEN-12 / CEN-14.
 */
enum TriagePriority: string
{
    case RED = 'red';         // Emergencia / Reanimación (Nivel 1)
    case ORANGE = 'orange';   // Muy Urgente (Nivel 2)
    case YELLOW = 'yellow';   // Urgente (Nivel 3)
    case GREEN = 'green';     // Poco Urgente (Nivel 4)
    case BLUE = 'blue';       // No Urgente (Nivel 5)

    /**
     * Nombre legible del nivel de prioridad clínica.
     */
    public function label(): string
    {
        return match ($this) {
            self::RED => 'Rojo (Emergencia)',
            self::ORANGE => 'Naranja (Muy Urgente)',
            self::YELLOW => 'Amarillo (Urgente)',
            self::GREEN => 'Verde (Poco Urgente)',
            self::BLUE => 'Azul (No Urgente)',
        };
    }

    /**
     * Nivel numérico Manchester (1 a 5).
     */
    public function level(): int
    {
        return match ($this) {
            self::RED => 1,
            self::ORANGE => 2,
            self::YELLOW => 3,
            self::GREEN => 4,
            self::BLUE => 5,
        };
    }

    /**
     * Tiempo objetivo máximo de espera para atención médica en minutos.
     */
    public function targetWaitMinutes(): int
    {
        return match ($this) {
            self::RED => 0,
            self::ORANGE => 10,
            self::YELLOW => 60,
            self::GREEN => 120,
            self::BLUE => 240,
        };
    }

    /**
     * Clases utilitarias de Tailwind CSS estandarizadas para badges e indicadores.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::RED => 'bg-red-600 text-white',
            self::ORANGE => 'bg-orange-600 text-white',
            self::YELLOW => 'bg-yellow-500 text-black',
            self::GREEN => 'bg-green-600 text-white',
            self::BLUE => 'bg-blue-600 text-white',
        };
    }

    /**
     * Código hexadecimal de color oficial MTS.
     */
    public function hexColor(): string
    {
        return match ($this) {
            self::RED => '#DC2626',
            self::ORANGE => '#EA580C',
            self::YELLOW => '#EAB308',
            self::GREEN => '#16A34A',
            self::BLUE => '#2563EB',
        };
    }
}
