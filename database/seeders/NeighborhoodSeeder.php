<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeder de barrios oficiales de Formosa Capital con contornos poligonales PostGIS (SRID 4326).
 *
 * Carga el catálogo cartográfico oficial desde `database/data/formosa_neighborhoods.geojson`
 * (polígonos orgánicos disjuntos, 0 % de solapamiento, válidos en PostGIS y aptos para operación offline-first).
 */
class NeighborhoodSeeder extends Seeder
{
    private const GEOJSON_RELATIVE_PATH = 'data/formosa_neighborhoods.geojson';

    /**
     * @return array<int, array{name: string, risk_level: string, geometry: array<string, mixed>}>
     */
    public static function barrios(): array
    {
        $path = database_path(self::GEOJSON_RELATIVE_PATH);

        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el archivo cartográfico de barrios en: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'FeatureCollection' || ! is_array($decoded['features'] ?? null)) {
            throw new RuntimeException('El archivo formosa_neighborhoods.geojson no contiene un FeatureCollection válido.');
        }

        $barrios = [];

        foreach ($decoded['features'] as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $geometry = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;
            $name = isset($properties['name']) ? trim((string) $properties['name']) : '';

            if ($name === '' || $geometry === null) {
                continue;
            }

            $barrios[] = [
                'name' => $name,
                'risk_level' => (string) ($properties['risk_level'] ?? 'normal'),
                'geometry' => $geometry,
            ];
        }

        return $barrios;
    }

    public function run(): void
    {
        $barrios = self::barrios();

        foreach ($barrios as $item) {
            $name = $item['name'];
            $riskLevel = $item['risk_level'];
            $geojson = json_encode($item['geometry'], JSON_THROW_ON_ERROR);

            $existing = DB::table('neighborhoods')->where('name', $name)->first();

            if ($existing) {
                DB::statement('
                    UPDATE neighborhoods
                    SET geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326),
                        risk_level = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ', [$geojson, $riskLevel, $existing->id]);
            } else {
                DB::statement('
                    INSERT INTO neighborhoods (name, geometry, risk_level, created_at, updated_at)
                    VALUES (?, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), ?, NOW(), NOW())
                ', [$name, $geojson, $riskLevel]);
            }
        }
    }
}
