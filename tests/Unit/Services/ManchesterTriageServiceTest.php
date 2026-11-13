<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TriagePriority;
use App\Services\ManchesterTriageService;
use Tests\TestCase;

/**
 * Pruebas unitarias de la lógica del árbol determinístico clínico Manchester (MTS).
 * Referencias:
 * - docs/specs/triage/spec.md
 * - docs/backlog.md §CEN-35 / CEN-38
 */
class ManchesterTriageServiceTest extends TestCase
{
    private ManchesterTriageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ManchesterTriageService;
    }

    public function test_via_aerea_obstruida_clasifica_como_rojo(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['spo2' => 98, 'temperature' => 36.6],
            discriminators: ['airway_obstruction']
        );

        $this->assertSame(TriagePriority::RED, $result['priority']);
        $this->assertSame(0, $result['target_wait_minutes']);
        $this->assertStringContainsString('Vía aérea obstruida', $result['discriminator']);
    }

    public function test_apnea_clasifica_como_rojo(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['respiratory_rate' => 0],
            discriminators: ['apnea']
        );

        $this->assertSame(TriagePriority::RED, $result['priority']);
        $this->assertSame(0, $result['target_wait_minutes']);
    }

    public function test_pulso_no_detectable_clasifica_como_rojo(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['heart_rate' => 0],
            discriminators: ['undetectable_pulse']
        );

        $this->assertSame(TriagePriority::RED, $result['priority']);
        $this->assertSame(0, $result['target_wait_minutes']);
    }

    public function test_spo2_critica_menor_a_85_clasifica_inmediatamente_como_rojo(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['spo2' => 84, 'temperature' => 36.8, 'systolic_bp' => 120]
        );

        $this->assertSame(TriagePriority::RED, $result['priority']);
        $this->assertSame(0, $result['target_wait_minutes']);
        $this->assertStringContainsString('SpO2 < 85%', $result['discriminator']);
    }

    public function test_colapso_hemodinamico_grave_pas_menor_a_70_clasifica_como_rojo(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['systolic_bp' => 65, 'spo2' => 95]
        );

        $this->assertSame(TriagePriority::RED, $result['priority']);
        $this->assertSame(0, $result['target_wait_minutes']);
    }

    public function test_dolor_severo_clasifica_como_naranja(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['spo2' => 97, 'temperature' => 37.0],
            discriminators: ['severe_pain']
        );

        $this->assertSame(TriagePriority::ORANGE, $result['priority']);
        $this->assertSame(10, $result['target_wait_minutes']);
        $this->assertStringContainsString('Dolor severo', $result['discriminator']);
    }

    public function test_disnea_aguda_clasifica_como_naranja(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['respiratory_rate' => 38, 'spo2' => 96],
            discriminators: ['acute_dyspnea']
        );

        $this->assertSame(TriagePriority::ORANGE, $result['priority']);
        $this->assertSame(10, $result['target_wait_minutes']);
    }

    public function test_letargo_o_alteracion_de_conciencia_clasifica_como_naranja(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['spo2' => 95],
            discriminators: ['lethargy']
        );

        $this->assertSame(TriagePriority::ORANGE, $result['priority']);
        $this->assertSame(10, $result['target_wait_minutes']);
    }

    public function test_spo2_entre_85_y_92_clasifica_como_naranja(): void
    {
        $testValues = [85, 88, 92];

        foreach ($testValues as $spo2) {
            $result = $this->service->evaluate(
                vitalSigns: ['spo2' => $spo2]
            );

            $this->assertSame(TriagePriority::ORANGE, $result['priority'], "Fallo para SpO2 = {$spo2}");
            $this->assertSame(10, $result['target_wait_minutes']);
            $this->assertStringContainsString('SpO2 85% - 92%', $result['discriminator']);
        }
    }

    public function test_shock_e_inestabilidad_hemodinamica_clasifica_como_naranja(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['systolic_bp' => 85, 'heart_rate' => 130, 'spo2' => 96]
        );

        $this->assertSame(TriagePriority::ORANGE, $result['priority']);
        $this->assertSame(10, $result['target_wait_minutes']);
    }

    public function test_sospecha_febril_de_dengue_en_formosa_clasifica_como_amarillo(): void
    {
        // Caso A: Temperatura > 39°C con cefalea o mialgia
        $resultA = $this->service->evaluate(
            vitalSigns: ['temperature' => 39.4, 'spo2' => 97],
            discriminators: ['headache', 'myalgia']
        );

        $this->assertSame(TriagePriority::YELLOW, $resultA['priority']);
        $this->assertSame(60, $resultA['target_wait_minutes']);
        $this->assertStringContainsString('dengue', strtolower($resultA['discriminator']));

        // Caso B: Discriminador explícito dengue_suspicion
        $resultB = $this->service->evaluate(
            vitalSigns: ['temperature' => 38.8, 'spo2' => 98],
            discriminators: ['dengue_suspicion']
        );

        $this->assertSame(TriagePriority::YELLOW, $resultB['priority']);
        $this->assertSame(60, $resultB['target_wait_minutes']);
    }

    public function test_spo2_entre_93_y_94_clasifica_como_amarillo(): void
    {
        foreach ([93, 94] as $spo2) {
            $result = $this->service->evaluate(
                vitalSigns: ['spo2' => $spo2]
            );

            $this->assertSame(TriagePriority::YELLOW, $result['priority'], "Fallo para SpO2 = {$spo2}");
            $this->assertSame(60, $result['target_wait_minutes']);
            $this->assertStringContainsString('SpO2 93% - 94%', $result['discriminator']);
        }
    }

    public function test_dolor_moderado_clasifica_como_amarillo(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['spo2' => 98],
            discriminators: ['moderate_pain']
        );

        $this->assertSame(TriagePriority::YELLOW, $result['priority']);
        $this->assertSame(60, $result['target_wait_minutes']);
    }

    public function test_fiebre_menor_a_38_5_sin_alteracion_hemodinamica_clasifica_como_verde(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['temperature' => 38.1, 'spo2' => 98, 'systolic_bp' => 120, 'heart_rate' => 75]
        );

        $this->assertSame(TriagePriority::GREEN, $result['priority']);
        $this->assertSame(120, $result['target_wait_minutes']);
    }

    public function test_dolor_leve_reciente_clasifica_como_verde(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['temperature' => 36.5, 'spo2' => 99],
            discriminators: ['mild_pain']
        );

        $this->assertSame(TriagePriority::GREEN, $result['priority']);
        $this->assertSame(120, $result['target_wait_minutes']);
    }

    public function test_chequeo_de_rutina_retiro_de_puntos_o_administrativo_clasifica_como_azul(): void
    {
        $cases = [
            'routine_check' => 'Chequeo de rutina',
            'stitches_removal' => 'Retiro de puntos',
            'administrative' => 'Trámite administrativo',
            'chronic_stable' => 'Problema crónico estable',
        ];

        foreach ($cases as $code => $expectedFragment) {
            $result = $this->service->evaluate(
                vitalSigns: ['temperature' => 36.6, 'spo2' => 98, 'heart_rate' => 72],
                discriminators: [$code]
            );

            $this->assertSame(TriagePriority::BLUE, $result['priority'], "Fallo para {$code}");
            $this->assertSame(240, $result['target_wait_minutes']);
            $this->assertStringContainsString($expectedFragment, $result['discriminator']);
        }
    }

    public function test_prioridad_rojo_prevalece_sobre_criterios_de_menor_gravedad(): void
    {
        // Paciente con motivo de retiro de puntos (AZUL) pero con SpO2 crítica de 80% (ROJO)
        $result = $this->service->evaluate(
            vitalSigns: ['spo2' => 80],
            discriminators: ['stitches_removal', 'mild_pain']
        );

        $this->assertSame(TriagePriority::RED, $result['priority']);
        $this->assertSame(0, $result['target_wait_minutes']);
    }

    public function test_prioridad_naranja_prevalece_sobre_amarillo_y_verde(): void
    {
        // Paciente con fiebre dengue (AMARILLO) pero con dolor severo y disnea aguda (NARANJA)
        $result = $this->service->evaluate(
            vitalSigns: ['temperature' => 39.5, 'spo2' => 90],
            discriminators: ['dengue_suspicion', 'severe_pain', 'acute_dyspnea']
        );

        $this->assertSame(TriagePriority::ORANGE, $result['priority']);
        $this->assertSame(10, $result['target_wait_minutes']);
    }

    public function test_soporta_discriminadores_como_mapa_asociativo_de_checkboxes(): void
    {
        $result = $this->service->evaluate(
            vitalSigns: ['spo2' => 97],
            discriminators: [
                'severe_pain' => true,
                'mild_pain' => false,
                'routine_check' => false,
            ]
        );

        $this->assertSame(TriagePriority::ORANGE, $result['priority']);
        $this->assertSame(10, $result['target_wait_minutes']);
    }

    public function test_catalogo_de_discriminadores_contiene_todos_los_niveles(): void
    {
        $catalogue = $this->service->getDiscriminatorCatalogue();

        $this->assertArrayHasKey('red', $catalogue);
        $this->assertArrayHasKey('orange', $catalogue);
        $this->assertArrayHasKey('yellow', $catalogue);
        $this->assertArrayHasKey('green', $catalogue);
        $this->assertArrayHasKey('blue', $catalogue);

        $this->assertSame(TriagePriority::RED, $catalogue['red']['priority']);
        $this->assertNotEmpty($catalogue['red']['items']);
    }
}
