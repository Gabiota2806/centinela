<?php

declare(strict_types=1);

namespace Tests\Feature\Weather;

use App\Models\WeatherRecord;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Pruebas del comando programado weather:fetch-daily y caché de contingencia de 7 días (RF-GEO-02).
 */
class FetchDailyWeatherCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(OpenMeteoService::CONTINGENCY_CACHE_KEY);
    }

    public function test_command_fetches_weather_and_populates_seven_day_contingency_cache(): void
    {
        $dates = collect(range(6, 0))
            ->map(static fn (int $offset): string => now()->subDays($offset)->toDateString())
            ->values()
            ->all();

        Http::fake([
            'https://api.open-meteo.com/v1/forecast*' => Http::response([
                'latitude' => -26.18,
                'longitude' => -58.17,
                'daily' => [
                    'time' => $dates,
                    'temperature_2m_max' => [31.0, 32.5, 33.0, 29.8, 30.1, 34.2, 35.0],
                    'temperature_2m_min' => [20.0, 21.0, 22.0, 19.5, 18.8, 21.4, 22.7],
                    'precipitation_sum' => [0.0, 5.2, 42.8, 12.0, 0.0, 0.0, 61.5],
                    'relative_humidity_2m_mean' => [65.0, 72.0, 88.0, 79.0, 66.0, 62.0, 85.0],
                ],
            ], 200),
        ]);

        $this->artisan('weather:fetch-daily')
            ->expectsOutputToContain('7 registros procesados')
            ->assertSuccessful();

        $this->assertDatabaseCount('weather_records', 7);
        $this->assertTrue(Cache::has(OpenMeteoService::CONTINGENCY_CACHE_KEY));

        $cached = app(OpenMeteoService::class)->getContingencyWeather(7);
        $this->assertCount(7, $cached);
        $this->assertSame($dates[6], $cached[0]['recorded_date']);
        $this->assertSame(61.5, $cached[0]['precipitation_sum']);
    }

    public function test_command_logs_warning_and_degrades_gracefully_when_open_meteo_is_unreachable(): void
    {
        foreach (range(0, 6) as $daysAgo) {
            WeatherRecord::factory()->create([
                'recorded_date' => now()->subDays($daysAgo)->toDateString(),
                'precipitation_sum' => $daysAgo === 0 ? 55.0 : 4.0,
            ]);
        }

        Http::fake(static function (): never {
            throw new ConnectionException('Network unreachable');
        });

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(static fn (string $message, array $context): bool => str_contains($message, 'Open-Meteo')
                && str_contains((string) ($context['error'] ?? ''), 'Network unreachable'));

        $this->artisan('weather:fetch-daily')
            ->expectsOutputToContain('modo contingencia con 7 día(s) locales')
            ->assertSuccessful();

        $fallback = app(OpenMeteoService::class)->getContingencyWeather(7);
        $this->assertCount(7, $fallback);
        $this->assertSame(55.0, $fallback[0]['precipitation_sum']);
        $this->assertTrue(Cache::has(OpenMeteoService::CONTINGENCY_CACHE_KEY));
    }

    public function test_contingency_weather_serves_from_cache_when_offline_without_network_calls(): void
    {
        Http::preventStrayRequests();

        $expected = [
            [
                'recorded_date' => '2026-11-26',
                'temperature_max' => 34.0,
                'temperature_min' => 22.0,
                'precipitation_sum' => 44.5,
                'relative_humidity_mean' => 83.0,
                'source' => 'open-meteo',
            ],
        ];

        Cache::put(OpenMeteoService::CONTINGENCY_CACHE_KEY, $expected, now()->addDays(7));

        $result = app(OpenMeteoService::class)->getContingencyWeather(7);

        $this->assertSame($expected, $result);
    }

    public function test_daily_weather_command_is_scheduled_at_06_00(): void
    {
        $events = collect(app(Schedule::class)->events());

        /** @var Event|null $weatherEvent */
        $weatherEvent = $events->first(
            static fn (Event $event): bool => str_contains((string) $event->command, 'weather:fetch-daily')
        );

        $this->assertNotNull($weatherEvent, 'El comando weather:fetch-daily debe estar programado en routes/console.php.');
        $this->assertSame('0 6 * * *', $weatherEvent->expression);
    }
}
