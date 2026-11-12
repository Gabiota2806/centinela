<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TriagePriority;
use App\Models\TriageAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
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
            $this->fail('Se esperaba que TriageAuditLog::update() lanzara LogicException.');
        } catch (LogicException $exception) {
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
            $this->fail('Se esperaba que TriageAuditLog::delete() lanzara LogicException.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('inmutables', $exception->getMessage());
        }

        $this->assertDatabaseHas('triage_audit_logs', [
            'id' => $auditLog->id,
        ]);
    }
}
