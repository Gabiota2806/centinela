<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ConsultationStatus;
use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Models\Consultation;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use App\Services\Telemetry\AnonymizerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pruebas unitarias de disociación irreversible y anonimización (Ley N° 25.326).
 * Referencia: docs/specs/00_contracts.md §4.1 y §5; docs/specs/geo/spec.md RF-GEO-04.
 */
class AnonymizerServiceTest extends TestCase
{
    private AnonymizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('centinela.caps_code', 'CAPS-CODDA-01');
        config()->set('centinela.timezone', 'America/Argentina/Cordoba');
        $this->service = new AnonymizerService;
    }

    public function test_build_payload_cumple_contrato_estricto_y_excluye_toda_pii(): void
    {
        $referenceDate = CarbonImmutable::parse('2026-11-24 15:30:00', 'America/Argentina/Cordoba');
        Carbon::setTestNow($referenceDate);

        try {
            $neighborhood = new Neighborhood;
            $neighborhood->id = 14;
            $neighborhood->name = 'San Miguel';

            $patient = new Patient([
                'dni' => '38123456',
                'first_name' => 'Juan',
                'last_name' => 'Pérez',
                'birth_date' => '1990-05-10',
                'gender' => 'Masculino',
                'phone' => '+54 370 4998877',
                'address' => 'Av. 25 de Mayo 1450',
                'neighborhood_id' => 14,
            ]);
            $patient->id = 99;
            $patient->setRelation('neighborhood', $neighborhood);

            $triage = new Triage([
                'patient_id' => 99,
                'symptoms' => 'Paciente Juan Pérez DNI 38123456 refiere fiebre mialgias cefalea intensa',
                'vital_signs' => [
                    'temperature' => 39.2,
                    'discriminators' => ['fiebre', 'mialgias', 'cefalea'],
                ],
                'algorithmic_priority' => TriagePriority::YELLOW,
                'final_priority' => TriagePriority::YELLOW,
                'status' => TriageStatus::COMPLETED,
            ]);
            $triage->setRelation('patient', $patient);

            $consultation = new Consultation([
                'diagnosis' => 'sospecha_dengue',
                'clinical_notes' => 'Control en 24hs - llamar a Juan Pérez 3704998877',
                'prescription' => 'Paracetamol 500mg',
                'status' => ConsultationStatus::COMPLETED,
                'completed_at' => $referenceDate,
            ]);

            $payload = $this->service->buildPayload($consultation, $triage);

            $expectedKeys = [
                'event_uuid',
                'caps_code',
                'neighborhood_id',
                'neighborhood_name',
                'age_group',
                'triage_priority',
                'presumptive_diagnosis',
                'symptoms_summary',
                'temperature_c',
                'recorded_at',
            ];

            $this->assertSame($expectedKeys, array_keys($payload));
            $this->assertTrue(Str::isUuid($payload['event_uuid']));
            $this->assertSame('CAPS-CODDA-01', $payload['caps_code']);
            $this->assertSame(14, $payload['neighborhood_id']);
            $this->assertSame('San Miguel', $payload['neighborhood_name']);
            $this->assertSame('adulto', $payload['age_group']);
            $this->assertSame('yellow', $payload['triage_priority']);
            $this->assertSame('sospecha_dengue', $payload['presumptive_diagnosis']);
            $this->assertSame('fiebre_mialgias_cefalea', $payload['symptoms_summary']);
            $this->assertSame(39.2, $payload['temperature_c']);
            $this->assertStringEndsWith('-03:00', $payload['recorded_at']);

            // Garantía Ley N° 25.326: ninguna clave ni valor identificatorio en el JSON final
            $forbiddenKeys = [
                'dni',
                'first_name',
                'last_name',
                'full_name',
                'phone',
                'address',
                'birth_date',
                'patient_id',
                'clinical_notes',
                'prescription',
            ];
            foreach ($forbiddenKeys as $key) {
                $this->assertArrayNotHasKey($key, $payload);
            }

            $serialized = json_encode($payload, JSON_THROW_ON_ERROR);
            foreach (['38123456', 'Juan', 'Pérez', '4998877', '25 de Mayo', '1990-05-10'] as $piiValue) {
                $this->assertStringNotContainsString($piiValue, $serialized);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_clasifica_correctamente_todos_los_grupos_etarios_en_bordes(): void
    {
        $now = CarbonImmutable::parse('2026-11-24', 'America/Argentina/Cordoba');

        $this->assertSame('infante', $this->service->resolveAgeGroup(CarbonImmutable::parse('2020-01-01'), $now));
        $this->assertSame('infante', $this->service->resolveAgeGroup(CarbonImmutable::parse('2015-11-24'), $now));
        $this->assertSame('adolescente', $this->service->resolveAgeGroup(CarbonImmutable::parse('2014-11-24'), $now));
        $this->assertSame('adolescente', $this->service->resolveAgeGroup(CarbonImmutable::parse('2009-11-24'), $now));
        $this->assertSame('adulto', $this->service->resolveAgeGroup(CarbonImmutable::parse('2008-11-24'), $now));
        $this->assertSame('adulto', $this->service->resolveAgeGroup(CarbonImmutable::parse('1967-11-24'), $now));
        $this->assertSame('mayor', $this->service->resolveAgeGroup(CarbonImmutable::parse('1966-11-24'), $now));
        $this->assertSame('mayor', $this->service->resolveAgeGroup(CarbonImmutable::parse('1945-06-15'), $now));
        $this->assertSame('adulto', $this->service->resolveAgeGroup(null, $now));
    }

    public function test_normaliza_diagnostico_desconocido_y_temperatura_fuera_de_rango(): void
    {
        $neighborhood = new Neighborhood;
        $neighborhood->id = 5;
        $neighborhood->name = 'Centro';

        $patient = new Patient([
            'dni' => '40111222',
            'first_name' => 'María',
            'last_name' => 'Gómez',
            'birth_date' => '1950-01-01',
            'phone' => '3704123456',
            'address' => 'Belgrano 200',
            'neighborhood_id' => 5,
        ]);
        $patient->setRelation('neighborhood', $neighborhood);

        $triage = new Triage([
            'symptoms' => 'María Gómez DNI 40111222 consulta por dolor abdominal leve',
            'vital_signs' => ['temperature' => 99.9],
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'status' => TriageStatus::COMPLETED,
        ]);
        $triage->setRelation('patient', $patient);

        $consultation = new Consultation([
            'diagnosis' => 'diagnostico_no_catalogado',
            'status' => ConsultationStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        $payload = $this->service->buildPayload($consultation, $triage);

        $this->assertSame('otro_clinico', $payload['presumptive_diagnosis']);
        $this->assertNull($payload['temperature_c']);
        $this->assertSame('mayor', $payload['age_group']);
        $this->assertStringNotContainsString('40111222', $payload['symptoms_summary']);
        $this->assertStringNotContainsString('maria', $payload['symptoms_summary']);
        $this->assertStringNotContainsString('gomez', $payload['symptoms_summary']);
    }
}
