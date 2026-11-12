<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pruebas de integración sobre las migraciones y esquema relacional de Centinela (CEN-14).
 */
class DatabaseMigrationsTest extends TestCase
{
    /**
     * Valida que las 5 tablas de la especificación existan en la base de datos.
     */
    public function test_all_cen14_tables_exist(): void
    {
        $tables = [
            'neighborhoods',
            'patients',
            'triages',
            'triage_audit_logs',
            'consultations',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "La tabla '{$table}' debe existir en la base de datos."
            );
        }
    }

    /**
     * Valida las columnas y configuración espacial de la tabla neighborhoods.
     */
    public function test_neighborhoods_table_structure_and_postgis_geometry(): void
    {
        $columns = ['id', 'name', 'geometry', 'risk_level', 'created_at', 'updated_at'];
        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('neighborhoods', $column),
                "La columna '{$column}' debe existir en 'neighborhoods'."
            );
        }

        // Verificar que la columna geometry esté registrada en PostGIS con SRID 4326
        $geomInfo = DB::selectOne("
            SELECT f_geometry_column, srid, type
            FROM geometry_columns
            WHERE f_table_name = 'neighborhoods'
        ");

        $this->assertNotNull($geomInfo, "La columna 'geometry' debe estar registrada en geometry_columns de PostGIS.");
        $this->assertEquals(4326, $geomInfo->srid, "El SRID de la geometría de neighborhoods debe ser 4326.");

        // Verificar que exista el índice espacial GIST sobre geometry
        $indexInfo = DB::selectOne("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'neighborhoods'
              AND indexdef LIKE '%USING gist%geometry%'
        ");

        $this->assertNotNull($indexInfo, "Debe existir un índice espacial GIST sobre la columna 'geometry' de 'neighborhoods'.");
    }

    /**
     * Valida las columnas, índice único de DNI y soft deletes de patients.
     */
    public function test_patients_table_structure_and_constraints(): void
    {
        $columns = [
            'id', 'dni', 'first_name', 'last_name', 'birth_date',
            'gender', 'phone', 'address', 'neighborhood_id',
            'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('patients', $column),
                "La columna '{$column}' debe existir en 'patients'."
            );
        }

        // Verificar índice único en DNI
        $dniIndex = DB::selectOne("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'patients'
              AND indexdef LIKE '%UNIQUE%(dni)%'
        ");

        $this->assertNotNull($dniIndex, "Debe existir un índice UNIQUE en patients.dni.");
    }

    /**
     * Valida las columnas y tipo JSONB en vital_signs de la tabla triages.
     */
    public function test_triages_table_structure_and_jsonb(): void
    {
        $columns = [
            'id', 'patient_id', 'user_id', 'symptoms', 'vital_signs',
            'algorithmic_priority', 'final_priority', 'is_override',
            'target_wait_minutes', 'status', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('triages', $column),
                "La columna '{$column}' debe existir en 'triages'."
            );
        }

        // Verificar tipo de columna vital_signs en Postgres
        $columnType = DB::selectOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_name = 'triages' AND column_name = 'vital_signs'
        ")->data_type;

        $this->assertSame('jsonb', $columnType, "La columna 'vital_signs' debe ser de tipo jsonb.");
    }

    /**
     * Valida la estructura de triage_audit_logs y la ausencia de updated_at para inmutabilidad.
     */
    public function test_triage_audit_logs_table_structure_and_inmutability(): void
    {
        $columns = [
            'id', 'triage_id', 'user_id', 'original_priority',
            'overridden_priority', 'override_reason', 'created_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('triage_audit_logs', $column),
                "La columna '{$column}' debe existir en 'triage_audit_logs'."
            );
        }

        // Inmutabilidad médico-legal: updated_at NO debe existir en la tabla
        $this->assertFalse(
            Schema::hasColumn('triage_audit_logs', 'updated_at'),
            "La tabla 'triage_audit_logs' no debe tener columna 'updated_at' para garantizar inmutabilidad."
        );
    }

    /**
     * Valida las columnas de la tabla consultations.
     */
    public function test_consultations_table_structure(): void
    {
        $columns = [
            'id', 'triage_id', 'doctor_id', 'clinical_notes',
            'diagnosis', 'prescription', 'status', 'started_at',
            'completed_at', 'created_at', 'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('consultations', $column),
                "La columna '{$column}' debe existir en 'consultations'."
            );
        }
    }

    /**
     * Valida la persistencia real y recuperación con relaciones de todos los modelos del ecosistema.
     */
    public function test_can_persist_and_retrieve_all_models_with_relationships(): void
    {
        $nurse = \App\Models\User::factory()->triageNurse()->create();
        $doctor = \App\Models\User::factory()->doctor()->create();

        $neighborhood = \App\Models\Neighborhood::factory()->create([
            'name' => 'San Miguel',
            'risk_level' => 'alto',
        ]);

        $patient = \App\Models\Patient::factory()->create([
            'dni' => '12345678',
            'first_name' => 'María',
            'last_name' => 'González',
            'neighborhood_id' => $neighborhood->id,
        ]);

        $triage = \App\Models\Triage::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $nurse->id,
            'algorithmic_priority' => \App\Enums\TriagePriority::GREEN,
            'final_priority' => \App\Enums\TriagePriority::ORANGE,
            'is_override' => true,
            'target_wait_minutes' => 10,
        ]);

        $auditLog = \App\Models\TriageAuditLog::factory()->create([
            'triage_id' => $triage->id,
            'user_id' => $doctor->id,
            'original_priority' => \App\Enums\TriagePriority::GREEN,
            'overridden_priority' => \App\Enums\TriagePriority::ORANGE,
            'override_reason' => 'Signos de dificultad respiratoria aguda constatados por guardia.',
        ]);

        $consultation = \App\Models\Consultation::factory()->create([
            'triage_id' => $triage->id,
            'doctor_id' => $doctor->id,
            'status' => \App\Enums\ConsultationStatus::IN_PROGRESS,
        ]);

        // Cargar desde la base de datos con relaciones
        $freshTriage = \App\Models\Triage::with(['patient.neighborhood', 'user', 'auditLogs', 'consultation.doctor'])
            ->findOrFail($triage->id);

        $this->assertSame('María González', $freshTriage->patient->full_name);
        $this->assertSame('San Miguel', $freshTriage->patient->neighborhood->name);
        $this->assertSame(\App\Enums\TriagePriority::ORANGE, $freshTriage->final_priority);
        $this->assertTrue($freshTriage->is_override);
        $this->assertCount(1, $freshTriage->auditLogs);
        $this->assertSame($auditLog->id, $freshTriage->auditLogs->first()->id);
        $this->assertSame($doctor->id, $freshTriage->consultation->doctor->id);
    }
}
