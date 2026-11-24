<?php

declare(strict_types=1);

namespace Tests\Feature\Triage;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Livewire\Triage\EvaluatePatient;
use App\Livewire\Triage\PrintTicket;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\TriageAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_pestana_en_espera_ordena_por_mts_muestra_posicion_global_y_badge_llamado(): void
    {
        $nurse = User::factory()->triageNurse()->create();

        $pacienteRojo = Patient::factory()->create(['first_name' => 'Elena', 'last_name' => 'Gómez', 'dni' => '21111111']);
        $pacienteVerde = Patient::factory()->create(['first_name' => 'Bruno', 'last_name' => 'Sosa', 'dni' => '22222222']);
        $pacienteAzul = Patient::factory()->create(['first_name' => 'Carla', 'last_name' => 'Paz', 'dni' => '23333333']);
        $pacienteLlamado = Patient::factory()->create(['first_name' => 'Diego', 'last_name' => 'Vera', 'dni' => '24444444']);
        $pacienteCompletado = Patient::factory()->create(['first_name' => 'Laura', 'last_name' => 'Maidana', 'dni' => '25555555']);

        $triageAzul = Triage::factory()->create([
            'patient_id' => $pacienteAzul->id,
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::BLUE,
            'final_priority' => TriagePriority::BLUE,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes(30),
        ]);

        $triageVerde = Triage::factory()->create([
            'patient_id' => $pacienteVerde->id,
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes(20),
        ]);

        $triageRojo = Triage::factory()->create([
            'patient_id' => $pacienteRojo->id,
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::RED,
            'final_priority' => TriagePriority::RED,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes(10),
        ]);

        $triageLlamado = Triage::factory()->create([
            'patient_id' => $pacienteLlamado->id,
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::ORANGE,
            'final_priority' => TriagePriority::ORANGE,
            'status' => TriageStatus::CALLED,
            'created_at' => now()->subMinutes(25),
        ]);

        Triage::factory()->create([
            'patient_id' => $pacienteCompletado->id,
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::YELLOW,
            'final_priority' => TriagePriority::YELLOW,
            'status' => TriageStatus::COMPLETED,
        ]);

        $component = Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->assertSet('activeTab', 'waiting')
            ->assertSeeInOrder(['Diego Vera', 'Elena Gómez', 'Bruno Sosa', 'Carla Paz'])
            ->assertSee('Llamado')
            ->assertSee('#1')
            ->assertSee('#2')
            ->assertSee('#3')
            ->assertSee(PrintTicket::ticketNumber($triageRojo->id))
            ->assertDontSee('Laura Maidana');

        /** @var Collection<int, Triage> $waitingTriages */
        $waitingTriages = $component->viewData('waitingTriages');
        $this->assertSame(
            [$triageLlamado->id, $triageRojo->id, $triageVerde->id, $triageAzul->id],
            $waitingTriages->pluck('id')->all()
        );
        $this->assertTrue($waitingTriages->first()->relationLoaded('patient'));
        $this->assertTrue($waitingTriages->first()->patient->relationLoaded('neighborhood'));
        $this->assertTrue($waitingTriages->first()->relationLoaded('user'));

        $positions = $component->viewData('queuePositions');
        $this->assertSame(1, $positions[$triageRojo->id]);
        $this->assertSame(2, $positions[$triageVerde->id]);
        $this->assertSame(3, $positions[$triageAzul->id]);
        $this->assertArrayNotHasKey($triageLlamado->id, $positions);
    }

    public function test_buscador_por_dni_y_nombre_filtra_en_espera_preservando_posicion_real_y_filtra_historial(): void
    {
        $nurse = User::factory()->triageNurse()->create();

        $p1 = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Torres', 'dni' => '31000001']);
        $p2 = Patient::factory()->create(['first_name' => 'Luis', 'last_name' => 'Acosta', 'dni' => '32000002']);
        $p3 = Patient::factory()->create(['first_name' => 'Sofía', 'last_name' => 'Benítez', 'dni' => '33000003']);
        $pHist = Patient::factory()->create(['first_name' => 'Ramón', 'last_name' => 'Galeano', 'dni' => '34000004']);

        Triage::factory()->create([
            'patient_id' => $p1->id,
            'algorithmic_priority' => TriagePriority::RED,
            'final_priority' => TriagePriority::RED,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes(15),
        ]);
        Triage::factory()->create([
            'patient_id' => $p2->id,
            'algorithmic_priority' => TriagePriority::YELLOW,
            'final_priority' => TriagePriority::YELLOW,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes(10),
        ]);
        $t3 = Triage::factory()->create([
            'patient_id' => $p3->id,
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes(5),
        ]);
        Triage::factory()->create([
            'patient_id' => $pHist->id,
            'algorithmic_priority' => TriagePriority::BLUE,
            'final_priority' => TriagePriority::BLUE,
            'status' => TriageStatus::COMPLETED,
        ]);

        // Filtra por DNI del paciente en posición #3: conserva su #3 global
        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->set('searchTriage', '33000003')
            ->assertSee('Sofía Benítez')
            ->assertSee(PrintTicket::ticketNumber($t3->id))
            ->assertSee('#3')
            ->assertDontSee('Ana Torres')
            ->assertDontSee('Luis Acosta')
            ->call('setTab', 'history')
            ->set('searchTriage', 'Galeano')
            ->assertSee('Ramón Galeano')
            ->assertDontSee('Sofía Benítez');
    }

    public function test_pestana_historial_muestra_en_consulta_completados_y_cancelados_con_eager_loading(): void
    {
        $doctor = User::factory()->doctor()->create();

        $pEnConsulta = Patient::factory()->create(['first_name' => 'Mario', 'last_name' => 'Ibarra', 'dni' => '41000001']);
        $pCompletado = Patient::factory()->create(['first_name' => 'Lucía', 'last_name' => 'Peralta', 'dni' => '42000002']);
        $pCancelado = Patient::factory()->create(['first_name' => 'Oscar', 'last_name' => 'Duarte', 'dni' => '43000003']);
        $pPendiente = Patient::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Romero', 'dni' => '44000004']);

        Triage::factory()->create(['patient_id' => $pEnConsulta->id, 'status' => TriageStatus::IN_CONSULTATION]);
        Triage::factory()->create(['patient_id' => $pCompletado->id, 'status' => TriageStatus::COMPLETED]);
        Triage::factory()->create(['patient_id' => $pCancelado->id, 'status' => TriageStatus::CANCELLED]);
        Triage::factory()->create(['patient_id' => $pPendiente->id, 'status' => TriageStatus::PENDING_CONSULTATION]);

        $component = Livewire::actingAs($doctor)
            ->test(EvaluatePatient::class)
            ->call('setTab', 'history')
            ->assertSet('activeTab', 'history')
            ->assertSee('Mario Ibarra')
            ->assertSee('Lucía Peralta')
            ->assertSee('Oscar Duarte')
            ->assertSee('En consulta')
            ->assertSee('Completado')
            ->assertSee('Cancelado')
            ->assertDontSee('Nadia Romero');

        $historyTriages = $component->viewData('historyTriages');
        $this->assertCount(3, $historyTriages);
        $this->assertTrue($historyTriages->first()->relationLoaded('patient'));
        $this->assertTrue($historyTriages->first()->patient->relationLoaded('neighborhood'));
        $this->assertTrue($historyTriages->first()->relationLoaded('user'));
    }

    public function test_editar_triage_pendiente_con_cambio_de_prioridad_recalcula_mts_y_registra_auditoria_inmutable(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create(['first_name' => 'Marta', 'last_name' => 'Rodríguez', 'dni' => '23456789']);

        $triage = Triage::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $nurse->id,
            'symptoms' => 'Febrícula leve de 6 horas.',
            'vital_signs' => [
                'temperature' => 37.9,
                'systolic_bp' => 120,
                'diastolic_bp' => 80,
                'heart_rate' => 78,
                'respiratory_rate' => 16,
                'spo2' => 98,
                'discriminators' => ['mild_pain'],
                'calculated_discriminator' => 'Fiebre < 38.5°C sin alteración hemodinámica',
            ],
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'is_override' => false,
            'target_wait_minutes' => 120,
            'status' => TriageStatus::PENDING_CONSULTATION,
        ]);

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->call('startEditing', $triage->id)
            ->assertSet('editingTriageId', $triage->id)
            ->assertSet('patientId', $patient->id)
            ->assertSet('temperature', 37.9)
            ->assertSet('spo2', 98)
            ->assertSet('discriminators.mild_pain', true)
            ->assertSet('priorityValue', 'green')
            ->set('spo2', 82)
            ->set('symptoms', 'Desaturación súbita y dificultad respiratoria en sala de espera.')
            ->assertSet('priorityValue', 'red')
            ->assertSet('targetWaitMinutes', 0)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editingTriageId', null)
            ->assertSet('savedSuccessfully', true)
            ->assertDispatched('triage-updated', triageId: $triage->id)
            ->assertSee('auditoría registrada');

        $triage->refresh();
        $this->assertSame(TriagePriority::RED, $triage->algorithmic_priority);
        $this->assertSame(TriagePriority::RED, $triage->final_priority);
        $this->assertSame(0, $triage->target_wait_minutes);
        $this->assertSame(82, $triage->vital_signs['spo2']);
        $this->assertSame('Desaturación súbita y dificultad respiratoria en sala de espera.', $triage->symptoms);

        $auditLog = TriageAuditLog::where('triage_id', $triage->id)->sole();
        $this->assertSame($nurse->id, $auditLog->user_id);
        $this->assertSame(TriagePriority::GREEN, $auditLog->original_priority);
        $this->assertSame(TriagePriority::RED, $auditLog->overridden_priority);
        $this->assertStringContainsString('RE-EVALUACIÓN DE TRIAGE', $auditLog->override_reason);
    }

    public function test_editar_triage_pendiente_sin_cambio_de_prioridad_actualiza_datos_sin_duplicar_auditoria(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create();

        $triage = Triage::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $nurse->id,
            'symptoms' => 'Control general inicial.',
            'vital_signs' => [
                'temperature' => 36.5,
                'systolic_bp' => 118,
                'diastolic_bp' => 76,
                'heart_rate' => 72,
                'respiratory_rate' => 15,
                'spo2' => 99,
                'discriminators' => [],
            ],
            'algorithmic_priority' => TriagePriority::BLUE,
            'final_priority' => TriagePriority::BLUE,
            'target_wait_minutes' => 240,
            'status' => TriageStatus::PENDING_CONSULTATION,
        ]);

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->call('startEditing', $triage->id)
            ->set('temperature', 36.8)
            ->set('symptoms', 'Control periódico y renovación de receta crónica.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('triage-updated', triageId: $triage->id)
            ->assertSee('sin cambios en prioridad');

        $triage->refresh();
        $this->assertSame(TriagePriority::BLUE, $triage->final_priority);
        $this->assertSame(36.8, (float) $triage->vital_signs['temperature']);
        $this->assertSame('Control periódico y renovación de receta crónica.', $triage->symptoms);
        $this->assertSame(0, TriageAuditLog::where('triage_id', $triage->id)->count());
    }

    /**
     * @return array<string, array{TriageStatus}>
     */
    public static function nonEditableStatusesProvider(): array
    {
        return [
            'llamado a consultorio' => [TriageStatus::CALLED],
            'en consulta' => [TriageStatus::IN_CONSULTATION],
            'completado' => [TriageStatus::COMPLETED],
            'cancelado' => [TriageStatus::CANCELLED],
        ];
    }

    #[DataProvider('nonEditableStatusesProvider')]
    public function test_no_permite_editar_triage_que_no_esta_en_espera_de_consulta(TriageStatus $status): void
    {
        $nurse = User::factory()->triageNurse()->create();

        $triage = Triage::factory()->create([
            'symptoms' => 'Síntoma original inmutable',
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'status' => $status,
        ]);

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->call('startEditing', $triage->id)
            ->assertSet('editingTriageId', null)
            ->assertHasErrors(['editing']);

        $this->expectException(InvalidArgumentException::class);

        try {
            $triage->updateEvaluation(['spo2' => 80], 'Intento de edición bloqueado', [], $nurse);
        } finally {
            $triage->refresh();
            $this->assertSame('Síntoma original inmutable', $triage->symptoms);
            $this->assertSame(TriagePriority::GREEN, $triage->final_priority);
            $this->assertSame(0, TriageAuditLog::where('triage_id', $triage->id)->count());
        }
    }

    public function test_acciones_de_fila_en_espera_emiten_eventos_de_ticket_y_clinical_override(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $triage = Triage::factory()->create([
            'status' => TriageStatus::PENDING_CONSULTATION,
        ]);

        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->call('showTicketFor', $triage->id)
            ->assertDispatched('triage-saved', triageId: $triage->id)
            ->call('openOverrideFor', $triage->id)
            ->assertDispatched('open-clinical-override', triageId: $triage->id);
    }

    public function test_signos_vitales_reservan_padding_derecho_con_pointer_events_none_tokens_danger_y_wire_target_save(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $patient = Patient::factory()->create();

        $component = Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class, ['patient' => $patient])
            ->assertSeeHtml('pr-9')
            ->assertSeeHtml('pr-12')
            ->assertSeeHtml('pr-14')
            ->assertSeeHtml('pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted')
            ->assertSeeHtml('wire:target="save"')
            ->assertSeeHtml('aria-describedby="vital-temp-error"')
            ->assertSeeHtml('aria-describedby="vital-pas-error"')
            ->assertSeeHtml('aria-describedby="vital-pad-error"')
            ->assertSeeHtml('aria-describedby="vital-hr-error"')
            ->assertSeeHtml('aria-describedby="vital-rr-error"')
            ->assertSeeHtml('aria-describedby="vital-spo2-error"')
            ->assertSeeHtml('aria-describedby="triage-symptoms-error"');

        $htmlSinError = $component->html();
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $htmlSinError);

        $component
            ->set('temperature', 55.0)
            ->set('spo2', 150)
            ->set('symptoms', '')
            ->call('save')
            ->assertHasErrors(['temperature', 'spo2', 'symptoms'])
            ->assertSeeHtml('border-danger')
            ->assertSeeHtml('text-danger')
            ->assertSeeHtml('aria-invalid="true"')
            ->assertDontSeeHtml('text-red-600')
            ->assertDontSeeHtml('border-red-200');
    }
}
