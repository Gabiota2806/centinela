<?php

declare(strict_types=1);

namespace Tests\Feature\Triage;

use App\Enums\TriagePriority;
use App\Livewire\Triage\ClinicalOverrideModal;
use App\Livewire\Triage\EvaluatePatient;
use App\Livewire\Triage\PrintTicket;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\TriageAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CEN-39: modal de Clinical Override rápido con justificación diferida
 * (docs/specs/triage/spec.md RF-TRIAGE-10 y RF-TRIAGE-11).
 */
class ClinicalOverrideModalTest extends TestCase
{
    use RefreshDatabase;

    private function triage(TriagePriority $priority = TriagePriority::GREEN): Triage
    {
        $paciente = Patient::factory()->create(['first_name' => 'Marta', 'last_name' => 'Rodríguez']);

        return Triage::factory()->create([
            'patient_id' => $paciente->id,
            'algorithmic_priority' => $priority,
            'final_priority' => $priority,
            'is_override' => false,
            'target_wait_minutes' => $priority->targetWaitMinutes(),
        ]);
    }

    public function test_override_de_emergencia_en_un_clic_con_justificacion_diferida(): void
    {
        // Gherkin CEN-39: Verde → Rojo marcando "Justificación Diferida".
        $triage = $this->triage(TriagePriority::GREEN);
        $enfermera = User::factory()->triageNurse()->create();

        Livewire::actingAs($enfermera)
            ->test(ClinicalOverrideModal::class)
            ->dispatch('open-clinical-override', triageId: $triage->id)
            ->set('newPriority', TriagePriority::RED->value)
            ->set('deferred', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('triage-overridden', triageId: $triage->id);

        $triage->refresh();
        $this->assertSame(TriagePriority::RED, $triage->final_priority);
        $this->assertSame(TriagePriority::GREEN, $triage->algorithmic_priority);
        $this->assertTrue($triage->is_override);
        $this->assertSame(0, $triage->target_wait_minutes);

        $log = TriageAuditLog::where('triage_id', $triage->id)->sole();
        $this->assertSame($enfermera->id, $log->user_id);
        $this->assertSame(TriagePriority::GREEN, $log->original_priority);
        $this->assertSame(TriagePriority::RED, $log->overridden_priority);
        $this->assertStringContainsString('JUSTIFICACIÓN DIFERIDA', $log->override_reason);
        $this->assertStringContainsString('Pendiente de redacción', $log->override_reason);
    }

    public function test_override_regular_guarda_el_motivo_en_la_auditoria(): void
    {
        $triage = $this->triage(TriagePriority::YELLOW);
        $medico = User::factory()->doctor()->create();

        Livewire::actingAs($medico)
            ->test(ClinicalOverrideModal::class)
            ->dispatch('open-clinical-override', triageId: $triage->id)
            ->set('newPriority', TriagePriority::ORANGE->value)
            ->set('reason', 'Dolor torácico con irradiación a brazo izquierdo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(TriagePriority::ORANGE, $triage->refresh()->final_priority);
        $this->assertSame(10, $triage->target_wait_minutes);
        $this->assertDatabaseHas('triage_audit_logs', [
            'triage_id' => $triage->id,
            'user_id' => $medico->id,
            'override_reason' => 'Dolor torácico con irradiación a brazo izquierdo',
        ]);
    }

    public function test_sin_diferir_el_motivo_es_obligatorio_y_de_al_menos_10_caracteres(): void
    {
        $triage = $this->triage();

        $componente = Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(ClinicalOverrideModal::class)
            ->dispatch('open-clinical-override', triageId: $triage->id)
            ->set('newPriority', TriagePriority::RED->value);

        $componente->set('reason', '')->call('save')->assertHasErrors(['reason' => 'required']);
        $componente->set('reason', 'Muy corto')->call('save')->assertHasErrors(['reason' => 'min']);
        $componente->assertSet('show', true)->assertNotDispatched('triage-overridden');

        $this->assertSame(TriagePriority::GREEN, $triage->refresh()->final_priority);
        $this->assertFalse($triage->is_override);
        $this->assertSame(0, TriageAuditLog::count());
    }

    public function test_hay_que_elegir_un_nivel_distinto_al_actual(): void
    {
        $triage = $this->triage(TriagePriority::GREEN);

        $componente = Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(ClinicalOverrideModal::class)
            ->dispatch('open-clinical-override', triageId: $triage->id)
            ->set('deferred', true);

        $componente->call('save')->assertHasErrors(['newPriority' => 'required']);
        $componente->set('newPriority', TriagePriority::GREEN->value)->call('save')->assertHasErrors('newPriority');
        $componente->set('newPriority', 'violeta')->call('save')->assertHasErrors('newPriority');

        $this->assertSame(0, TriageAuditLog::count());
    }

    public function test_el_modal_muestra_el_paciente_el_nivel_actual_y_los_5_niveles_mts(): void
    {
        $triage = $this->triage(TriagePriority::GREEN);

        $componente = Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(ClinicalOverrideModal::class)
            ->dispatch('open-clinical-override', triageId: $triage->id)
            ->assertSet('show', true)
            ->assertSee('Reclasificar prioridad')
            ->assertDontSee('Clinical Override')
            ->assertSeeHtml('max-w-2xl')
            ->assertSee('Marta Rodríguez')
            ->assertSee('Justificación diferida');

        foreach (TriagePriority::cases() as $priority) {
            $componente->assertSee($priority->label())
                ->assertSeeHtml('data-mts-option="'.$priority->value.'"')
                ->assertSee($priority->badgeClasses(), false);
        }
    }

    public function test_el_modal_esta_cerrado_hasta_recibir_un_triage_y_se_puede_cancelar(): void
    {
        $triage = $this->triage();

        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(ClinicalOverrideModal::class)
            ->assertSet('show', false)
            ->assertDontSee('Justificación diferida')
            ->dispatch('open-clinical-override', triageId: $triage->id)
            ->set('newPriority', TriagePriority::RED->value)
            ->call('close')
            ->assertSet('show', false)
            ->assertSet('newPriority', null);

        $this->assertSame(0, TriageAuditLog::count());
    }

    public function test_admision_no_puede_reclasificar(): void
    {
        Livewire::actingAs(User::factory()->admission()->create())
            ->test(ClinicalOverrideModal::class)
            ->assertForbidden();
    }

    public function test_la_pantalla_de_triage_incluye_el_modal_de_override(): void
    {
        $this->actingAs(User::factory()->triageNurse()->create())
            ->get('/triage')
            ->assertOk()
            ->assertSeeLivewire(ClinicalOverrideModal::class);
    }

    public function test_desde_el_ticket_se_puede_abrir_el_override_del_mismo_triage(): void
    {
        $triage = $this->triage();

        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(PrintTicket::class)
            ->dispatch('triage-saved', triageId: $triage->id)
            ->assertSee('Reclasificar prioridad')
            ->call('reclassify')
            ->assertSet('show', false)
            ->assertDispatched('open-clinical-override', triageId: $triage->id);
    }

    public function test_tras_registrar_el_triage_se_ofrece_reclasificar_y_se_refleja_el_override(): void
    {
        $paciente = Patient::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Villalba']);

        $pantalla = Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(EvaluatePatient::class)
            ->call('selectPatient', $paciente->id)
            ->set('symptoms', 'Control de rutina')
            ->call('save')
            ->assertSee('Reclasificar prioridad');

        $triage = Triage::query()->latest('id')->firstOrFail();

        $pantalla->call('openOverride')
            ->assertDispatched('open-clinical-override', triageId: $triage->id);

        $triage->overridePriority(TriagePriority::RED, '', User::factory()->triageNurse()->create(), true);

        $pantalla->dispatch('triage-overridden', triageId: $triage->id)
            ->assertSee('reclasificado')
            ->assertSee(TriagePriority::RED->label());
    }
}
