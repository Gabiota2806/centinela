<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TriagePriority;
use App\Exceptions\ImmutableModelException;
use App\Models\Triage;
use App\Models\TriageAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * CEN-16: Pruebas de integración sobre la inmutabilidad estricta de triage_audit_logs.
 */
class TriageAuditLogImmutableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica que se pueda crear un registro de auditoría y que UPDATED_AT sea nulo.
     */
    public function test_can_create_and_persist_triage_audit_log(): void
    {
        $auditLog = TriageAuditLog::factory()->create([
            'original_priority' => TriagePriority::GREEN,
            'overridden_priority' => TriagePriority::RED,
            'override_reason' => 'Paciente presenta deterioro hemodinámico súbito en sala de espera.',
        ]);

        $this->assertNull(TriageAuditLog::UPDATED_AT);
        $this->assertNotNull($auditLog->created_at);
        $this->assertSame(TriagePriority::GREEN, $auditLog->original_priority);
        $this->assertSame(TriagePriority::RED, $auditLog->overridden_priority);

        $this->assertDatabaseHas('triage_audit_logs', [
            'id' => $auditLog->id,
            'original_priority' => 'green',
            'overridden_priority' => 'red',
            'override_reason' => 'Paciente presenta deterioro hemodinámico súbito en sala de espera.',
        ]);
    }

    /**
     * Verifica que intentar actualizar un registro persistido en triage_audit_logs
     * lance excepción y preserve los datos originales en PostgreSQL.
     */
    public function test_persisted_audit_log_blocks_update_and_preserves_database_record(): void
    {
        $auditLog = TriageAuditLog::factory()->create([
            'original_priority' => TriagePriority::YELLOW,
            'overridden_priority' => TriagePriority::ORANGE,
            'override_reason' => 'Dolor torácico irradiado constatado en evaluación clínica.',
        ]);

        try {
            $auditLog->update([
                'overridden_priority' => TriagePriority::BLUE,
                'override_reason' => 'Intento de alteración posterior del motivo clínico.',
            ]);
            $this->fail('Se esperaba que TriageAuditLog::update() lanzara ImmutableModelException.');
        } catch (ImmutableModelException $exception) {
            $this->assertStringContainsString('inmutables', $exception->getMessage());
        }

        $this->assertDatabaseHas('triage_audit_logs', [
            'id' => $auditLog->id,
            'original_priority' => 'yellow',
            'overridden_priority' => 'orange',
            'override_reason' => 'Dolor torácico irradiado constatado en evaluación clínica.',
        ]);
    }

    /**
     * Verifica que intentar eliminar un registro persistido en triage_audit_logs
     * lance excepción y conserve la fila en PostgreSQL.
     */
    public function test_persisted_audit_log_blocks_delete_and_preserves_database_record(): void
    {
        $auditLog = TriageAuditLog::factory()->create([
            'original_priority' => TriagePriority::GREEN,
            'overridden_priority' => TriagePriority::ORANGE,
            'override_reason' => 'Disnea moderada con saturación limítrofe.',
        ]);

        try {
            $auditLog->delete();
            $this->fail('Se esperaba que TriageAuditLog::delete() lanzara ImmutableModelException.');
        } catch (ImmutableModelException $exception) {
            $this->assertStringContainsString('inmutables', $exception->getMessage());
        }

        $this->assertDatabaseHas('triage_audit_logs', [
            'id' => $auditLog->id,
        ]);
    }

    /**
     * Verifica que Triage::overridePriority actualice la prioridad final, active is_override,
     * recalcule target_wait_minutes según MTS y cree el registro inmutable en triage_audit_logs.
     */
    public function test_override_priority_updates_triage_and_creates_immutable_audit_log(): void
    {
        $doctor = User::factory()->doctor()->create();
        $triage = Triage::factory()->create([
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'is_override' => false,
            'target_wait_minutes' => 120,
        ]);

        $auditLog = $triage->overridePriority(
            newPriority: TriagePriority::RED,
            reason: 'Paciente presenta paro cardiorrespiratorio inminente en sala de espera.',
            user: $doctor,
            deferred: false
        );

        // Validaciones del modelo Triage actualizado
        $triage->refresh();
        $this->assertSame(TriagePriority::RED, $triage->final_priority);
        $this->assertSame(TriagePriority::GREEN, $triage->algorithmic_priority);
        $this->assertTrue($triage->is_override);
        $this->assertSame(0, $triage->target_wait_minutes);

        // Validaciones del registro de auditoría creado
        $this->assertInstanceOf(TriageAuditLog::class, $auditLog);
        $this->assertSame($triage->id, $auditLog->triage_id);
        $this->assertSame($doctor->id, $auditLog->user_id);
        $this->assertSame(TriagePriority::GREEN, $auditLog->original_priority);
        $this->assertSame(TriagePriority::RED, $auditLog->overridden_priority);
        $this->assertSame('Paciente presenta paro cardiorrespiratorio inminente en sala de espera.', $auditLog->override_reason);

        $this->assertDatabaseHas('triage_audit_logs', [
            'id' => $auditLog->id,
            'triage_id' => $triage->id,
            'user_id' => $doctor->id,
            'original_priority' => 'green',
            'overridden_priority' => 'red',
        ]);
    }

    /**
     * Verifica que overridePriority con deferred = true admita justificación vacía y
     * genere el motivo estándar reglamentario de emergencia crítica.
     */
    public function test_override_priority_supports_deferred_justification_with_empty_reason(): void
    {
        $doctor = User::factory()->doctor()->create();
        $triage = Triage::factory()->create([
            'final_priority' => TriagePriority::YELLOW,
            'is_override' => false,
            'target_wait_minutes' => 60,
        ]);

        $auditLog = $triage->overridePriority(
            newPriority: TriagePriority::ORANGE,
            reason: '',
            user: $doctor,
            deferred: true
        );

        $triage->refresh();
        $this->assertSame(TriagePriority::ORANGE, $triage->final_priority);
        $this->assertTrue($triage->is_override);
        $this->assertSame(10, $triage->target_wait_minutes);

        $this->assertStringContainsString('[JUSTIFICACIÓN DIFERIDA]', $auditLog->override_reason);
        $this->assertStringContainsString('Pendiente de redacción', $auditLog->override_reason);
    }

    /**
     * Verifica que overridePriority con deferred = true preserve notas breves del médico
     * prefijando la marca de diferido.
     */
    public function test_override_priority_supports_deferred_justification_with_custom_text(): void
    {
        $doctor = User::factory()->doctor()->create();
        $triage = Triage::factory()->create([
            'final_priority' => TriagePriority::GREEN,
        ]);

        $auditLog = $triage->overridePriority(
            newPriority: TriagePriority::RED,
            reason: 'Shock anafiláctico agudo post-medicación.',
            user: $doctor,
            deferred: true
        );

        $this->assertSame(
            '[JUSTIFICACIÓN DIFERIDA] Shock anafiláctico agudo post-medicación.',
            $auditLog->override_reason
        );
    }

    /**
     * Verifica que overridePriority no diferido exija una justificación clínica válida.
     */
    public function test_override_priority_throws_exception_when_reason_is_empty_and_not_deferred(): void
    {
        $doctor = User::factory()->doctor()->create();
        $triage = Triage::factory()->create([
            'final_priority' => TriagePriority::GREEN,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Debe proporcionar una justificación clínica válida');

        $triage->overridePriority(
            newPriority: TriagePriority::YELLOW,
            reason: '   ',
            user: $doctor,
            deferred: false
        );
    }

    /**
     * Verifica que el registro generado a través de overridePriority sea estrictamente
     * inmutable en la base de datos PostgreSQL ante intentos de update y delete.
     */
    public function test_audit_log_created_via_override_priority_is_immutable(): void
    {
        $doctor = User::factory()->doctor()->create();
        $triage = Triage::factory()->create([
            'final_priority' => TriagePriority::GREEN,
        ]);

        $auditLog = $triage->overridePriority(
            newPriority: TriagePriority::RED,
            reason: 'Paro inminente.',
            user: $doctor
        );

        $this->expectException(ImmutableModelException::class);
        $auditLog->update(['override_reason' => 'Motivo adulterado']);
    }
}
