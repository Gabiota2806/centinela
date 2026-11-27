<?php

declare(strict_types=1);

namespace Tests\Feature\Doctor;

use App\Enums\ConsultationStatus;
use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Livewire\Doctor\MedicalQueue;
use App\Livewire\Triage\ClinicalOverrideModal;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-42: panel médico con ordenamiento dinámico por MTS
 * (docs/specs/consultas/spec.md RF-CONS-01, 02, 04 y 06).
 */
class MedicalQueueTest extends TestCase
{
    use RefreshDatabase;

    private function enEspera(string $nombre, TriagePriority $prioridad, int $minutosAtras, TriageStatus $status = TriageStatus::PENDING_CONSULTATION): Triage
    {
        [$first, $last] = explode(' ', $nombre);

        return Triage::factory()->create([
            'patient_id' => Patient::factory()->create(['first_name' => $first, 'last_name' => $last])->id,
            'algorithmic_priority' => $prioridad,
            'final_priority' => $prioridad,
            'target_wait_minutes' => $prioridad->targetWaitMinutes(),
            'status' => $status,
            'created_at' => now()->subMinutes($minutosAtras),
        ]);
    }

    private function medico(): User
    {
        return User::factory()->doctor()->create();
    }

    public function test_la_ruta_consultas_muestra_la_cola_medica_y_el_modal_de_override(): void
    {
        $this->actingAs($this->medico())
            ->get('/consultas')
            ->assertOk()
            ->assertSeeLivewire(MedicalQueue::class)
            ->assertSeeLivewire(ClinicalOverrideModal::class);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function rolesYAcceso(): array
    {
        return [
            'admisión' => ['admission', 403],
            'enfermería de triage' => ['triageNurse', 403],
            'médico de guardia' => ['doctor', 200],
            'dirección' => ['director', 200],
        ];
    }

    #[DataProvider('rolesYAcceso')]
    public function test_solo_medicos_y_direccion_acceden_a_la_cola(string $rol, int $esperado): void
    {
        $this->actingAs(User::factory()->{$rol}()->create())
            ->get('/consultas')
            ->assertStatus($esperado);
    }

    public function test_el_componente_rechaza_a_enfermeria(): void
    {
        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(MedicalQueue::class)
            ->assertForbidden();
    }

    public function test_cada_accion_vuelve_a_verificar_el_permiso_del_usuario(): void
    {
        $triage = $this->enEspera('Marta Rodríguez', TriagePriority::YELLOW, 10);
        $cola = Livewire::actingAs($this->medico())->test(MedicalQueue::class);

        $this->actingAs(User::factory()->admission()->create());

        $cola->call('callPatient', $triage->id)->assertForbidden();
        $this->assertSame(TriageStatus::PENDING_CONSULTATION, $triage->refresh()->status);
    }

    public function test_la_cola_ordena_por_prioridad_mts_y_luego_por_llegada(): void
    {
        // Gherkin RF-CONS-01: Verde, Rojo y Amarillo ➔ #1 Rojo, #2 Amarillo, #3 Verde.
        $verde = $this->enEspera('Ana Verde', TriagePriority::GREEN, 50);
        $rojo = $this->enEspera('Raúl Rojo', TriagePriority::RED, 5);
        $amarillo = $this->enEspera('Alma Amarilla', TriagePriority::YELLOW, 20);
        $amarilloPrevio = $this->enEspera('Abel Amarillo', TriagePriority::YELLOW, 40);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSeeInOrder(['#1', 'Raúl Rojo', '#2', 'Abel Amarillo', '#3', 'Alma Amarilla', '#4', 'Ana Verde'])
            ->assertSee('TR-'.str_pad((string) $rojo->id, 5, '0', STR_PAD_LEFT))
            ->assertSee('TR-'.str_pad((string) $verde->id, 5, '0', STR_PAD_LEFT))
            ->assertSee('Llamar a consultorio');

        $this->assertNotNull($amarillo);
        $this->assertNotNull($amarilloPrevio);
    }

    public function test_cada_tarjeta_muestra_barrio_signos_vitales_y_tiempo_restante(): void
    {
        Carbon::setTestNow('2026-11-24 15:00:00');
        $triage = $this->enEspera('Marta Rodríguez', TriagePriority::YELLOW, 15);
        $triage->update(['vital_signs' => ['temperature' => 38.9, 'heart_rate' => 110, 'spo2' => 95,
            'systolic_bp' => 130, 'diastolic_bp' => 85, 'respiratory_rate' => 22]]);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee($triage->patient->neighborhood->name)
            ->assertSee('38.9')
            ->assertSee('110')
            ->assertSee('95')
            ->assertSee('Quedan 45 min')
            ->assertSee('Espera máx. 60 min');
    }

    public function test_la_espera_excedida_se_destaca_con_tokens_de_peligro(): void
    {
        Carbon::setTestNow('2026-11-24 15:00:00');
        $this->enEspera('Ana Verde', TriagePriority::GREEN, 150);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee('Excedido por 30 min')
            ->assertSeeHtml('data-overdue="true"');
    }

    public function test_las_esperas_largas_se_muestran_en_horas_y_dias(): void
    {
        $this->assertSame('45 min', MedicalQueue::formatMinutes(45));
        $this->assertSame('2 h 5 min', MedicalQueue::formatMinutes(125));
        $this->assertSame('7 d 21 h', MedicalQueue::formatMinutes(11393));

        Carbon::setTestNow('2026-11-24 15:00:00');
        $this->enEspera('Ana Verde', TriagePriority::GREEN, 245);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee('Excedido por 2 h 5 min');
    }

    public function test_un_rojo_se_muestra_como_atencion_inmediata(): void
    {
        $this->enEspera('Raúl Rojo', TriagePriority::RED, 0);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee('Atención inmediata');
    }

    public function test_la_cola_no_muestra_completados_ni_cancelados(): void
    {
        $this->enEspera('Carla Completa', TriagePriority::RED, 10, TriageStatus::COMPLETED);
        $this->enEspera('Ciro Cancelado', TriagePriority::RED, 10, TriageStatus::CANCELLED);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertDontSee('Carla Completa')
            ->assertDontSee('Ciro Cancelado')
            ->assertSee('No hay pacientes en espera');
    }

    public function test_llamar_a_consultorio_mueve_al_paciente_a_llamados(): void
    {
        $triage = $this->enEspera('Marta Rodríguez', TriagePriority::YELLOW, 10);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->call('callPatient', $triage->id)
            ->assertHasNoErrors()
            ->assertSee('Se llamó a Marta Rodríguez a consultorio')
            ->assertSee('Ingresar a consulta')
            ->assertSee('Volver a espera')
            ->assertSee('Cancelar / Ausente');

        $this->assertSame(TriageStatus::CALLED, $triage->refresh()->status);
    }

    public function test_volver_a_espera_devuelve_al_paciente_a_su_posicion(): void
    {
        // Nombres sin tilde: assertSeeInOrder desalinea offsets con texto multibyte previo.
        $primero = $this->enEspera('Mario Paz', TriagePriority::YELLOW, 30, TriageStatus::CALLED);
        $this->enEspera('Carlos Villalba', TriagePriority::YELLOW, 10);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->call('returnToQueue', $primero->id)
            ->assertSee('Mario Paz volvió a la espera')
            ->assertSeeInOrder(['waiting-'.$primero->id, '#1', 'Mario Paz', '#2', 'Carlos Villalba']);

        $this->assertSame(TriageStatus::PENDING_CONSULTATION, $primero->refresh()->status);
    }

    public function test_cancelar_por_ausente_saca_al_paciente_de_la_cola(): void
    {
        $triage = $this->enEspera('Marta Rodríguez', TriagePriority::YELLOW, 30, TriageStatus::CALLED);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->call('markAbsent', $triage->id)
            ->assertSee('Marta Rodríguez se marcó como ausente');

        $this->assertSame(TriageStatus::CANCELLED, $triage->refresh()->status);
    }

    public function test_ingresar_a_consulta_abre_la_consulta_del_medico(): void
    {
        $triage = $this->enEspera('Marta Rodríguez', TriagePriority::YELLOW, 30, TriageStatus::CALLED);
        $medico = $this->medico();

        Livewire::actingAs($medico)
            ->test(MedicalQueue::class)
            ->call('startConsultation', $triage->id)
            ->assertRedirect(route('consultations.show', $triage));

        $triage->refresh();
        $this->assertSame(TriageStatus::IN_CONSULTATION, $triage->status);
        $this->assertSame($medico->id, $triage->consultation->doctor_id);
        $this->assertSame(ConsultationStatus::IN_PROGRESS, $triage->consultation->status);
    }

    public function test_los_pacientes_en_consulta_enlazan_al_consultorio(): void
    {
        $triage = $this->enEspera('Marta Rodríguez', TriagePriority::YELLOW, 30, TriageStatus::CALLED);
        $triage->startConsultation($this->medico());

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee('Abrir consultorio')
            ->assertSeeHtml('href="'.route('consultations.show', $triage).'"');
    }

    public function test_los_horarios_de_dias_anteriores_muestran_la_fecha(): void
    {
        Carbon::setTestNow('2026-11-24 22:07:00');
        $triage = $this->enEspera('Carlos Villalba', TriagePriority::ORANGE, 30, TriageStatus::CALLED);
        $triage->startConsultation($this->medico());

        Carbon::setTestNow('2026-11-27 15:00:00');

        // 22:07 UTC del 24/11 = 19:07 hora local del CAPS.
        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee('desde 24/11 19:07');

        $this->enEspera('Ana Verde', TriagePriority::GREEN, 60 * 24 * 2);
        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee('llegó 25/11 12:00');

        Carbon::setTestNow('2026-11-24 23:00:00');
        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee('desde 19:07')
            ->assertDontSee('desde 24/11');
    }

    public function test_muestra_el_aviso_del_cierre_de_consulta_al_volver_a_la_cola(): void
    {
        session()->flash('queue-notice', 'Consulta de Marta Rodríguez cerrada: Sospecha de Dengue / Arbovirosis (Finalizada).');

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee('Consulta de Marta Rodríguez cerrada');
    }

    public function test_una_accion_invalida_muestra_el_error_sin_romper_la_pantalla(): void
    {
        $triage = $this->enEspera('Marta Rodríguez', TriagePriority::YELLOW, 30);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->call('markAbsent', $triage->id)
            ->assertSee('No se puede');

        $this->assertSame(TriageStatus::PENDING_CONSULTATION, $triage->refresh()->status);
    }

    public function test_desde_la_tarjeta_se_abre_el_clinical_override(): void
    {
        $triage = $this->enEspera('Marta Rodríguez', TriagePriority::GREEN, 30);

        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->assertSee('Reclasificar')
            ->call('reclassify', $triage->id)
            ->assertDispatched('open-clinical-override', triageId: $triage->id);
    }

    public function test_las_acciones_ignoran_triages_inexistentes(): void
    {
        Livewire::actingAs($this->medico())
            ->test(MedicalQueue::class)
            ->call('callPatient', 999999)
            ->assertSee('El paciente ya no está en la cola');
    }

    public function test_la_vista_no_usa_emojis(): void
    {
        $this->enEspera('Marta Rodríguez', TriagePriority::YELLOW, 30, TriageStatus::CALLED);
        $html = Livewire::actingAs($this->medico())->test(MedicalQueue::class)->html();

        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html);
    }
}
