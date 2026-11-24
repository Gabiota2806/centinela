<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\ConsultationStatus;
use App\Enums\TriageStatus;
use App\Events\ConsultationCompleted;
use App\Models\Consultation;
use App\Models\Triage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Valida el esquema base de contratos compartidos para Cola Médica, Clima,
 * Telemetría Outbox y Analítica Geoespacial (docs/specs/00_contracts.md).
 */
class SprintContractsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_triages_has_composite_active_queue_index(): void
    {
        $index = DB::selectOne("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'triages'
              AND indexname = 'idx_triages_active_queue'
        ");

        $this->assertNotNull($index, 'Debe existir el índice compuesto idx_triages_active_queue en triages.');
        $this->assertStringContainsString('status', $index->indexdef);
        $this->assertStringContainsString('final_priority', $index->indexdef);
        $this->assertStringContainsString('created_at', $index->indexdef);
    }

    public function test_neighborhoods_has_predictive_risk_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('neighborhoods', 'risk_score'));
        $this->assertTrue(Schema::hasColumn('neighborhoods', 'risk_updated_at'));
    }

    public function test_weather_epidemiology_and_outbox_tables_exist_with_contract_columns(): void
    {
        $expectedTables = [
            'weather_records' => [
                'id', 'recorded_date', 'latitude', 'longitude',
                'temperature_max', 'temperature_min', 'precipitation_sum',
                'relative_humidity_mean', 'source', 'raw_payload',
                'created_at', 'updated_at',
            ],
            'epidemiology_settings' => [
                'id', 'rain_threshold_mm', 'time_lag_days',
                'febrile_alert_threshold', 'high_risk_probability',
                'updated_by', 'created_at', 'updated_at',
            ],
            'outbox_telemetry_events' => [
                'id', 'event_uuid', 'consultation_id', 'payload',
                'status', 'attempts', 'last_error', 'sent_at',
                'created_at', 'updated_at',
            ],
        ];

        foreach ($expectedTables as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "La tabla '{$table}' debe existir.");

            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasColumn($table, $column),
                    "La columna '{$column}' debe existir en '{$table}'."
                );
            }
        }
    }

    public function test_consultation_completed_domain_event_can_be_dispatched(): void
    {
        Event::fake([ConsultationCompleted::class]);

        $triage = Triage::factory()->create(['status' => TriageStatus::COMPLETED]);
        $consultation = Consultation::factory()->create([
            'triage_id' => $triage->id,
            'status' => ConsultationStatus::COMPLETED,
        ]);

        ConsultationCompleted::dispatch($consultation, $triage);

        Event::assertDispatched(
            ConsultationCompleted::class,
            fn (ConsultationCompleted $event): bool => $event->consultation->is($consultation)
                && $event->triage->is($triage)
        );
    }
}
