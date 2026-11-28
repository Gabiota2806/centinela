<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Events\HighEpidemiologicalRiskDetected;
use App\Models\Neighborhood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncNeighborhoodRiskCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.hub_url', 'http://hub.local:8001');
        config()->set('analytics.api_key', 'caps-sync-key');
        config()->set('analytics.timeout_seconds', 3);
    }

    public function test_syncs_batch_risks_into_neighborhoods_and_dispatches_high_risk_alerts(): void
    {
        Event::fake([HighEpidemiologicalRiskDetected::class]);

        $lowNeighborhood = Neighborhood::factory()->create([
            'name' => 'Centro',
            'risk_level' => 'normal',
            'risk_score' => null,
            'risk_updated_at' => null,
        ]);
        $highNeighborhood = Neighborhood::factory()->create([
            'name' => 'Lote 4',
            'risk_level' => 'low',
            'risk_score' => 0.1200,
            'risk_updated_at' => null,
        ]);

        DB::table('epidemiology_settings')->insert([
            'rain_threshold_mm' => 40.00,
            'time_lag_days' => 10,
            'febrile_alert_threshold' => 5,
            'high_risk_probability' => 0.65,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'http://hub.local:8001/api/v1/neighborhoods/risks*' => Http::response([
                'items' => [
                    [
                        'neighborhood_id' => $lowNeighborhood->id,
                        'risk_class' => 0,
                        'risk_level' => 'low',
                        'risk_score' => 0.0815,
                        'probabilities' => ['low' => 0.80, 'moderate' => 0.1185, 'high' => 0.0815],
                        'model_version' => 'rf-v1.0',
                        'predicted_at' => '2026-11-28T11:00:00-03:00',
                    ],
                    [
                        'neighborhood_id' => $highNeighborhood->id,
                        'risk_class' => 2,
                        'risk_level' => 'high',
                        'risk_score' => 0.8745,
                        'probabilities' => ['low' => 0.04, 'moderate' => 0.0855, 'high' => 0.8745],
                        'model_version' => 'rf-v1.0',
                        'predicted_at' => '2026-11-28T11:00:00-03:00',
                    ],
                    [
                        'neighborhood_id' => 999999,
                        'risk_class' => 1,
                        'risk_level' => 'moderate',
                        'risk_score' => 0.45,
                        'probabilities' => ['low' => 0.25, 'moderate' => 0.30, 'high' => 0.45],
                        'model_version' => 'rf-v1.0',
                        'predicted_at' => '2026-11-28T11:00:00-03:00',
                    ],
                ],
                'model_version' => 'rf-v1.0',
                'predicted_at' => '2026-11-28T11:00:00-03:00',
            ], 200),
        ]);

        $this->artisan('epidemiology:sync-risk')
            ->assertSuccessful()
            ->expectsOutputToContain('2 barrio(s) actualizados; 1 alerta(s) de riesgo alto emitidas');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'high_risk_threshold=0.65'));

        $lowNeighborhood->refresh();
        $highNeighborhood->refresh();

        $this->assertSame('low', $lowNeighborhood->risk_level);
        $this->assertEqualsWithDelta(0.0815, $lowNeighborhood->risk_score, 0.0001);
        $this->assertNotNull($lowNeighborhood->risk_updated_at);

        $this->assertSame('high', $highNeighborhood->risk_level);
        $this->assertEqualsWithDelta(0.8745, $highNeighborhood->risk_score, 0.0001);
        $this->assertNotNull($highNeighborhood->risk_updated_at);

        Event::assertDispatched(HighEpidemiologicalRiskDetected::class, 1);
        Event::assertDispatched(
            HighEpidemiologicalRiskDetected::class,
            fn (HighEpidemiologicalRiskDetected $event): bool => $event->neighborhood->is($highNeighborhood)
                && abs($event->riskScore - 0.8745) < 0.0001
                && $event->previousRiskLevel === 'low'
                && $event->modelVersion === 'rf-v1.0',
        );
    }

    public function test_syncs_single_neighborhood_when_option_is_provided(): void
    {
        Event::fake([HighEpidemiologicalRiskDetected::class]);

        $target = Neighborhood::factory()->create(['name' => 'San Miguel', 'risk_level' => 'low']);
        $untouched = Neighborhood::factory()->create(['name' => 'Belgrano', 'risk_level' => 'low', 'risk_score' => 0.11]);

        Http::fake([
            "http://hub.local:8001/api/v1/neighborhoods/{$target->id}/risk*" => Http::response([
                'neighborhood_id' => $target->id,
                'risk_class' => 1,
                'risk_level' => 'moderate',
                'risk_score' => 0.4123,
                'probabilities' => ['low' => 0.1877, 'moderate' => 0.4000, 'high' => 0.4123],
                'model_version' => 'rf-v1.0',
                'predicted_at' => '2026-11-28T11:30:00-03:00',
            ], 200),
        ]);

        $this->artisan('epidemiology:sync-risk', ['--neighborhood' => (string) $target->id])
            ->assertSuccessful();

        $target->refresh();
        $untouched->refresh();

        $this->assertSame('moderate', $target->risk_level);
        $this->assertEqualsWithDelta(0.4123, $target->risk_score, 0.0001);
        $this->assertSame('low', $untouched->risk_level);
        $this->assertEqualsWithDelta(0.11, $untouched->risk_score, 0.0001);

        Event::assertNotDispatched(HighEpidemiologicalRiskDetected::class);
    }

    public function test_degrades_gracefully_offline_without_corrupting_local_neighborhood_data(): void
    {
        Event::fake([HighEpidemiologicalRiskDetected::class]);

        $existingUpdatedAt = now()->subHours(2)->startOfSecond();
        $neighborhood = Neighborhood::factory()->create([
            'name' => 'Namqom',
            'risk_level' => 'moderate',
            'risk_score' => 0.5512,
            'risk_updated_at' => $existingUpdatedAt,
        ]);

        Http::fake(function (): never {
            throw new ConnectionException('Hub offline');
        });

        $this->artisan('epidemiology:sync-risk')
            ->assertSuccessful()
            ->expectsOutputToContain('Se conservan los datos locales vigentes');

        $neighborhood->refresh();

        $this->assertSame('moderate', $neighborhood->risk_level);
        $this->assertEqualsWithDelta(0.5512, $neighborhood->risk_score, 0.0001);
        $this->assertTrue($neighborhood->risk_updated_at->equalTo($existingUpdatedAt));

        Event::assertNotDispatched(HighEpidemiologicalRiskDetected::class);
    }

    public function test_rejects_invalid_neighborhood_option(): void
    {
        $this->artisan('epidemiology:sync-risk', ['--neighborhood' => 'abc'])
            ->assertExitCode(2);
    }
}
