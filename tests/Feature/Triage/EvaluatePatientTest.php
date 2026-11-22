<?php

declare(strict_types=1);

namespace Tests\Feature\Triage;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Livewire\Triage\EvaluatePatient;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
            ->test(EvaluatePatient::class, ['patient' => $patient])
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
            ->test(EvaluatePatient::class, ['patient' => $patient])
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
            ->test(EvaluatePatient::class, ['patient' => $patient])
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
            ->test(EvaluatePatient::class, ['patient' => $patient])
            ->set('symptoms', 'Consulta de guardia')
            ->set('temperature', 55.0) // Fuera de rango
            ->set('spo2', 150)         // Fuera de rango
            ->call('save')
            ->assertHasErrors(['temperature', 'spo2']);
    }

    public function test_personal_no_autorizado_es_rechazado_al_montar_componente(): void
    {
        $admission = User::factory()->admission()->create();

        Livewire::actingAs($admission)
            ->test(EvaluatePatient::class)
            ->assertForbidden();
    }

    public function test_listado_de_pacientes_carga_relacion_neighborhood_con_eager_loading(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        Patient::factory()->create();

        $component = Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class);

        /** @var Collection<int, Patient> $patients */
        $patients = $component->viewData('availablePatients');
        $this->assertNotEmpty($patients);
        $this->assertTrue($patients->first()->relationLoaded('neighborhood'));
    }

    public function test_soporta_montar_con_patient_id_o_modelo_patient(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create();

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class, ['patientId' => $patient->id])
            ->assertSet('patientId', $patient->id)
            ->assertSet('patient.id', $patient->id);
    }

    public function test_la_temperatura_admite_decimales_y_se_sincroniza_al_salir_del_campo(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create();

        // Con sincronización "live" el valor parcial "38." llegaba vacío y borraba el campo (QA CEN-37).
        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->assertSeeHtml('wire:model.live.blur="temperature"')
            // type="number" descarta "38.9" si el navegador está en español (espera coma).
            ->assertSeeHtml('inputmode="decimal"')
            ->assertSeeHtml("replace(',', '.')")
            ->call('selectPatient', $patient->id)
            ->set('symptoms', 'Fiebre')
            ->set('temperature', 38.9)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(38.9, (float) Triage::query()->latest('id')->value('vital_signs')['temperature']);
    }

    public function test_los_signos_vitales_no_usan_campos_numericos_con_flechas_ni_rueda(): void
    {
        // type="number" muestra flechas y cambia el valor con la rueda del mouse (QA CEN-37).
        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(EvaluatePatient::class)
            ->assertDontSeeHtml('type="number"')
            ->assertSeeHtml('inputmode="numeric"');
    }

    public function test_la_vista_solo_usa_colores_de_marca_definidos_en_la_paleta(): void
    {
        // brand-azul no existe en tailwind.config.js: el botón "Registrar Triage Clínico" quedaba blanco sobre blanco.
        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(EvaluatePatient::class)
            ->assertDontSeeHtml('brand-azul')
            ->assertSeeHtml('bg-brand-petroleo');
    }
}
