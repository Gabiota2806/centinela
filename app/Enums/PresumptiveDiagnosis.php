<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Diagnósticos presuntivos estandarizados del cierre de consulta.
 * Referencia: docs/specs/00_contracts.md §5.1 (consultations.diagnosis).
 */
enum PresumptiveDiagnosis: string
{
    case SINDROME_FEBRIL_INESPECIFICO = 'sindrome_febril_inespecifico';
    case SOSPECHA_DENGUE = 'sospecha_dengue';
    case IRA = 'ira';
    case GASTROENTERITIS = 'gastroenteritis';
    case TRAUMATISMO_HERIDA = 'traumatismo_herida';
    case HIPERTENSION_CARDIOVASCULAR = 'hipertension_cardiovascular';
    case OTRO_CLINICO = 'otro_clinico';

    public function label(): string
    {
        return match ($this) {
            self::SINDROME_FEBRIL_INESPECIFICO => 'Síndrome Febril Agudo Inespecífico (SFI)',
            self::SOSPECHA_DENGUE => 'Sospecha de Dengue / Arbovirosis',
            self::IRA => 'Infección Respiratoria Aguda (IRA)',
            self::GASTROENTERITIS => 'Gastroenteritis Aguda',
            self::TRAUMATISMO_HERIDA => 'Traumatismo / Herida',
            self::HIPERTENSION_CARDIOVASCULAR => 'Crisis Hipertensiva / Cardiovascular',
            self::OTRO_CLINICO => 'Otra Patología Clínica / Control',
        };
    }

    /**
     * Diagnósticos febriles sujetos a vigilancia epidemiológica.
     */
    public function isFebrile(): bool
    {
        return match ($this) {
            self::SINDROME_FEBRIL_INESPECIFICO, self::SOSPECHA_DENGUE, self::IRA => true,
            default => false,
        };
    }
}
