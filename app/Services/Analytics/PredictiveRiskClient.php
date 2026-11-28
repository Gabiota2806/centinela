<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Exceptions\PredictiveRiskException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente HTTP REST hacia el Hub analítico FastAPI para inferencias de riesgo barrial (RF-GEO-09).
 *
 * Consume GET /api/v1/neighborhoods/risks y GET /api/v1/neighborhoods/{id}/risk autenticando
 * exclusivamente mediante X-CAPS-Key conforme a docs/specs/00_contracts.md §4.2.
 */
class PredictiveRiskClient
{
    /** @var list<string> */
    private const CANONICAL_RISK_LEVELS = ['low', 'moderate', 'high'];

    /**
     * Obtiene las predicciones de riesgo en lote para todos los barrios disponibles en el Hub.
     *
     * @param  list<int>|null  $neighborhoodIds
     * @return array{
     *     items: list<array{
     *         neighborhood_id: int,
     *         risk_class: int,
     *         risk_level: string,
     *         risk_score: float,
     *         probabilities: array{low: float, moderate: float, high: float},
     *         model_version: string,
     *         predicted_at: CarbonImmutable,
     *     }>,
     *     model_version: string,
     *     predicted_at: CarbonImmutable,
     * }
     *
     * @throws PredictiveRiskException
     */
    public function fetchAllNeighborhoodRisks(?float $highRiskThreshold = null, ?array $neighborhoodIds = null): array
    {
        $query = $this->buildQuery($highRiskThreshold, $neighborhoodIds);

        try {
            $response = $this->httpClient()->get('/api/v1/neighborhoods/risks', $query);
        } catch (ConnectionException $exception) {
            throw new PredictiveRiskException(
                'No se pudo establecer conexión con el Hub analítico para consultar riesgos barriales.',
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new PredictiveRiskException(sprintf(
                'El Hub analítico respondió HTTP %d al consultar riesgos barriales.',
                $response->status(),
            ));
        }

        $payload = $response->json();
        if (! is_array($payload) || ! isset($payload['items']) || ! is_array($payload['items'])) {
            throw new PredictiveRiskException('Respuesta batch de inferencia inválida: falta el arreglo items.');
        }

        $batchModelVersion = is_string($payload['model_version'] ?? null) && trim($payload['model_version']) !== ''
            ? trim($payload['model_version'])
            : 'unknown';
        $batchPredictedAt = $this->parseTimestamp($payload['predicted_at'] ?? null);

        $items = [];
        foreach ($payload['items'] as $rawItem) {
            if (! is_array($rawItem)) {
                throw new PredictiveRiskException('Elemento inválido dentro del lote de inferencias.');
            }
            $items[] = $this->normalizeRiskItem($rawItem, $batchModelVersion, $batchPredictedAt);
        }

        return [
            'items' => $items,
            'model_version' => $batchModelVersion,
            'predicted_at' => $batchPredictedAt,
        ];
    }

    /**
     * Obtiene la predicción de riesgo individual para un barrio específico.
     *
     * @return array{
     *     neighborhood_id: int,
     *     risk_class: int,
     *     risk_level: string,
     *     risk_score: float,
     *     probabilities: array{low: float, moderate: float, high: float},
     *     model_version: string,
     *     predicted_at: CarbonImmutable,
     * }
     *
     * @throws PredictiveRiskException
     */
    public function fetchNeighborhoodRisk(int $neighborhoodId, ?float $highRiskThreshold = null): array
    {
        if ($neighborhoodId < 1) {
            throw new PredictiveRiskException('El identificador de barrio debe ser un entero positivo.');
        }

        $query = $this->buildQuery($highRiskThreshold);

        try {
            $response = $this->httpClient()->get("/api/v1/neighborhoods/{$neighborhoodId}/risk", $query);
        } catch (ConnectionException $exception) {
            throw new PredictiveRiskException(
                "No se pudo establecer conexión con el Hub analítico para el barrio {$neighborhoodId}.",
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new PredictiveRiskException(sprintf(
                'El Hub analítico respondió HTTP %d al consultar el barrio %d.',
                $response->status(),
                $neighborhoodId,
            ));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new PredictiveRiskException('Respuesta individual de inferencia inválida.');
        }

        return $this->normalizeRiskItem($payload);
    }

    private function httpClient(): PendingRequest
    {
        $baseUrl = rtrim((string) (config('centinela.hub.url') ?: config('analytics.hub_url', 'http://hub_analytics:8001')), '/');
        $apiKey = (string) (config('centinela.hub.api_key') ?: config('analytics.api_key', ''));
        $timeout = max(1, (int) (config('centinela.hub.timeout_seconds') ?: config('analytics.timeout_seconds', 5)));

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout($timeout)
            ->withHeaders([
                'X-CAPS-Key' => $apiKey,
            ]);
    }

    /**
     * @param  list<int>|null  $neighborhoodIds
     * @return array<string, float|string>
     */
    private function buildQuery(?float $highRiskThreshold, ?array $neighborhoodIds = null): array
    {
        $query = [];

        if ($highRiskThreshold !== null) {
            if ($highRiskThreshold <= 0.0 || $highRiskThreshold > 1.0) {
                throw new PredictiveRiskException('El umbral high_risk_threshold debe estar en el rango (0, 1].');
            }
            $query['high_risk_threshold'] = round($highRiskThreshold, 4);
        }

        if ($neighborhoodIds !== null && $neighborhoodIds !== []) {
            $cleanIds = array_values(array_unique(array_filter(
                array_map(static fn (int $id): int => $id, $neighborhoodIds),
                static fn (int $id): bool => $id >= 1,
            )));
            if ($cleanIds !== []) {
                $query['neighborhood_ids'] = implode(',', $cleanIds);
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *     neighborhood_id: int,
     *     risk_class: int,
     *     risk_level: string,
     *     risk_score: float,
     *     probabilities: array{low: float, moderate: float, high: float},
     *     model_version: string,
     *     predicted_at: CarbonImmutable,
     * }
     */
    private function normalizeRiskItem(
        array $raw,
        ?string $fallbackModelVersion = null,
        ?CarbonImmutable $fallbackPredictedAt = null,
    ): array {
        $neighborhoodId = filter_var($raw['neighborhood_id'] ?? null, FILTER_VALIDATE_INT);
        if ($neighborhoodId === false || $neighborhoodId < 1) {
            throw new PredictiveRiskException('Respuesta de inferencia sin neighborhood_id válido.');
        }

        $riskLevel = strtolower(trim((string) ($raw['risk_level'] ?? '')));
        if (! in_array($riskLevel, self::CANONICAL_RISK_LEVELS, true)) {
            throw new PredictiveRiskException("Nivel de riesgo no reconocido en la respuesta del Hub: '{$riskLevel}'.");
        }

        if (! isset($raw['risk_score']) || ! is_numeric($raw['risk_score'])) {
            throw new PredictiveRiskException('Respuesta de inferencia sin risk_score numérico.');
        }
        $riskScore = round((float) $raw['risk_score'], 4);
        if ($riskScore < 0.0 || $riskScore > 1.0) {
            throw new PredictiveRiskException('El campo risk_score debe estar entre 0.0 y 1.0.');
        }

        $riskClass = filter_var($raw['risk_class'] ?? null, FILTER_VALIDATE_INT);
        if ($riskClass === false || $riskClass < 0 || $riskClass > 2) {
            $riskClass = match ($riskLevel) {
                'high' => 2,
                'moderate' => 1,
                default => 0,
            };
        }

        $rawProbs = is_array($raw['probabilities'] ?? null) ? $raw['probabilities'] : [];
        $probabilities = [
            'low' => round((float) ($rawProbs['low'] ?? 0.0), 4),
            'moderate' => round((float) ($rawProbs['moderate'] ?? 0.0), 4),
            'high' => round((float) ($rawProbs['high'] ?? $riskScore), 4),
        ];

        $modelVersion = is_string($raw['model_version'] ?? null) && trim($raw['model_version']) !== ''
            ? trim($raw['model_version'])
            : ($fallbackModelVersion ?? 'unknown');

        $predictedAt = isset($raw['predicted_at'])
            ? $this->parseTimestamp($raw['predicted_at'])
            : ($fallbackPredictedAt ?? CarbonImmutable::now());

        return [
            'neighborhood_id' => $neighborhoodId,
            'risk_class' => $riskClass,
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'probabilities' => $probabilities,
            'model_version' => $modelVersion,
            'predicted_at' => $predictedAt,
        ];
    }

    private function parseTimestamp(mixed $raw): CarbonImmutable
    {
        if (! is_string($raw) || trim($raw) === '') {
            return CarbonImmutable::now();
        }

        try {
            return CarbonImmutable::parse($raw);
        } catch (Throwable $exception) {
            throw new PredictiveRiskException('El campo predicted_at no tiene un formato ISO-8601 válido.', previous: $exception);
        }
    }
}
