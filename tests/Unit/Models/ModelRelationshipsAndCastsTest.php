<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\ConsultationStatus;
use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Models\Consultation;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\TriageAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;
use Tests\TestCase;

/**
 * Pruebas unitarias de modelos Eloquent, relaciones, casteo de tipos e inmutabilidad (CEN-14).
 */
class ModelRelationshipsAndCastsTest extends TestCase
{
    public function test_triage_priority_enum_values_and_helpers(): void
    {
        $this->assertSame('red', TriagePriority::RED->value);
        $this->assertSame(1, TriagePriority::RED->level());
        $this->assertSame(0, TriagePriority::RED->targetWaitMinutes());
        $this->assertSame('#DC2626', TriagePriority::RED->hexColor());

        $this->assertSame('orange', TriagePriority::ORANGE->value);
        $this->assertSame(2, TriagePriority::ORANGE->level());
        $this->assertSame(10, TriagePriority::ORANGE->targetWaitMinutes());

        $this->assertSame('yellow', TriagePriority::YELLOW->value);
        $this->assertSame(3, TriagePriority::YELLOW->level());
        $this->assertSame(60, TriagePriority::YELLOW->targetWaitMinutes());

        $this->assertSame('green', TriagePriority::GREEN->value);
        $this->assertSame(4, TriagePriority::GREEN->level());
        $this->assertSame(120, TriagePriority::GREEN->targetWaitMinutes());

        $this->assertSame('blue', TriagePriority::BLUE->value);
        $this->assertSame(5, TriagePriority::BLUE->level());
        $this->assertSame(240, TriagePriority::BLUE->targetWaitMinutes());
    }

    public function test_triage_status_enum_values_and_labels(): void
    {
        $this->assertSame('pending_consultation', TriageStatus::PENDING_CONSULTATION->value);
        $this->assertSame('in_consultation', TriageStatus::IN_CONSULTATION->value);
        $this->assertSame('completed', TriageStatus::COMPLETED->value);
        $this->assertSame('cancelled', TriageStatus::CANCELLED->value);
        $this->assertNotEmpty(TriageStatus::PENDING_CONSULTATION->label());
    }

    public function test_consultation_status_enum_values_and_labels(): void
    {
        $this->assertSame('in_progress', ConsultationStatus::IN_PROGRESS->value);
        $this->assertSame('completed', ConsultationStatus::COMPLETED->value);
        $this->assertSame('referred_sipec', ConsultationStatus::REFERRED_SIPEC->value);
        $this->assertNotEmpty(ConsultationStatus::REFERRED_SIPEC->label());
    }

    public function test_neighborhood_model_relationships(): void
    {
        $neighborhood = new Neighborhood();
        $this->assertInstanceOf(HasMany::class, $neighborhood->patients());
        $this->assertInstanceOf(Patient::class, $neighborhood->patients()->getRelated());
    }

    public function test_patient_model_relationships_and_accessors(): void
    {
        $patient = new Patient([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
        ]);

        $this->assertSame('Juan Pérez', $patient->full_name);
        $this->assertInstanceOf(BelongsTo::class, $patient->neighborhood());
        $this->assertInstanceOf(Neighborhood::class, $patient->neighborhood()->getRelated());
        $this->assertInstanceOf(HasMany::class, $patient->triages());
        $this->assertInstanceOf(Triage::class, $patient->triages()->getRelated());
    }

    public function test_triage_model_relationships_and_casts(): void
    {
        $triage = new Triage();

        $this->assertInstanceOf(BelongsTo::class, $triage->patient());
        $this->assertInstanceOf(Patient::class, $triage->patient()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $triage->user());
        $this->assertInstanceOf(User::class, $triage->user()->getRelated());

        $this->assertInstanceOf(HasMany::class, $triage->auditLogs());
        $this->assertInstanceOf(TriageAuditLog::class, $triage->auditLogs()->getRelated());

        $this->assertInstanceOf(HasOne::class, $triage->consultation());
        $this->assertInstanceOf(Consultation::class, $triage->consultation()->getRelated());

        $casts = $triage->getCasts();
        $this->assertSame('array', $casts['vital_signs']);
        $this->assertSame(TriagePriority::class, $casts['algorithmic_priority']);
        $this->assertSame(TriagePriority::class, $casts['final_priority']);
        $this->assertSame(TriageStatus::class, $casts['status']);
        $this->assertSame('boolean', $casts['is_override']);
        $this->assertSame('integer', $casts['target_wait_minutes']);
    }

    public function test_consultation_model_relationships_and_casts(): void
    {
        $consultation = new Consultation();

        $this->assertInstanceOf(BelongsTo::class, $consultation->triage());
        $this->assertInstanceOf(Triage::class, $consultation->triage()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $consultation->doctor());
        $this->assertInstanceOf(User::class, $consultation->doctor()->getRelated());
        $this->assertSame('doctor_id', $consultation->doctor()->getForeignKeyName());

        $casts = $consultation->getCasts();
        $this->assertSame(ConsultationStatus::class, $casts['status']);
        $this->assertSame('datetime', $casts['started_at']);
        $this->assertSame('datetime', $casts['completed_at']);
    }

    public function test_user_model_has_relationships_to_triage_and_consultations(): void
    {
        $user = new User();

        $this->assertInstanceOf(HasMany::class, $user->triages());
        $this->assertInstanceOf(Triage::class, $user->triages()->getRelated());

        $this->assertInstanceOf(HasMany::class, $user->consultations());
        $this->assertInstanceOf(Consultation::class, $user->consultations()->getRelated());
        $this->assertSame('doctor_id', $user->consultations()->getForeignKeyName());

        $this->assertInstanceOf(HasMany::class, $user->triageAuditLogs());
        $this->assertInstanceOf(TriageAuditLog::class, $user->triageAuditLogs()->getRelated());
    }

    public function test_triage_audit_log_model_relationships_and_casts(): void
    {
        $log = new TriageAuditLog();

        $this->assertInstanceOf(BelongsTo::class, $log->triage());
        $this->assertInstanceOf(Triage::class, $log->triage()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $log->user());
        $this->assertInstanceOf(User::class, $log->user()->getRelated());

        $this->assertNull(TriageAuditLog::UPDATED_AT);

        $casts = $log->getCasts();
        $this->assertSame(TriagePriority::class, $casts['original_priority']);
        $this->assertSame(TriagePriority::class, $casts['overridden_priority']);
    }

    public function test_triage_audit_log_throws_exception_on_update(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Los registros de auditoría de Clinical Override son estrictamente inmutables y no pueden modificarse.');

        $log = new TriageAuditLog();
        $log->exists = true; // Simular que ya está persistido
        $log->override_reason = 'Nueva justificación intentada';
        $log->save();
    }

    public function test_triage_audit_log_throws_exception_on_delete(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Los registros de auditoría de Clinical Override son estrictamente inmutables y no pueden eliminarse.');

        $log = new TriageAuditLog();
        $log->exists = true; // Simular que ya está persistido
        $log->delete();
    }
}
