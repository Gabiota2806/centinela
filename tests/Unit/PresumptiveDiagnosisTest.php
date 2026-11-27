<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PresumptiveDiagnosis;
use PHPUnit\Framework\TestCase;

/**
 * CEN-47: catálogo canónico de diagnósticos presuntivos (docs/specs/00_contracts.md §5.1).
 */
class PresumptiveDiagnosisTest extends TestCase
{
    public function test_el_catalogo_coincide_con_el_contrato(): void
    {
        $esperado = [
            'sindrome_febril_inespecifico' => ['Síndrome Febril Agudo Inespecífico (SFI)', true],
            'sospecha_dengue' => ['Sospecha de Dengue / Arbovirosis', true],
            'ira' => ['Infección Respiratoria Aguda (IRA)', true],
            'gastroenteritis' => ['Gastroenteritis Aguda', false],
            'traumatismo_herida' => ['Traumatismo / Herida', false],
            'hipertension_cardiovascular' => ['Crisis Hipertensiva / Cardiovascular', false],
            'otro_clinico' => ['Otra Patología Clínica / Control', false],
        ];

        $real = [];
        foreach (PresumptiveDiagnosis::cases() as $diagnostico) {
            $real[$diagnostico->value] = [$diagnostico->label(), $diagnostico->isFebrile()];
        }

        $this->assertSame($esperado, $real);
    }
}
