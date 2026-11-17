<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Exceptions\ImmutableModelException;
use App\Livewire\Triage\ClinicalOverrideModal;
use App\Livewire\Triage\EvaluatePatient;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\TriageAuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CEN-41 [T3.8]: Testing de casos de discrepancia y reordenamiento de cola por override clínico.
 *
 * Valida:
 * 1. Reordenamiento inmediato en la cola médica cuando un paciente Verde pasa a Rojo por Clinical Override.
 * 2. Trazabilidad de discrepancias clínicas (escalado, de-escalado, cadena de múltiples overrides y desempate FIFO).
 * 3. Inmutabilidad estricta de la bitácora médico-legal triage_audit_logs.
 * 4. Integridad referencial de claves foráneas en PostgreSQL (restrictOnDelete en user_id y cascadeOnDelete en triage_id).
 *
 * Referencias:
 * - docs/specs/triage/spec.md (RF-TRIAGE-10, RF-TRIAGE-11)
 * - docs/backlog.md §CEN-41 [T3.8]
 */
class ClinicalOverrideAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Escenario principal CEN-41:
     * Verifica que un paciente con triage Verde reclasificado a Rojo pase a encabezar
     * la cola médica inmediatamente por encima de pacientes Naranja, Amarillo, Verde y Azul.
     */
    public function test_green_triage_overridden_to_red_immediately_heads_medical_queue(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $doctor = User::factory()->doctor()->create();

        $triageBlue = Triage::factory()->create([
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::BLUE,
            'final_priority' => TriagePriority::BLUE,
            'target_wait_minutes' => 240,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => Carbon::now()->subMinutes(40),
        ]);

        $triageOrange = Triage::factory()->create([
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::ORANGE,
            'final_priority' => TriagePriority::ORANGE,
            'target_wait_minutes' => 10,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        $triageYellow = Triage::factory()->create([
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::YELLOW,
            'final_priority' => TriagePriority::YELLOW,
            'target_wait_minutes' => 60,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => Carbon::now()->subMinutes(20),
        ]);

        $triageGreen = Triage::factory()->create([
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'is_override' => false,
            'target_wait_minutes' => 120,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => Carbon::now()->subMinutes(5),
        ]);

        // Antes del override: Naranja -> Amarillo -> Verde -> Azul
        $initialQueueIds = Triage::medicalQueue()->pluck('id')->all();
        $this->assertSame(
            [$triageOrange->id, $triageYellow->id, $triageGreen->id, $triageBlue->id],
            $initialQueueIds
        );

        // El médico aplica Clinical Override de Verde a Rojo por descompensación aguda
        $auditLog = $triageGreen->overridePriority(
            newPriority: TriagePriority::RED,
            reason: 'Paciente en sala de espera presenta síncope e hipoperfusión periférica aguda.',
            user: $doctor,
            deferred: false
        );

        // Después del override: el paciente reclasificado a Rojo encabeza inmediatamente la cola
        $reorderedQueueIds = Triage::medicalQueue()->pluck('id')->all();
        $this->assertSame(
            [$triageGreen->id, $triageOrange->id, $triageYellow->id, $triageBlue->id],
            $reorderedQueueIds
        );

        $headOfQueue = Triage::medicalQueue()->firstOrFail();
        $this->assertSame($triageGreen->id, $headOfQueue->id);
        $this->assertSame(TriagePriority::GREEN, $headOfQueue->algorithmic_priority);
        $this->assertSame(TriagePriority::RED, $headOfQueue->final_priority);
        $this->assertTrue($headOfQueue->is_override);
        $this->assertSame(0, $headOfQueue->target_wait_minutes);

        $this->assertSame(TriagePriority::GREEN, $auditLog->original_priority);
        $this->assertSame(TriagePriority::RED, $auditLog->overridden_priority);
    }

    /**
     * Verifica el flujo completo desde Livewire (EvaluatePatient + ClinicalOverrideModal con
     * justificación diferida en 1 clic): paciente evaluado como Verde pasa a Rojo y encabeza la cola.
     */
    public function test_emergency_deferred_override_via_livewire_modal_reorders_queue_to_top(): void
    {
        $nurse = User::factory()->triageNurse()->create();

        $waitingOrange = Triage::factory()->create([
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::ORANGE,
            'final_priority' => TriagePriority::ORANGE,
            'target_wait_minutes' => 10,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => Carbon::now()->subMinutes(15),
        ]);

        $patient = Patient::factory()->create([
            'first_name' => 'Ramón',
            'last_name' => 'Gómez',
        ]);

        // 1. Enfermería evalúa al paciente con febrícula leve (clasificación algorítmica VERDE)
        Livewire::actingAs($nurse)
            ->test(EvaluatePatient::class)
            ->call('selectPatient', $patient->id)
            ->set('temperature', 38.0)
            ->set('systolic_bp', 120)
            ->set('diastolic_bp', 80)
            ->set('heart_rate', 78)
            ->set('respiratory_rate', 16)
            ->set('spo2', 98)
            ->set('symptoms', 'Febrícula de 12 horas sin signos de alarma iniciales.')
            ->call('save')
            ->assertHasNoErrors();

        $greenTriage = Triage::where('patient_id', $patient->id)->sole();
        $this->assertSame(TriagePriority::GREEN, $greenTriage->algorithmic_priority);
        $this->assertSame(TriagePriority::GREEN, $greenTriage->final_priority);

        // El paciente Naranja sigue primero antes del override
        $this->assertSame(
            [$waitingOrange->id, $greenTriage->id],
            Triage::medicalQueue()->pluck('id')->all()
        );

        // 2. El paciente convulsiona en sala de espera -> Override de emergencia en 1 clic (diferido) a ROJO
        Livewire::actingAs($nurse)
            ->test(ClinicalOverrideModal::class)
            ->dispatch('open-clinical-override', triageId: $greenTriage->id)
            ->set('newPriority', TriagePriority::RED->value)
            ->set('deferred', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('triage-overridden', triageId: $greenTriage->id);

        // 3. El paciente reclasificado a Rojo encabeza inmediatamente la cola
        $this->assertSame(
            [$greenTriage->id, $waitingOrange->id],
            Triage::medicalQueue()->pluck('id')->all()
        );

        $auditLog = TriageAuditLog::where('triage_id', $greenTriage->id)->sole();
        $this->assertSame(TriagePriority::GREEN, $auditLog->original_priority);
        $this->assertSame(TriagePriority::RED, $auditLog->overridden_priority);
        $this->assertStringContainsString('[JUSTIFICACIÓN DIFERIDA]', $auditLog->override_reason);
    }

    /**
     * Verifica casos de discrepancia por sobre-clasificación (de-escalado / downgrade)
     * y respeto del criterio FIFO (created_at ASC) dentro del mismo nivel de prioridad MTS.
     */
    public function test_downgrade_override_reorders_patient_behind_higher_and_earlier_same_priority(): void
    {
        $doctor = User::factory()->doctor()->create();

        $triageOrange = Triage::factory()->create([
            'algorithmic_priority' => TriagePriority::ORANGE,
            'final_priority' => TriagePriority::ORANGE,
            'target_wait_minutes' => 10,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => Carbon::now()->subMinutes(5),
        ]);

        $triageYellow = Triage::factory()->create([
            'algorithmic_priority' => TriagePriority::YELLOW,
            'final_priority' => TriagePriority::YELLOW,
            'target_wait_minutes' => 60,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => Carbon::now()->subMinutes(25),
        ]);

        $earlierGreen = Triage::factory()->create([
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'target_wait_minutes' => 120,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        $triageBlue = Triage::factory()->create([
            'algorithmic_priority' => TriagePriority::BLUE,
            'final_priority' => TriagePriority::BLUE,
            'target_wait_minutes' => 240,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => Carbon::now()->subMinutes(60),
        ]);

        // Inicialmente el paciente Naranja encabeza la cola
        $this->assertSame(
            [$triageOrange->id, $triageYellow->id, $earlierGreen->id, $triageBlue->id],
            Triage::medicalQueue()->pluck('id')->all()
        );

        // El médico constata que la oximetría baja era artefacto por esmalte de uñas y reclasifica a VERDE
        $triageOrange->overridePriority(
            newPriority: TriagePriority::GREEN,
            reason: 'Artefacto de lectura en oxímetro de pulso; paciente eupneico con SpO2 real 98%.',
            user: $doctor,
            deferred: false
        );

        // Ahora debe ubicarse detrás de Amarillo y detrás del Verde que ingresó 25 minutos antes (FIFO), pero antes del Azul
        $this->assertSame(
            [$triageYellow->id, $earlierGreen->id, $triageOrange->id, $triageBlue->id],
            Triage::medicalQueue()->pluck('id')->all()
        );

        $triageOrange->refresh();
        $this->assertSame(TriagePriority::ORANGE, $triageOrange->algorithmic_priority);
        $this->assertSame(TriagePriority::GREEN, $triageOrange->final_priority);
        $this->assertSame(120, $triageOrange->target_wait_minutes);
        $this->assertTrue($triageOrange->is_override);
    }

    /**
     * Verifica la trazabilidad de múltiples overrides sucesivos sobre un mismo triage:
     * algorithmic_priority conserva el cálculo original mientras que cada audit log
     * encadena la transición exacta (Verde -> Amarillo -> Rojo).
     */
    public function test_sequential_overrides_preserve_initial_algorithmic_priority_and_chain_audit_history(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $doctor = User::factory()->doctor()->create();

        $triage = Triage::factory()->create([
            'user_id' => $nurse->id,
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'is_override' => false,
            'target_wait_minutes' => 120,
        ]);

        // 1° Override (Enfermería): Verde -> Amarillo
        $firstLog = $triage->overridePriority(
            newPriority: TriagePriority::YELLOW,
            reason: 'Aumento de curva térmica a 39.2°C con cefalea retroocular intensa.',
            user: $nurse,
            deferred: false
        );

        // 2° Override (Médico): Amarillo -> Rojo
        $secondLog = $triage->overridePriority(
            newPriority: TriagePriority::RED,
            reason: 'Hipotensión severa y signos de choque por dengue grave.',
            user: $doctor,
            deferred: false
        );

        $triage->refresh();
        $this->assertSame(TriagePriority::GREEN, $triage->algorithmic_priority);
        $this->assertSame(TriagePriority::RED, $triage->final_priority);
        $this->assertSame(0, $triage->target_wait_minutes);
        $this->assertTrue($triage->is_override);
        $this->assertCount(2, $triage->auditLogs);

        $this->assertSame(TriagePriority::GREEN, $firstLog->original_priority);
        $this->assertSame(TriagePriority::YELLOW, $firstLog->overridden_priority);
        $this->assertSame($nurse->id, $firstLog->user_id);

        $this->assertSame(TriagePriority::YELLOW, $secondLog->original_priority);
        $this->assertSame(TriagePriority::RED, $secondLog->overridden_priority);
        $this->assertSame($doctor->id, $secondLog->user_id);
    }

    /**
     * Verifica que la cola médica (Triage::medicalQueue) excluya pacientes que ya están
     * en consulta, completados o cancelados, incluso si tienen prioridad Roja.
     */
    public function test_medical_queue_only_includes_pending_consultation_triages(): void
    {
        $inConsultationRed = Triage::factory()->create([
            'final_priority' => TriagePriority::RED,
            'status' => TriageStatus::IN_CONSULTATION,
        ]);

        $completedRed = Triage::factory()->create([
            'final_priority' => TriagePriority::RED,
            'status' => TriageStatus::COMPLETED,
        ]);

        $cancelledOrange = Triage::factory()->create([
            'final_priority' => TriagePriority::ORANGE,
            'status' => TriageStatus::CANCELLED,
        ]);

        $waitingGreen = Triage::factory()->create([
            'final_priority' => TriagePriority::GREEN,
            'status' => TriageStatus::PENDING_CONSULTATION,
        ]);

        $queueIds = Triage::medicalQueue()->pluck('id')->all();

        $this->assertSame([$waitingGreen->id], $queueIds);
        $this->assertNotContains($inConsultationRed->id, $queueIds);
        $this->assertNotContains($completedRed->id, $queueIds);
        $this->assertNotContains($cancelledOrange->id, $queueIds);
    }

    /**
     * Verifica la inmutabilidad absoluta de triage_audit_logs ante cualquier intento de
     * actualización o borrado (update, save sobre atributo mutado, delete y destroy).
     */
    public function test_triage_audit_logs_enforce_strict_immutability_against_mutations_and_deletions(): void
    {
        $doctor = User::factory()->doctor()->create();
        $triage = Triage::factory()->create([
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
        ]);

        $auditLog = $triage->overridePriority(
            newPriority: TriagePriority::RED,
            reason: 'Deterioro hemodinámico agudo en guardia.',
            user: $doctor
        );

        $this->assertNull(TriageAuditLog::UPDATED_AT);

        // 1. Intento de update()
        try {
            $auditLog->update(['override_reason' => 'Motivo alterado ilegalmente']);
            $this->fail('TriageAuditLog::update() debía lanzar ImmutableModelException.');
        } catch (ImmutableModelException $e) {
            $this->assertStringContainsString('inmutables', $e->getMessage());
        }

        // 2. Intento de mutación directa de atributo + save()
        try {
            $auditLog->overridden_priority = TriagePriority::BLUE;
            $auditLog->save();
            $this->fail('TriageAuditLog::save() en instancia existente debía lanzar ImmutableModelException.');
        } catch (ImmutableModelException $e) {
            $this->assertStringContainsString('inmutables', $e->getMessage());
        }

        // 3. Intento de delete()
        try {
            $auditLog->delete();
            $this->fail('TriageAuditLog::delete() debía lanzar ImmutableModelException.');
        } catch (ImmutableModelException $e) {
            $this->assertStringContainsString('inmutables', $e->getMessage());
        }

        // 4. Intento de destroy()
        try {
            TriageAuditLog::destroy($auditLog->id);
            $this->fail('TriageAuditLog::destroy() debía lanzar ImmutableModelException.');
        } catch (ImmutableModelException $e) {
            $this->assertStringContainsString('inmutables', $e->getMessage());
        }

        // El registro permanece intacto en PostgreSQL
        $this->assertDatabaseHas('triage_audit_logs', [
            'id' => $auditLog->id,
            'triage_id' => $triage->id,
            'user_id' => $doctor->id,
            'original_priority' => 'green',
            'overridden_priority' => 'red',
            'override_reason' => 'Deterioro hemodinámico agudo en guardia.',
        ]);
    }

    /**
     * Verifica la integridad referencial de claves foráneas en PostgreSQL sobre triage_audit_logs:
     * - restrictOnDelete en user_id impide eliminar al profesional que firmó un override clínico.
     * - Rechazo de claves foráneas inexistentes (triage_id y user_id).
     * - cascadeOnDelete en triage_id mantiene consistencia referencial si se elimina el triage padre.
     */
    public function test_triage_audit_logs_foreign_key_constraints_integrity(): void
    {
        $doctor = User::factory()->doctor()->create();
        $triage = Triage::factory()->create([
            'algorithmic_priority' => TriagePriority::YELLOW,
            'final_priority' => TriagePriority::YELLOW,
        ]);

        $auditLog = $triage->overridePriority(
            newPriority: TriagePriority::RED,
            reason: 'Compromiso respiratorio agudo.',
            user: $doctor
        );

        // 1. restrictOnDelete sobre user_id: prohibido eliminar un usuario con auditorías firmadas
        try {
            DB::transaction(fn () => $doctor->delete());
            $this->fail('PostgreSQL debía bloquear la eliminación de un User referenciado en triage_audit_logs.');
        } catch (QueryException $e) {
            $this->assertDatabaseHas('users', ['id' => $doctor->id]);
            $this->assertDatabaseHas('triage_audit_logs', ['id' => $auditLog->id, 'user_id' => $doctor->id]);
        }

        // 2. Rechazo de triage_id inexistente
        try {
            DB::transaction(fn () => TriageAuditLog::create([
                'triage_id' => 999999,
                'user_id' => $doctor->id,
                'original_priority' => TriagePriority::GREEN,
                'overridden_priority' => TriagePriority::RED,
                'override_reason' => 'Intento con triage_id huérfano.',
            ]));
            $this->fail('PostgreSQL debía rechazar un triage_id inexistente.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('triage_audit_logs', [
                'override_reason' => 'Intento con triage_id huérfano.',
            ]);
        }

        // 3. Rechazo de user_id inexistente
        try {
            DB::transaction(fn () => TriageAuditLog::create([
                'triage_id' => $triage->id,
                'user_id' => 999999,
                'original_priority' => TriagePriority::GREEN,
                'overridden_priority' => TriagePriority::RED,
                'override_reason' => 'Intento con user_id huérfano.',
            ]));
            $this->fail('PostgreSQL debía rechazar un user_id inexistente.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('triage_audit_logs', [
                'override_reason' => 'Intento con user_id huérfano.',
            ]);
        }

        // 4. cascadeOnDelete en nivel de base de datos sobre triage_id al eliminar el triage padre
        $triageId = $triage->id;
        $auditLogId = $auditLog->id;
        $triage->delete();

        $this->assertDatabaseMissing('triages', ['id' => $triageId]);
        $this->assertDatabaseMissing('triage_audit_logs', ['id' => $auditLogId]);
    }
}
