<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\WeatherFetchException;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincronización diaria automatizada de clima para Formosa Capital con fallback offline-first (RF-GEO-02).
 */
class FetchDailyWeatherCommand extends Command
{
    protected $signature = 'weather:fetch-daily {--days=7 : Cantidad de días hacia atrás a sincronizar desde Open-Meteo}';

    protected $description = 'Sincroniza el clima diario de Formosa Capital desde Open-Meteo con caché de contingencia de 7 días.';

    public function handle(OpenMeteoService $weatherService): int
    {
        $days = max(1, (int) $this->option('days'));

        try {
            $records = $weatherService->fetchAndStore($days);
            $cached = $weatherService->getContingencyWeather(OpenMeteoService::CONTINGENCY_TTL_DAYS);

            $this->info(sprintf(
                'Clima sincronizado correctamente desde Open-Meteo (%d registros procesados; %d días en caché de contingencia).',
                $records->count(),
                count($cached),
            ));

            return self::SUCCESS;
        } catch (WeatherFetchException|Throwable $exception) {
            Log::warning('Open-Meteo inaccesible durante weather:fetch-daily; degradando a caché/base local.', [
                'error' => $exception->getMessage(),
                'days_requested' => $days,
            ]);

            $fallback = $weatherService->getContingencyWeather(OpenMeteoService::CONTINGENCY_TTL_DAYS);

            $this->warn(sprintf(
                'No se pudo contactar a Open-Meteo (%s). Operando en modo contingencia con %d día(s) locales disponibles.',
                $exception->getMessage(),
                count($fallback),
            ));

            return self::SUCCESS;
        }
    }
}
