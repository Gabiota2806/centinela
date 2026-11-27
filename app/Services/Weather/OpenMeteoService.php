<?php

declare(strict_types=1);

namespace App\Services\Weather;

use App\Exceptions\WeatherFetchException;
use App\Models\WeatherRecord;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP de Open-Meteo para Formosa Capital con persistencia idempotente en weather_records (RF-GEO-01)
 * y caché de contingencia de 7 días para operación offline-first (RF-GEO-02).
 */
class OpenMeteoService
{
    public const SOURCE = 'open-meteo';

    public const CONTINGENCY_CACHE_KEY = 'centinela.weather.contingency_7d';

    public const CONTINGENCY_TTL_DAYS = 7;

    /**
     * Consulta la serie meteorológica diaria en Open-Meteo y la persiste de forma idempotente por fecha.
     *
     * @return Collection<int, WeatherRecord>
     */
    public function fetchAndStore(?int $pastDays = null): Collection
    {
        $baseUrl = (string) config('services.open_meteo.base_url', 'https://api.open-meteo.com/v1/forecast');
        $latitude = (float) config('services.open_meteo.latitude', -26.18);
        $longitude = (float) config('services.open_meteo.longitude', -58.17);
        $timezone = (string) config('services.open_meteo.timezone', 'America/Argentina/Cordoba');
        $timeout = max(1, (int) config('services.open_meteo.timeout', 10));
        $daysBack = max(0, $pastDays ?? (int) config('services.open_meteo.past_days', 7));
        $forecastDays = max(1, (int) config('services.open_meteo.forecast_days', 1));

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->get($baseUrl, [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,relative_humidity_2m_mean',
                    'timezone' => $timezone,
                    'past_days' => $daysBack,
                    'forecast_days' => $forecastDays,
                ]);
        } catch (ConnectionException $exception) {
            throw new WeatherFetchException(
                'No se pudo establecer conexión con Open-Meteo: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new WeatherFetchException(
                sprintf('Open-Meteo respondió con código HTTP %d.', $response->status()),
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['daily']['time']) || ! is_array($payload['daily']['time']) || $payload['daily']['time'] === []) {
            throw new WeatherFetchException('La respuesta de Open-Meteo no contiene la serie diaria esperada.');
        }

        $daily = $payload['daily'];
        $hourlyHumidityMeans = $this->calculateHourlyHumidityMeans($payload);
        $resolvedLat = isset($payload['latitude']) && is_numeric($payload['latitude']) ? round((float) $payload['latitude'], 4) : round($latitude, 4);
        $resolvedLng = isset($payload['longitude']) && is_numeric($payload['longitude']) ? round((float) $payload['longitude'], 4) : round($longitude, 4);

        /** @var Collection<int, WeatherRecord> $records */
        $records = DB::transaction(function () use ($daily, $hourlyHumidityMeans, $resolvedLat, $resolvedLng, $timezone): Collection {
            $stored = collect();

            foreach ($daily['time'] as $index => $date) {
                if (! is_string($date) || trim($date) === '') {
                    continue;
                }

                $tempMax = $this->numericAt($daily, 'temperature_2m_max', $index);
                $tempMin = $this->numericAt($daily, 'temperature_2m_min', $index);
                $precipitation = $this->numericAt($daily, 'precipitation_sum', $index) ?? 0.0;
                $humidity = $this->numericAt($daily, 'relative_humidity_2m_mean', $index)
                    ?? $this->numericAt($daily, 'relative_humidity_2m', $index)
                    ?? ($hourlyHumidityMeans[$date] ?? null);

                $record = WeatherRecord::query()->updateOrCreate(
                    ['recorded_date' => $date],
                    [
                        'latitude' => $resolvedLat,
                        'longitude' => $resolvedLng,
                        'temperature_max' => $tempMax,
                        'temperature_min' => $tempMin,
                        'precipitation_sum' => $precipitation,
                        'relative_humidity_mean' => $humidity,
                        'source' => self::SOURCE,
                        'raw_payload' => [
                            'date' => $date,
                            'timezone' => $timezone,
                            'latitude' => $resolvedLat,
                            'longitude' => $resolvedLng,
                            'temperature_2m_max' => $tempMax,
                            'temperature_2m_min' => $tempMin,
                            'precipitation_sum' => $precipitation,
                            'relative_humidity_2m_mean' => $humidity,
                        ],
                    ],
                );

                $stored->push($record);
            }

            return $stored;
        });

        $this->refreshContingencyCache();

        return $records;
    }

    /**
     * Actualiza la caché de contingencia de 7 días a partir de la base local PostgreSQL.
     *
     * @return array<int, array{recorded_date: string, temperature_max: float|null, temperature_min: float|null, precipitation_sum: float, relative_humidity_mean: float|null, source: string}>
     */
    public function refreshContingencyCache(int $days = self::CONTINGENCY_TTL_DAYS): array
    {
        $limit = max(1, $days);

        /** @var array<int, array{recorded_date: string, temperature_max: float|null, temperature_min: float|null, precipitation_sum: float, relative_humidity_mean: float|null, source: string}> $snapshot */
        $snapshot = WeatherRecord::query()
            ->recentDays($limit)
            ->get()
            ->map(static fn (WeatherRecord $record): array => [
                'recorded_date' => $record->recorded_date->toDateString(),
                'temperature_max' => $record->temperature_max,
                'temperature_min' => $record->temperature_min,
                'precipitation_sum' => $record->precipitation_sum,
                'relative_humidity_mean' => $record->relative_humidity_mean,
                'source' => $record->source,
            ])
            ->values()
            ->all();

        if ($snapshot !== []) {
            Cache::put(
                self::CONTINGENCY_CACHE_KEY,
                $snapshot,
                now()->addDays(self::CONTINGENCY_TTL_DAYS),
            );
        }

        return $snapshot;
    }

    /**
     * Devuelve los últimos N días climáticos desde caché de contingencia o, en su defecto, desde la base local.
     *
     * @return array<int, array{recorded_date: string, temperature_max: float|null, temperature_min: float|null, precipitation_sum: float, relative_humidity_mean: float|null, source: string}>
     */
    public function getContingencyWeather(int $days = self::CONTINGENCY_TTL_DAYS): array
    {
        $limit = max(1, $days);
        $cached = Cache::get(self::CONTINGENCY_CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            return array_slice($cached, 0, $limit);
        }

        return $this->refreshContingencyCache($limit);
    }

    /**
     * @param  array<string, mixed>  $daily
     */
    private function numericAt(array $daily, string $key, int $index): ?float
    {
        if (! isset($daily[$key]) || ! is_array($daily[$key]) || ! array_key_exists($index, $daily[$key])) {
            return null;
        }

        $value = $daily[$key][$index];

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * Calcula la humedad relativa media diaria a partir de la serie horaria `relative_humidity_2m`
     * cuando el proveedor o mock no incluye `daily.relative_humidity_2m_mean`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    private function calculateHourlyHumidityMeans(array $payload): array
    {
        $times = $payload['hourly']['time'] ?? null;
        $values = $payload['hourly']['relative_humidity_2m'] ?? null;

        if (! is_array($times) || ! is_array($values)) {
            return [];
        }

        $grouped = [];

        foreach ($times as $index => $timestamp) {
            if (! is_string($timestamp) || strlen($timestamp) < 10) {
                continue;
            }

            if (! array_key_exists($index, $values) || ! is_numeric($values[$index])) {
                continue;
            }

            $day = substr($timestamp, 0, 10);
            $grouped[$day][] = (float) $values[$index];
        }

        $means = [];

        foreach ($grouped as $day => $samples) {
            $means[$day] = round(array_sum($samples) / count($samples), 2);
        }

        return $means;
    }
}
