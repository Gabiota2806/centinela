<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint geoespacial PostGIS de barrios de Formosa Capital (CEN-63 / RF-GEO-11).
 *
 * Entrega una colección GeoJSON FeatureCollection utilizando ST_AsGeoJSON() y ST_Centroid()
 * en una única consulta agregada sin N+1 ni exposición de datos personales (Ley N° 25.326).
 */
class GeoSpatialController extends Controller
{
    /**
     * Diagnósticos presuntivos canónicos de vigilancia febril (docs/specs/00_contracts.md §5.1).
     *
     * @var list<string>
     */
    private const FEBRILE_DIAGNOSES = [
        'sindrome_febril_inespecifico',
        'sospecha_dengue',
        'ira',
    ];

    /**
     * Retorna la capa GeoJSON de todos los barrios con riesgo predictivo y conteos febriles (7 días).
     */
    public function neighborhoods(): JsonResponse
    {
        $windowStart = CarbonImmutable::now()->subDays(7)->toDateTimeString();
        $displayTimezone = (string) config('centinela.timezone', 'America/Argentina/Cordoba');

        $febrilePlaceholders = implode(', ', array_fill(0, count(self::FEBRILE_DIAGNOSES), '?'));

        $rows = DB::select(
            "SELECT
                n.id,
                n.name,
                n.risk_level,
                n.risk_score,
                n.risk_updated_at,
                ST_AsGeoJSON(n.geometry) AS geometry_geojson,
                ST_Y(ST_Centroid(n.geometry)) AS centroid_lat,
                ST_X(ST_Centroid(n.geometry)) AS centroid_lng,
                COALESCE(stats.febrile_cases_7d, 0) AS febrile_cases_7d,
                COALESCE(stats.total_consultations_7d, 0) AS total_consultations_7d
            FROM neighborhoods n
            LEFT JOIN (
                SELECT
                    p.neighborhood_id,
                    COUNT(c.id) AS total_consultations_7d,
                    COUNT(c.id) FILTER (WHERE c.diagnosis IN ({$febrilePlaceholders})) AS febrile_cases_7d
                FROM consultations c
                INNER JOIN triages t ON t.id = c.triage_id
                INNER JOIN patients p ON p.id = t.patient_id
                WHERE p.neighborhood_id IS NOT NULL
                  AND COALESCE(c.completed_at, c.started_at, c.created_at) >= ?
                GROUP BY p.neighborhood_id
            ) stats ON stats.neighborhood_id = n.id
            ORDER BY n.id ASC",
            [...self::FEBRILE_DIAGNOSES, $windowStart],
        );

        $features = [];

        foreach ($rows as $row) {
            $geometry = is_string($row->geometry_geojson)
                ? json_decode($row->geometry_geojson, true, 512, JSON_THROW_ON_ERROR)
                : null;

            $riskUpdatedAt = $row->risk_updated_at !== null
                ? CarbonImmutable::parse((string) $row->risk_updated_at)
                    ->setTimezone($displayTimezone)
                    ->toIso8601String()
                : null;

            $id = (int) $row->id;

            $features[] = [
                'type' => 'Feature',
                'id' => $id,
                'geometry' => $geometry,
                'properties' => [
                    'id' => $id,
                    'name' => (string) $row->name,
                    'risk_level' => $this->normalizeRiskLevel($row->risk_level),
                    'risk_score' => $row->risk_score !== null ? round((float) $row->risk_score, 4) : null,
                    'febrile_cases_7d' => (int) $row->febrile_cases_7d,
                    'total_consultations_7d' => (int) $row->total_consultations_7d,
                    'risk_updated_at' => $riskUpdatedAt,
                    'centroid_lat' => $row->centroid_lat !== null ? round((float) $row->centroid_lat, 6) : null,
                    'centroid_lng' => $row->centroid_lng !== null ? round((float) $row->centroid_lng, 6) : null,
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    private function normalizeRiskLevel(mixed $raw): string
    {
        $normalized = strtolower(trim((string) $raw));

        if (in_array($normalized, ['high', 'alto', 'alta', 'critical', 'critico', 'crítico'], true)) {
            return 'high';
        }

        if (in_array($normalized, ['moderate', 'moderado', 'moderada', 'medium', 'medio', 'media'], true)) {
            return 'moderate';
        }

        return 'low';
    }
}
