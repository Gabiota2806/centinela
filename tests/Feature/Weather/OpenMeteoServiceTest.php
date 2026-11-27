<?php

declare(strict_types=1);

namespace Tests\Feature\Weather;

use App\Exceptions\WeatherFetchException;
use App\Models\WeatherRecord;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pruebas del cliente HTTP Open-Meteo y persistencia idempotente en weather_records (RF-GEO-01).
 */
class OpenMeteoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_formosa_weather_and_persists_records(): void
    {
        Http::fake([
            'https://api.open-meteo.com/v1/forecast*' => Http::response([
                'latitude' => -26.18,
                'longitude' => -58.17,
                'timezone' => 'America/Argentina/Cordoba',
                'daily' => [
                    'time' => ['2026-11-24', '2026-11-25'],
                    'temperature_2m_max' => [34.5, 31.2],
                    'temperature_2m_min' => [22.1, 20.4],
                    'precipitation_sum' => [0.0, 48.6],
                    'relative_humidity_2m_mean' => [68.4, 84.2],
                ],
            ], 200),
        ]);

        $service = app(OpenMeteoService::class);
        $records = $service->fetchAndStore(pastDays: 2);

        $this->assertCount(2, $records);
        $this->assertDatabaseCount('weather_records', 2);

        Http::assertSent(function (Request $request): bool {
            $url = $request->url();

            return str_starts_with($url, 'https://api.open-meteo.com/v1/forecast')
                && (float) $request['latitude'] === -26.18
                && (float) $request['longitude'] === -58.17
                && str_contains((string) $request['daily'], 'precipitation_sum')
                && str_contains((string) $request['daily'], 'temperature_2m_max')
                && str_contains((string) $request['daily'], 'temperature_2m_min')
                && str_contains((string) $request['daily'], 'relative_humidity_2m_mean');
        });

        $rainyDay = WeatherRecord::query()->whereDate('recorded_date', '2026-11-25')->firstOrFail();

        $this->assertSame(-26.18, $rainyDay->latitude);
        $this->assertSame(-58.17, $rainyDay->longitude);
        $this->assertSame(31.2, $rainyDay->temperature_max);
        $this->assertSame(20.4, $rainyDay->temperature_min);
        $this->assertSame(48.6, $rainyDay->precipitation_sum);
        $this->assertSame(84.2, $rainyDay->relative_humidity_mean);
        $this->assertSame('open-meteo', $rainyDay->source);
        $this->assertIsArray($rainyDay->raw_payload);
        $this->assertSame(48.6, $rainyDay->raw_payload['precipitation_sum']);
    }

    public function test_calculates_daily_humidity_mean_from_hourly_series_when_daily_mean_is_absent(): void
    {
        Http::fake([
            '*' => Http::response([
                'latitude' => -26.18,
                'longitude' => -58.17,
                'daily' => [
                    'time' => ['2026-11-26'],
                    'temperature_2m_max' => [33.0],
                    'temperature_2m_min' => [21.0],
                    'precipitation_sum' => [12.5],
                ],
                'hourly' => [
                    'time' => ['2026-11-26T00:00', '2026-11-26T12:00', '2026-11-26T18:00'],
                    'relative_humidity_2m' => [70, 80, 90],
                ],
            ], 200),
        ]);

        app(OpenMeteoService::class)->fetchAndStore(pastDays: 1);

        $record = WeatherRecord::query()->whereDate('recorded_date', '2026-11-26')->firstOrFail();
        $this->assertSame(80.0, $record->relative_humidity_mean);
    }

    public function test_persists_records_idempotently_updating_existing_recorded_date(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'daily' => [
                        'time' => ['2026-11-25'],
                        'temperature_2m_max' => [30.0],
                        'temperature_2m_min' => [19.0],
                        'precipitation_sum' => [10.0],
                        'relative_humidity_2m_mean' => [70.0],
                    ],
                ], 200)
                ->push([
                    'daily' => [
                        'time' => ['2026-11-25'],
                        'temperature_2m_max' => [32.4],
                        'temperature_2m_min' => [20.1],
                        'precipitation_sum' => [45.5],
                        'relative_humidity_2m_mean' => [82.0],
                    ],
                ], 200),
        ]);

        $service = app(OpenMeteoService::class);
        $service->fetchAndStore(pastDays: 1);
        $service->fetchAndStore(pastDays: 1);

        $this->assertDatabaseCount('weather_records', 1);

        $record = WeatherRecord::query()->whereDate('recorded_date', '2026-11-25')->firstOrFail();
        $this->assertSame(32.4, $record->temperature_max);
        $this->assertSame(45.5, $record->precipitation_sum);
        $this->assertSame(82.0, $record->relative_humidity_mean);
    }

    public function test_throws_weather_fetch_exception_on_http_failure_without_corrupting_existing_data(): void
    {
        $existing = WeatherRecord::factory()->create([
            'recorded_date' => '2026-11-24',
            'precipitation_sum' => 15.0,
        ]);

        Http::fake([
            '*' => Http::response(['error' => true, 'reason' => 'Service unavailable'], 503),
        ]);

        try {
            app(OpenMeteoService::class)->fetchAndStore(pastDays: 1);
            $this->fail('Se esperaba WeatherFetchException ante HTTP 503.');
        } catch (WeatherFetchException $exception) {
            $this->assertStringContainsString('503', $exception->getMessage());
        }

        $this->assertDatabaseCount('weather_records', 1);
        $this->assertSame(15.0, $existing->fresh()->precipitation_sum);
    }

    public function test_throws_weather_fetch_exception_on_connection_error(): void
    {
        Http::fake(static function (): never {
            throw new ConnectionException('Connection timed out');
        });

        $this->expectException(WeatherFetchException::class);

        app(OpenMeteoService::class)->fetchAndStore(pastDays: 1);
    }
}
