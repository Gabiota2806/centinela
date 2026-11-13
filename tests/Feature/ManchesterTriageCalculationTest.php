<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Livewire\Triage\EvaluatePatient;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use App\Services\ManchesterTriageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Suite de pruebas de integración para la clasificación clínica automatizada
 * bajo el Sistema Manchester de Triage (MTS).
 *
 * Cubre el 100% de los métodos y ramas de cálculo de prioridad en:
 * - App\Services\ManchesterTriageService
 * - App\Enums\TriagePriority
 * - App\Livewire\Triage\EvaluatePatient
 *
 * Referencias:
 * - docs/specs/triage/spec.md (RF-TRIAGE-01 a RF-TRIAGE-06)
 * - docs/backlog.md §CEN-38 [T3.7]
 */
class ManchesterTriageCalculationTest extends TestCase
{
    use RefreshDatabase;

    private ManchesterTriageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ManchesterTriageService::class);
    }

    /**
     * Provee escenarios integrales para los 5 niveles del Sistema Manchester (MTS)
     * combinando signos vitales y discriminadores clínicos.
     *
     * @return array<string, array{
     *     vitals: array<string, float|int|null>,
     *     discriminators: array<string, bool>,
     *     expectedPriority: TriagePriority,
     *     expectedWait: int,
     *     expectedLevel: int,
     *     expectedHex: string,
     *     expectedDiscriminatorFragment: string
     * }>
     */
    public static function fiveLevelsIntegrationProvider(): array
    {
        return [
            'Nivel 1 ROJO - Hipoxemia critica SpO2 < 85%' => [
                'vitals' => [
                    'temperature' => 36.8,
                    'systolic_bp' => 115,
                    'diastolic_bp' => 75,
                    'heart_rate' => 105,
                    'respiratory_rate' => 24,
                    'spo2' => 82,
                ],
                'discriminators' => [],
                'expectedPriority' => TriagePriority::RED,
                'expectedWait' => 0,
                'expectedLevel' => 1,
                'expectedHex' => '#DC2626',
                'expectedDiscriminatorFragment' => 'SpO2 < 85%',
            ],
            'Nivel 2 NARANJA - Hipoxemia moderada SpO2 89% e inestabilidad' => [
                'vitals' => [
                    'temperature' => 37.4,
                    'systolic_bp' => 110,
                    'diastolic_bp' => 70,
                    'heart_rate' => 98,
                    'respiratory_rate' => 22,
                    'spo2' => 89,
                ],
                'discriminators' => [],
                'expectedPriority' => TriagePriority::ORANGE,
                'expectedWait' => 10,
                'expectedLevel' => 2,
                'expectedHex' => '#EA580C',
                'expectedDiscriminatorFragment' => 'SpO2 85% - 92%',
            ],
            'Nivel 3 AMARILLO - Sospecha febril de dengue en Formosa (> 39C + mialgias)' => [
                'vitals' => [
                    'temperature' => 39.6,
                    'systolic_bp' => 120,
                    'diastolic_bp' => 80,
                    'heart_rate' => 92,
                    'respiratory_rate' => 18,
                    'spo2' => 97,
                ],
                'discriminators' => ['myalgia' => true],
                'expectedPriority' => TriagePriority::YELLOW,
                'expectedWait' => 60,
                'expectedLevel' => 3,
                'expectedHex' => '#EAB308',
                'expectedDiscriminatorFragment' => 'dengue',
            ],
            'Nivel 4 VERDE - Febricula sin compromiso hemodinamico (38.0C)' => [
                'vitals' => [
                    'temperature' => 38.0,
                    'systolic_bp' => 122,
                    'diastolic_bp' => 78,
                    'heart_rate' => 76,
                    'respiratory_rate' => 16,
                    'spo2' => 98,
                ],
                'discriminators' => [],
                'expectedPriority' => TriagePriority::GREEN,
                'expectedWait' => 120,
                'expectedLevel' => 4,
                'expectedHex' => '#16A34A',
                'expectedDiscriminatorFragment' => 'Fiebre < 38.5°C',
            ],
            'Nivel 5 AZUL - Control de rutina con signos vitales fisiologicos normales' => [
                'vitals' => [
                    'temperature' => 36.5,
                    'systolic_bp' => 120,
                    'diastolic_bp' => 80,
                    'heart_rate' => 70,
                    'respiratory_rate' => 15,
                    'spo2' => 99,
                ],
                'discriminators' => ['routine_check' => true],
                'expectedPriority' => TriagePriority::BLUE,
                'expectedWait' => 240,
                'expectedLevel' => 5,
                'expectedHex' => '#2563EB',
                'expectedDiscriminatorFragment' => 'Chequeo de rutina',
            ],
        ];
    }

    #[DataProvider('fiveLevelsIntegrationProvider')]
    public function test_integracion_completa_calcula_y_persiste_los_cinco_niveles_manchester(
        array $vitals,
        array $discriminators,
        TriagePriority $expectedPriority,
        int $expectedWait,
        int $expectedLevel,
        string $expectedHex,
        string $expectedDiscriminatorFragment
    ): void {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create();

        $component = Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class, ['patient' => $patient])
            ->set('symptoms', 'Evaluación clínica integral MTS en guardia')
            ->set('temperature', $vitals['temperature'])
            ->set('systolic_bp', $vitals['systolic_bp'])
            ->set('diastolic_bp', $vitals['diastolic_bp'])
            ->set('heart_rate', $vitals['heart_rate'])
            ->set('respiratory_rate', $vitals['respiratory_rate'])
            ->set('spo2', $vitals['spo2']);

        foreach ($discriminators as $code => $active) {
            $component->set("discriminators.{$code}", $active);
        }

        $component
            ->assertSet('priorityValue', $expectedPriority->value)
            ->assertSet('priorityLabel', $expectedPriority->label())
            ->assertSet('targetWaitMinutes', $expectedWait)
            ->assertSet('badgeClasses', $expectedPriority->badgeClasses())
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('savedSuccessfully', true);

        $this->assertSame($expectedLevel, $expectedPriority->level());
        $this->assertSame($expectedHex, $expectedPriority->hexColor());

        /** @var Triage $triage */
        $triage = Triage::query()->where('patient_id', $patient->id)->latest('id')->firstOrFail();

        $this->assertSame($expectedPriority, $triage->algorithmic_priority);
        $this->assertSame($expectedPriority, $triage->final_priority);
        $this->assertFalse($triage->is_override);
        $this->assertSame($expectedWait, $triage->target_wait_minutes);
        $this->assertSame(TriageStatus::PENDING_CONSULTATION, $triage->status);
        $this->assertStringContainsString(
            $expectedDiscriminatorFragment,
            (string) $triage->vital_signs['calculated_discriminator']
        );
    }

    /**
     * Provee todas las condiciones de riesgo vital inminente que obligan a
     * asignar prioridad ROJO con 0 minutos de espera (RF-TRIAGE-03).
     *
     * @return array<string, array{vitals: array<string, float|int>, discriminators: array<string, bool>, expectedText: string}>
     */
    public static function imminentRiskRedProvider(): array
    {
        return [
            'SpO2 critica al 84% (frontera exacta < 85%)' => [
                'vitals' => ['spo2' => 84, 'temperature' => 36.6, 'systolic_bp' => 120, 'heart_rate' => 80, 'respiratory_rate' => 18],
                'discriminators' => ['routine_check' => true, 'mild_pain' => true],
                'expectedText' => 'SpO2 < 85%',
            ],
            'SpO2 critica severa al 65%' => [
                'vitals' => ['spo2' => 65, 'temperature' => 37.0, 'systolic_bp' => 110, 'heart_rate' => 115, 'respiratory_rate' => 28],
                'discriminators' => ['dengue_suspicion' => true],
                'expectedText' => 'SpO2 < 85%',
            ],
            'Bradipnea extrema / paro respiratorio inminente (FR = 5 rpm)' => [
                'vitals' => ['spo2' => 92, 'respiratory_rate' => 5, 'systolic_bp' => 100, 'heart_rate' => 60],
                'discriminators' => [],
                'expectedText' => 'Apnea o paro respiratorio inminente',
            ],
            'Colapso hemodinamico grave (PAS = 69 mmHg < 70)' => [
                'vitals' => ['spo2' => 95, 'systolic_bp' => 69, 'diastolic_bp' => 40, 'heart_rate' => 135, 'respiratory_rate' => 22],
                'discriminators' => [],
                'expectedText' => 'PAS < 70 mmHg',
            ],
            'Via aerea obstruida (airway_obstruction)' => [
                'vitals' => ['spo2' => 96, 'systolic_bp' => 125, 'heart_rate' => 90, 'respiratory_rate' => 20],
                'discriminators' => ['airway_obstruction' => true],
                'expectedText' => 'Vía aérea obstruida',
            ],
            'Via aerea comprometida (airway_compromised)' => [
                'vitals' => ['spo2' => 96, 'systolic_bp' => 120, 'heart_rate' => 88, 'respiratory_rate' => 19],
                'discriminators' => ['airway_compromised' => true],
                'expectedText' => 'Vía aérea obstruida o compromiso inminente',
            ],
            'Apnea declarada por discriminador' => [
                'vitals' => ['spo2' => 95, 'respiratory_rate' => 16],
                'discriminators' => ['apnea' => true],
                'expectedText' => 'Apnea o paro respiratorio inminente',
            ],
            'Paro respiratorio (respiratory_arrest)' => [
                'vitals' => ['spo2' => 95, 'respiratory_rate' => 16],
                'discriminators' => ['respiratory_arrest' => true],
                'expectedText' => 'Apnea o paro respiratorio inminente',
            ],
            'Pulso no detectable (undetectable_pulse)' => [
                'vitals' => ['spo2' => 95, 'heart_rate' => 75],
                'discriminators' => ['undetectable_pulse' => true],
                'expectedText' => 'Pulso no detectable',
            ],
            'Paro cardiaco (cardiac_arrest)' => [
                'vitals' => ['spo2' => 95, 'heart_rate' => 75],
                'discriminators' => ['cardiac_arrest' => true],
                'expectedText' => 'paro cardiorrespiratorio',
            ],
            'Shock descompensado por discriminador (severe_shock)' => [
                'vitals' => ['spo2' => 95, 'systolic_bp' => 100],
                'discriminators' => ['severe_shock' => true],
                'expectedText' => 'Shock descompensado',
            ],
            'Convulsion activa en curso (convulsion_active)' => [
                'vitals' => ['spo2' => 96, 'systolic_bp' => 130, 'heart_rate' => 110],
                'discriminators' => ['convulsion_active' => true],
                'expectedText' => 'Convulsión activa en curso',
            ],
        ];
    }

    #[DataProvider('imminentRiskRedProvider')]
    public function test_signos_de_riesgo_inminente_asignan_ineludiblemente_rojo_con_cero_minutos(
        array $vitals,
        array $discriminators,
        string $expectedText
    ): void {
        $doctor = User::factory()->doctor()->create();
        $patient = Patient::factory()->create();

        $component = Livewire::actingAs($doctor)
            ->test(EvaluatePatient::class, ['patient' => $patient])
            ->set('symptoms', 'Emergencia clínica con compromiso vital agudo');

        foreach ($vitals as $field => $value) {
            $component->set($field, $value);
        }

        foreach ($discriminators as $code => $active) {
            $component->set("discriminators.{$code}", $active);
        }

        $component
            ->assertSet('priorityValue', TriagePriority::RED->value)
            ->assertSet('targetWaitMinutes', 0)
            ->assertSet('badgeClasses', 'bg-red-600 text-white')
            ->call('save')
            ->assertHasNoErrors();

        $triage = Triage::query()->where('patient_id', $patient->id)->firstOrFail();

        $this->assertSame(TriagePriority::RED, $triage->algorithmic_priority);
        $this->assertSame(TriagePriority::RED, $triage->final_priority);
        $this->assertSame(0, $triage->target_wait_minutes);
        $this->assertStringContainsString($expectedText, (string) $triage->vital_signs['calculated_discriminator']);
    }

    /**
     * Provee todas las ramas clínicas del Nivel 2 (NARANJA - 10 min).
     *
     * @return array<string, array{vitals: array<string, float|int>, discriminators: array<string, bool>, expectedText: string}>
     */
    public static function orangeBranchesProvider(): array
    {
        return [
            'Dolor severo EVA 8-10' => [
                'vitals' => ['spo2' => 98, 'temperature' => 36.7],
                'discriminators' => ['severe_pain' => true],
                'expectedText' => 'Dolor severo (EVA 8-10)',
            ],
            'Disnea aguda por discriminador' => [
                'vitals' => ['spo2' => 95, 'respiratory_rate' => 22],
                'discriminators' => ['acute_dyspnea' => true],
                'expectedText' => 'Disnea aguda',
            ],
            'Taquipnea severa (FR = 36 rpm > 35)' => [
                'vitals' => ['spo2' => 96, 'respiratory_rate' => 36],
                'discriminators' => [],
                'expectedText' => 'alteración respiratoria severa',
            ],
            'Bradipnea moderada-severa (FR = 9 rpm < 10 y > 5)' => [
                'vitals' => ['spo2' => 96, 'respiratory_rate' => 9],
                'discriminators' => [],
                'expectedText' => 'alteración respiratoria severa',
            ],
            'Letargo (lethargy)' => [
                'vitals' => ['spo2' => 97],
                'discriminators' => ['lethargy' => true],
                'expectedText' => 'Letargo o alteración aguda del estado de conciencia',
            ],
            'Alteracion aguda de conciencia (altered_consciousness)' => [
                'vitals' => ['spo2' => 97],
                'discriminators' => ['altered_consciousness' => true],
                'expectedText' => 'Letargo o alteración aguda del estado de conciencia',
            ],
            'Hipoxemia moderada limite inferior (SpO2 = 85%)' => [
                'vitals' => ['spo2' => 85, 'respiratory_rate' => 20],
                'discriminators' => [],
                'expectedText' => 'Hipoxemia moderada (SpO2 85% - 92%)',
            ],
            'Hipoxemia moderada limite superior (SpO2 = 92%)' => [
                'vitals' => ['spo2' => 92, 'respiratory_rate' => 20],
                'discriminators' => [],
                'expectedText' => 'Hipoxemia moderada (SpO2 85% - 92%)',
            ],
            'Shock por discriminador clinico' => [
                'vitals' => ['spo2' => 96, 'systolic_bp' => 105, 'heart_rate' => 95],
                'discriminators' => ['shock' => true],
                'expectedText' => 'Signos de shock o inestabilidad hemodinámica',
            ],
            'Inestabilidad hemodinamica por hipotension (PAS 85) y taquicardia (FC 125)' => [
                'vitals' => ['spo2' => 96, 'systolic_bp' => 85, 'heart_rate' => 125],
                'discriminators' => [],
                'expectedText' => 'Signos de shock o inestabilidad hemodinámica',
            ],
            'Inestabilidad hemodinamica por hipotension (PAS 88) y bradicardia (FC 38)' => [
                'vitals' => ['spo2' => 96, 'systolic_bp' => 88, 'heart_rate' => 38],
                'discriminators' => [],
                'expectedText' => 'Signos de shock o inestabilidad hemodinámica',
            ],
            'Hiperpirexia extrema (Temperatura = 40.5 C)' => [
                'vitals' => ['spo2' => 97, 'temperature' => 40.5, 'systolic_bp' => 120, 'heart_rate' => 100],
                'discriminators' => [],
                'expectedText' => 'Hiperpirexia extrema (≥ 40.5 °C)',
            ],
        ];
    }

    #[DataProvider('orangeBranchesProvider')]
    public function test_todas_las_ramas_de_nivel_naranja_asignan_10_minutos(
        array $vitals,
        array $discriminators,
        string $expectedText
    ): void {
        $result = $this->service->evaluate($vitals, $discriminators);

        $this->assertSame(TriagePriority::ORANGE, $result['priority']);
        $this->assertSame(10, $result['target_wait_minutes']);
        $this->assertStringContainsString($expectedText, $result['discriminator']);
    }

    /**
     * Provee todas las ramas clínicas del Nivel 3 (AMARILLO - 60 min).
     *
     * @return array<string, array{vitals: array<string, float|int>, discriminators: array<string, bool>, expectedText: string}>
     */
    public static function yellowBranchesProvider(): array
    {
        return [
            'Fiebre > 39C con cefalea (sospecha dengue Formosa)' => [
                'vitals' => ['temperature' => 39.1, 'spo2' => 97],
                'discriminators' => ['headache' => true],
                'expectedText' => 'Fiebre persistente > 39°C con sospecha febril de dengue',
            ],
            'Fiebre > 39C con sintomas de dengue (dengue_symptoms)' => [
                'vitals' => ['temperature' => 39.8, 'spo2' => 96],
                'discriminators' => ['dengue_symptoms' => true],
                'expectedText' => 'Fiebre persistente > 39°C con sospecha febril de dengue',
            ],
            'Discriminador dengue_suspicion con fiebre moderada (38.2C)' => [
                'vitals' => ['temperature' => 38.2, 'spo2' => 98],
                'discriminators' => ['dengue_suspicion' => true],
                'expectedText' => 'Sospecha febril de dengue',
            ],
            'Desaturacion leve limite inferior (SpO2 = 93%)' => [
                'vitals' => ['spo2' => 93, 'temperature' => 36.8],
                'discriminators' => [],
                'expectedText' => 'Desaturación leve (SpO2 93% - 94%)',
            ],
            'Desaturacion leve limite superior (SpO2 = 94%)' => [
                'vitals' => ['spo2' => 94, 'temperature' => 36.8],
                'discriminators' => [],
                'expectedText' => 'Desaturación leve (SpO2 93% - 94%)',
            ],
            'Dolor moderado (EVA 4-7)' => [
                'vitals' => ['spo2' => 98, 'temperature' => 36.6],
                'discriminators' => ['moderate_pain' => true],
                'expectedText' => 'Dolor moderado (EVA 4-7)',
            ],
            'Fiebre elevada aislada en limite exacto (38.5C)' => [
                'vitals' => ['temperature' => 38.5, 'spo2' => 98],
                'discriminators' => [],
                'expectedText' => 'Fiebre elevada (≥ 38.5 °C)',
            ],
            'Fiebre elevada aislada sin sintomas de dengue (39.5C)' => [
                'vitals' => ['temperature' => 39.5, 'spo2' => 98],
                'discriminators' => [],
                'expectedText' => 'Fiebre elevada (≥ 38.5 °C)',
            ],
            'Hemorragia menor incontrolada' => [
                'vitals' => ['spo2' => 98, 'temperature' => 36.5],
                'discriminators' => ['uncontrolled_minor_hemorrhage' => true],
                'expectedText' => 'Hemorragia menor incontrolada',
            ],
            'Vomitos persistentes o deshidratacion moderada' => [
                'vitals' => ['spo2' => 97, 'temperature' => 37.0],
                'discriminators' => ['persistent_vomiting' => true],
                'expectedText' => 'Vómitos persistentes o deshidratación moderada',
            ],
        ];
    }

    #[DataProvider('yellowBranchesProvider')]
    public function test_todas_las_ramas_de_nivel_amarillo_asignan_60_minutos(
        array $vitals,
        array $discriminators,
        string $expectedText
    ): void {
        $result = $this->service->evaluate($vitals, $discriminators);

        $this->assertSame(TriagePriority::YELLOW, $result['priority']);
        $this->assertSame(60, $result['target_wait_minutes']);
        $this->assertStringContainsString($expectedText, $result['discriminator']);
    }

    /**
     * Provee todas las ramas clínicas del Nivel 4 (VERDE - 120 min).
     *
     * @return array<string, array{vitals: array<string, float|int>, discriminators: array<string, bool>, expectedText: string}>
     */
    public static function greenBranchesProvider(): array
    {
        return [
            'Febricula en limite inferior (37.5C)' => [
                'vitals' => ['temperature' => 37.5, 'spo2' => 98],
                'discriminators' => [],
                'expectedText' => 'Fiebre < 38.5°C sin alteración hemodinámica',
            ],
            'Fiebre baja en limite superior (38.4C)' => [
                'vitals' => ['temperature' => 38.4, 'spo2' => 98],
                'discriminators' => [],
                'expectedText' => 'Fiebre < 38.5°C sin alteración hemodinámica',
            ],
            'Discriminador explicito mild_fever con temperatura normal' => [
                'vitals' => ['temperature' => 37.0, 'spo2' => 98],
                'discriminators' => ['mild_fever' => true],
                'expectedText' => 'Fiebre < 38.5°C sin alteración hemodinámica',
            ],
            'Dolor leve reciente (EVA 1-3)' => [
                'vitals' => ['temperature' => 36.6, 'spo2' => 99],
                'discriminators' => ['mild_pain' => true],
                'expectedText' => 'Dolor leve reciente (EVA 1-3)',
            ],
            'Traumatismo menor reciente sin deformidad' => [
                'vitals' => ['temperature' => 36.6, 'spo2' => 99],
                'discriminators' => ['mild_trauma' => true],
                'expectedText' => 'Traumatismo menor reciente sin deformidad',
            ],
            'Vomitos leves sin deshidratacion' => [
                'vitals' => ['temperature' => 36.8, 'spo2' => 98],
                'discriminators' => ['mild_vomiting' => true],
                'expectedText' => 'Vómitos leves sin deshidratación',
            ],
            'Infeccion local menor o erupcion cutanea afebril' => [
                'vitals' => ['temperature' => 36.7, 'spo2' => 99],
                'discriminators' => ['local_infection' => true],
                'expectedText' => 'Infección local menor / erupción cutánea afebril',
            ],
        ];
    }

    #[DataProvider('greenBranchesProvider')]
    public function test_todas_las_ramas_de_nivel_verde_asignan_120_minutos(
        array $vitals,
        array $discriminators,
        string $expectedText
    ): void {
        $result = $this->service->evaluate($vitals, $discriminators);

        $this->assertSame(TriagePriority::GREEN, $result['priority']);
        $this->assertSame(120, $result['target_wait_minutes']);
        $this->assertStringContainsString($expectedText, $result['discriminator']);
    }

    /**
     * Provee todas las ramas clínicas del Nivel 5 (AZUL - 240 min), incluyendo fallback.
     *
     * @return array<string, array{vitals: array<string, float|int>, discriminators: array<string, bool>, expectedText: string}>
     */
    public static function blueBranchesProvider(): array
    {
        return [
            'Chequeo de rutina' => [
                'vitals' => ['temperature' => 36.5, 'spo2' => 98, 'systolic_bp' => 120, 'heart_rate' => 72, 'respiratory_rate' => 16],
                'discriminators' => ['routine_check' => true],
                'expectedText' => 'Chequeo de rutina / control periódico',
            ],
            'Retiro de puntos de sutura' => [
                'vitals' => ['temperature' => 36.6, 'spo2' => 99],
                'discriminators' => ['stitches_removal' => true],
                'expectedText' => 'Retiro de puntos de sutura',
            ],
            'Tramite administrativo o recetas' => [
                'vitals' => ['temperature' => 36.4, 'spo2' => 98],
                'discriminators' => ['administrative' => true],
                'expectedText' => 'Trámite administrativo o recetas crónicas',
            ],
            'Problema cronico estable sin signos de alarma' => [
                'vitals' => ['temperature' => 36.7, 'spo2' => 97],
                'discriminators' => ['chronic_stable' => true],
                'expectedText' => 'Problema crónico estable sin signos de alarma',
            ],
            'Fallback por defecto sin discriminadores y signos vitales normales' => [
                'vitals' => ['temperature' => 37.4, 'spo2' => 95, 'systolic_bp' => 90, 'heart_rate' => 100, 'respiratory_rate' => 10],
                'discriminators' => [],
                'expectedText' => 'Consulta no urgente / chequeo general',
            ],
        ];
    }

    #[DataProvider('blueBranchesProvider')]
    public function test_todas_las_ramas_de_nivel_azul_asignan_240_minutos(
        array $vitals,
        array $discriminators,
        string $expectedText
    ): void {
        $result = $this->service->evaluate($vitals, $discriminators);

        $this->assertSame(TriagePriority::BLUE, $result['priority']);
        $this->assertSame(240, $result['target_wait_minutes']);
        $this->assertStringContainsString($expectedText, $result['discriminator']);
    }

    public function test_normalizacion_de_signos_vitales_convierte_cadenas_numericas_e_ignora_valores_invalidos(): void
    {
        $normalized = $this->service->normalizeVitalSigns([
            'temperature' => '38.6',
            'systolic_bp' => '125',
            'diastolic_bp' => '82',
            'heart_rate' => '88',
            'respiratory_rate' => '18',
            'spo2' => '96',
        ]);

        $this->assertSame(38.6, $normalized['temperature']);
        $this->assertSame(125, $normalized['systolic_bp']);
        $this->assertSame(82, $normalized['diastolic_bp']);
        $this->assertSame(88, $normalized['heart_rate']);
        $this->assertSame(18, $normalized['respiratory_rate']);
        $this->assertSame(96, $normalized['spo2']);

        $emptyNormalized = $this->service->normalizeVitalSigns([
            'temperature' => 'no-numerico',
            'spo2' => null,
        ]);

        $this->assertNull($emptyNormalized['temperature']);
        $this->assertNull($emptyNormalized['spo2']);
        $this->assertNull($emptyNormalized['systolic_bp']);
    }

    public function test_flujo_reactivo_livewire_permite_buscar_deseleccionar_y_resetear_evaluacion(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patientA = Patient::factory()->create([
            'first_name' => 'Mariana',
            'last_name' => 'Gómez',
            'dni' => '38999111',
        ]);

        $component = Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->set('searchPatient', '38999111')
            ->call('selectPatient', $patientA->id)
            ->assertSet('patientId', $patientA->id)
            ->set('spo2', 80)
            ->assertSet('priorityValue', 'red')
            ->assertSet('targetWaitMinutes', 0)
            ->call('deselectPatient')
            ->assertSet('patientId', null)
            ->call('selectPatient', $patientA->id)
            ->call('resetEvaluation')
            ->assertSet('patientId', null)
            ->assertSet('spo2', null)
            ->assertSet('priorityValue', 'blue')
            ->assertSet('targetWaitMinutes', 240);

        $this->assertNotNull($component);
    }
}
