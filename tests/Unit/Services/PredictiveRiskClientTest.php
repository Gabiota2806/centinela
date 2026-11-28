<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\PredictiveRiskException;
use App\Services\Analytics\PredictiveRiskClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictiveRiskClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.hub_url', 'http://hub.local:8001');
        config()->set('analytics.api_key', 'caps-test-key-123');
        config()->set('analytics.timeout_seconds', 3);
    }

    public function test_fetch_all_neighborhood_risks_sends_caps_key_and_parses_batch_response(): void
    {
        Http::fake([
            'http://hub.local:8001/api/v1/neighborhoods/risks*' => Http::response([
                'items' => [
                    [
                        'neighborhood_id' => 14,
                        'risk_class' => 2,
                        'risk_level' => 'high',
                        'risk_score' => 0.8421,
                        'probabilities' => [
                            'low' => 0.0510,
                            'moderate' => 0.1069,
                            'high' => 0.8421,
                        ],
                        'model_version' => 'rf-v1.0',
                        'predicted_at' => '2026-11-28T10:15:00-03:00',
                    ],
                ],
                'model_version' => 'rf-v1.0',
                'predicted_at' => '2026-11-28T10:15:00-03:00',
            ], 200),
        ]);

        $client = new PredictiveRiskClient;
        $result = $client->fetchAllNeighborhoodRisks(0.70);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://hub.local:8001/api/v1/neighborhoods/risks?high_risk_threshold=0.7'
                && $request->hasHeader('X-CAPS-Key', 'caps-test-key-123')
                && $request->hasHeader('Accept', 'application/json');
        });

        $this->assertSame('rf-v1.0', $result['model_version']);
        $this->assertInstanceOf(CarbonImmutable::class, $result['predicted_at']);
        $this->assertCount(1, $result['items']);
        $this->assertSame(14, $result['items'][0]['neighborhood_id']);
        $this->assertSame('high', $result['items'][0]['risk_level']);
        $this->assertSame(0.8421, $result['items'][0]['risk_score']);
    }

    public function test_fetch_neighborhood_risk_returns_normalized_single_prediction(): void
    {
        Http::fake([
            'http://hub.local:8001/api/v1/neighborhoods/14/risk*' => Http::response([
                'neighborhood_id' => 14,
                'risk_class' => 1,
                'risk_level' => 'moderate',
                'risk_score' => 0.3412,
                'probabilities' => [
                    'low' => 0.1588,
                    'moderate' => 0.5000,
                    'high' => 0.3412,
                ],
                'model_version' => 'rf-v1.0',
                'predicted_at' => '2026-11-28T10:20:00-03:00',
            ], 200),
        ]);

        $client = new PredictiveRiskClient;
        $item = $client->fetchNeighborhoodRisk(14);

        $this->assertSame(14, $item['neighborhood_id']);
        $this->assertSame(1, $item['risk_class']);
        $this->assertSame('moderate', $item['risk_level']);
        $this->assertSame(0.3412, $item['risk_score']);
    }

    public function test_throws_predictive_risk_exception_on_connection_failure(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Connection refused');
        });

        $this->expectException(PredictiveRiskException::class);

        (new PredictiveRiskClient)->fetchAllNeighborhoodRisks();
    }

    public function test_throws_predictive_risk_exception_on_unauthorized_or_server_error(): void
    {
        Http::fake([
            'http://hub.local:8001/api/v1/neighborhoods/risks*' => Http::response(['detail' => 'Unauthorized'], 401),
        ]);

        $this->expectException(PredictiveRiskException::class);
        $this->expectExceptionMessage('HTTP 401');

        (new PredictiveRiskClient)->fetchAllNeighborhoodRisks();
    }

    public function test_throws_predictive_risk_exception_on_invalid_risk_level_in_payload(): void
    {
        Http::fake([
            'http://hub.local:8001/api/v1/neighborhoods/risks*' => Http::response([
                'items' => [
                    [
                        'neighborhood_id' => 14,
                        'risk_class' => 9,
                        'risk_level' => 'extremo_invalido',
                        'risk_score' => 0.9,
                        'model_version' => 'rf-v1.0',
                        'predicted_at' => '2026-11-28T10:20:00-03:00',
                    ],
                ],
                'model_version' => 'rf-v1.0',
                'predicted_at' => '2026-11-28T10:20:00-03:00',
            ], 200),
        ]);

        $this->expectException(PredictiveRiskException::class);

        (new PredictiveRiskClient)->fetchAllNeighborhoodRisks();
    }
}
