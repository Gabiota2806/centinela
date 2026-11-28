<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\HighEpidemiologicalRiskDetected;
use App\Exceptions\PredictiveRiskException;
use App\Models\Neighborhood;
use App\Services\Analytics\PredictiveRiskClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincroniza los niveles de riesgo epidemiológico desde el Hub FastAPI hacia neighborhoods (RF-GEO-09).
 *
 * Opera con tolerancia a desconexiones (Offline-First): si el Hub no responde, registra el
 * incidente y conserva intactos los datos locales previos sin lanzar fallo fatal.
 */
class SyncNeighborhoodRiskCommand extends Command
{
    protected $signature = 'epidemiology:sync-risk
        {--neighborhood= : ID específico de barrio a sincronizar en lugar del lote completo}';

    protected $description = 'Sincroniza el riesgo epidemiológico barrial desde FastAPI hacia PostgreSQL y emite alertas de riesgo alto.';

    public function handle(PredictiveRiskClient $client): int
    {
        $neighborhoodOption = $this->option('neighborhood');
        $targetNeighborhoodId = null;

        if ($neighborhoodOption !== null && $neighborhoodOption !== '') {
            $parsedId = filter_var($neighborhoodOption, FILTER_VALIDATE_INT);
            if ($parsedId === false || $parsedId < 1) {
                $this->error('La opción --neighborhood debe ser un entero positivo.');

                return self::INVALID;
            }
            $targetNeighborhoodId = $parsedId;
        }

        $highRiskThreshold = $this->resolveConfiguredHighRiskThreshold();

        try {
            if ($targetNeighborhoodId !== null) {
                $items = [$client->fetchNeighborhoodRisk($targetNeighborhoodId, $highRiskThreshold)];
            } else {
                $batch = $client->fetchAllNeighborhoodRisks($highRiskThreshold);
                $items = $batch['items'];
            }
        } catch (PredictiveRiskException|Throwable $exception) {
            Log::warning('Hub analítico inaccesible durante epidemiology:sync-risk; preservando niveles de riesgo locales.', [
                'error' => $exception->getMessage(),
                'neighborhood_id' => $targetNeighborhoodId,
            ]);

            $this->warn(sprintf(
                'No se pudo sincronizar el riesgo epidemiológico desde el Hub (%s). Se conservan los datos locales vigentes.',
                $exception->getMessage(),
            ));

            return self::SUCCESS;
        }

        if ($items === []) {
            $this->info('El Hub analítico no retornó predicciones para sincronizar.');

            return self::SUCCESS;
        }

        $ids = array_values(array_unique(array_map(
            static fn (array $item): int => $item['neighborhood_id'],
            $items,
        )));

        /** @var list<array{neighborhood: Neighborhood, risk_score: float, previous_risk_level: ?string, model_version: string, predicted_at: CarbonImmutable}> $highRiskAlerts */
        $highRiskAlerts = [];
        $updatedCount = 0;

        DB::transaction(function () use ($ids, $items, &$updatedCount, &$highRiskAlerts): void {
            $neighborhoods = Neighborhood::query()
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                /** @var Neighborhood|null $neighborhood */
                $neighborhood = $neighborhoods->get($item['neighborhood_id']);
                if ($neighborhood === null) {
                    continue;
                }

                $previousRiskLevel = $neighborhood->risk_level;

                $neighborhood->forceFill([
                    'risk_level' => $item['risk_level'],
                    'risk_score' => $item['risk_score'],
                    'risk_updated_at' => $item['predicted_at'],
                ])->save();

                $updatedCount++;

                if ($item['risk_level'] === 'high') {
                    $highRiskAlerts[] = [
                        'neighborhood' => $neighborhood,
                        'risk_score' => $item['risk_score'],
                        'previous_risk_level' => $previousRiskLevel,
                        'model_version' => $item['model_version'],
                        'predicted_at' => $item['predicted_at'],
                    ];
                }
            }
        });

        foreach ($highRiskAlerts as $alert) {
            HighEpidemiologicalRiskDetected::dispatch(
                $alert['neighborhood'],
                $alert['risk_score'],
                $alert['previous_risk_level'],
                $alert['model_version'],
                $alert['predicted_at'],
            );

            Log::warning('Alerta epidemiológica interna: barrio clasificado en riesgo alto.', [
                'neighborhood_id' => $alert['neighborhood']->id,
                'neighborhood_name' => $alert['neighborhood']->name,
                'risk_level' => 'high',
                'risk_score' => $alert['risk_score'],
                'previous_risk_level' => $alert['previous_risk_level'],
                'model_version' => $alert['model_version'],
                'predicted_at' => $alert['predicted_at']->toIso8601String(),
            ]);
        }

        $this->info(sprintf(
            'Riesgo epidemiológico sincronizado (%d barrio(s) actualizados; %d alerta(s) de riesgo alto emitidas).',
            $updatedCount,
            count($highRiskAlerts),
        ));

        return self::SUCCESS;
    }

    private function resolveConfiguredHighRiskThreshold(): ?float
    {
        $raw = DB::table('epidemiology_settings')
            ->orderByDesc('id')
            ->value('high_risk_probability');

        if ($raw === null || ! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        return ($value > 0.0 && $value <= 1.0) ? $value : null;
    }
}
