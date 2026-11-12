<?php

declare(strict_types=1);

namespace Tests\Feature\Triage;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Livewire\Triage\EvaluatePatient;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas de integración para el componente reactivo y flujo de triage (MTS).
 * Referencias:
 * - docs/specs/triage/spec.md
 * - docs/backlog.md §CEN-35 / CEN-38
 */
class EvaluatePatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_enfermero_de_triage_y_medico_pueden_acceder_a_triage(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $this->actingAs($nurse)
            ->get(route('triage.index'))
            ->assertOk()
            ->assertSeeLivewire(EvaluatePatient::class)
            ->assertSee('Clasificación de Triage (Sistema Manchester)');

        $doctor = User::factory()->doctor()->create();
        $this->actingAs($doctor)
            ->get(route('triage.index'))
            ->assertOk()
            ->assertSeeLivewire(EvaluatePatient::class);
    }

    public function test_personal_de_admision_es_rechazado_con_403(): void
    {
        $admission = User::factory()->admission()->create();

        $this->actingAs($admission)
            ->get(route('triage.index'))
            ->assertForbidden();
    }

    public function test_usuario_no_autenticado_es_redirigido_a_login(): void
    {
        $this->get(route('triage.index'))
            ->assertRedirect(route('login'));
    }

    public function test_recalculo_reactivo_actualiza_prioridad_y_tiempo_de_espera_ante_spo2_critica(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create();

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class, ['patient' => $patient->id])
            ->assertSet('priorityValue', 'blue')
            ->assertSet('targetWaitMinutes', 240)
            ->set('spo2', 82)
            ->assertSet('priorityValue', 'red')
            ->assertSet('targetWaitMinutes', 0)
            ->assertSee('Rojo (Emergencia)')
            ->assertSee('SpO2 < 85%');
    }

    public function test_recalculo_reactivo_con_discriminador_de_dengue_asigna_amarillo(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create();

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class, ['patient' => $patient->id])
            ->set('temperature', 39.5)
            ->set('discriminators.headache', true)
            ->assertSet('priorityValue', 'yellow')
            ->assertSet('targetWaitMinutes', 60)
            ->assertSee('Amarillo (Urgente)')
            ->assertSee('dengue');
    }

    public function test_guarda_exitosamente_triage_en_base_de_datos(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create();

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class, ['patient' => $patient->id])
            ->set('symptoms', 'Paciente refiere dolor torácico opresivo de inicio súbito')
            ->set('temperature', 37.2)
            ->set('systolic_bp', 130)
            ->set('diastolic_bp', 85)
            ->set('heart_rate', 95)
            ->set('respiratory_rate', 20)
            ->set('spo2', 96)
            ->set('discriminators.severe_pain', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('savedSuccessfully', true)
            ->assertSee('Triage registrado con éxito');

        $this->assertDatabaseHas('triages', [
            'patient_id' => $patient->id,
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::ORANGE->value,
            'final_priority' => TriagePriority::ORANGE->value,
            'is_override' => false,
            'target_wait_minutes' => 10,
            'status' => TriageStatus::PENDING_CONSULTATION->value,
        ]);

        /** @var Triage $triage */
        $triage = Triage::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame(96, $triage->vital_signs['spo2']);
        $this->assertContains('severe_pain', $triage->vital_signs['discriminators']);
    }

    public function test_validacion_falla_si_no_se_selecciona_paciente_o_faltan_sintomas(): void
    {
        $nurse = User::factory()->triageNurse()->create();

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->call('save')
            ->assertHasErrors(['patientId', 'symptoms']);
    }

    public function test_validacion_de_rangos_fisiologicos_de_signos_vitales(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create();

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class, ['patient' => $patient->id])
            ->set('symptoms', 'Consulta de guardia')
            ->set('temperature', 55.0) // Fuera de rango
            ->set('spo2', 150)         // Fuera de rango
            ->call('save')
            ->assertHasErrors(['temperature', 'spo2']);
    }
}
