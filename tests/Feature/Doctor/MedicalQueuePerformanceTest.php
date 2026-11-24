<?php

declare(strict_types=1);

namespace Tests\Feature\Doctor;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use Database\Seeders\NeighborhoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CEN-45: la cola activa se apoya en idx_triages_active_queue y no genera N+1
 * (docs/specs/consultas/spec.md RF-CONS-07).
 */
class MedicalQueuePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function sembrarCola(int $cantidad): void
    {
        $barrio = Neighborhood::query()->firstOrFail();

        Triage::factory()
            ->count($cantidad)
            ->for(Patient::factory()->state(['neighborhood_id' => $barrio->id]))
            ->sequence(
                ['status' => TriageStatus::PENDING_CONSULTATION],
                ['status' => TriageStatus::CALLED],
                ['status' => TriageStatus::IN_CONSULTATION],
                ['status' => TriageStatus::COMPLETED],
            )
            ->create();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NeighborhoodSeeder::class);
    }

    public function test_el_indice_compuesto_de_la_cola_existe_en_postgresql(): void
    {
        $definicion = DB::scalar(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'triages' AND indexname = 'idx_triages_active_queue'"
        );

        $this->assertNotNull($definicion);
        $this->assertStringContainsString('(status, final_priority, created_at)', $definicion);
    }

    public function test_la_consulta_de_cola_activa_usa_el_indice_compuesto(): void
    {
        $this->sembrarCola(40);
        DB::statement('ANALYZE triages');

        // Con pocas filas PostgreSQL prefiere un seq scan; se desactiva para
        // comprobar que el plan puede resolverse con el índice de la cola.
        DB::statement('SET LOCAL enable_seqscan = off');
        $query = Triage::query()->activeQueue();
        $plan = collect(DB::select('EXPLAIN '.$query->toSql(), $query->getBindings()))
            ->pluck('QUERY PLAN')
            ->implode("\n");

        $this->assertStringContainsString('idx_triages_active_queue', $plan);
    }

    public function test_la_consulta_de_cola_activa_responde_en_menos_de_20_ms(): void
    {
        $this->sembrarCola(400);
        DB::statement('ANALYZE triages');

        $query = Triage::query()->activeQueue();
        $plan = collect(DB::select('EXPLAIN ANALYZE '.$query->toSql(), $query->getBindings()))
            ->pluck('QUERY PLAN')
            ->implode("\n");

        preg_match('/Execution Time: ([\d.]+) ms/', $plan, $tiempo);
        $this->assertNotEmpty($tiempo, $plan);
        $this->assertLessThan(20.0, (float) $tiempo[1], $plan);
    }

    public function test_la_cola_activa_excluye_completados_y_cancelados_y_respeta_el_orden_mts(): void
    {
        $verde = Triage::factory()->create(['final_priority' => TriagePriority::GREEN, 'status' => TriageStatus::PENDING_CONSULTATION]);
        $rojo = Triage::factory()->create(['final_priority' => TriagePriority::RED, 'status' => TriageStatus::PENDING_CONSULTATION]);
        $llamado = Triage::factory()->create(['final_priority' => TriagePriority::BLUE, 'status' => TriageStatus::CALLED]);
        Triage::factory()->create(['status' => TriageStatus::COMPLETED]);
        Triage::factory()->create(['status' => TriageStatus::CANCELLED]);

        $ids = Triage::query()->activeQueue()->pluck('id')->all();

        $this->assertSame([$rojo->id, $verde->id, $llamado->id], $ids);
    }

    public function test_la_cola_activa_precarga_relaciones_sin_consultas_n_mas_1(): void
    {
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            Triage::query()->activeQueue()->get()->each(function (Triage $triage): void {
                $triage->patient->neighborhood?->name;
                $triage->user->name;
                $triage->consultation?->status;
            });

            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->sembrarCola(4);
        $conPocos = $contar();

        $this->sembrarCola(40);
        $conMuchos = $contar();

        $this->assertSame($conPocos, $conMuchos);
        $this->assertLessThanOrEqual(5, $conMuchos);
    }
}
