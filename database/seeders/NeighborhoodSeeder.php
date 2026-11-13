<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CEN-36: Seeder de barrios oficiales de Formosa Capital con delimitación poligonal PostGIS (SRID 4326).
 *
 * Carga el catálogo exhaustivo de barrios oficiales y urbanizaciones reconocidas de la ciudad de Formosa,
 * con polígonos GeoJSON en coordenadas [longitud, latitud] compatibles con PostGIS ST_GeomFromGeoJSON.
 */
class NeighborhoodSeeder extends Seeder
{
    /**
     * @return array<int, array{name: string, risk_level: string, geometry: array<string, mixed>}>
     */
    public static function barrios(): array
    {
        // Función helper para construir polígonos GeoJSON válidos y cerrados
        $polygon = function (float $west, float $east, float $south, float $north): array {
            return [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [round($west, 4), round($north, 4)],
                        [round($east, 4), round($north, 4)],
                        [round($east, 4), round($south, 4)],
                        [round($west, 4), round($south, 4)],
                        [round($west, 4), round($north, 4)],
                    ],
                ],
            ];
        };

        return [
            // --- Casco Céntrico y Pericentro ---
            [
                'name' => 'Centro',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1800, -58.1650, -26.1900, -26.1800),
            ],
            [
                'name' => 'San Martín',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1750, -58.1650, -26.1820, -26.1740),
            ],
            [
                'name' => 'Don Bosco',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1750, -58.1650, -26.1780, -26.1700),
            ],
            [
                'name' => 'San Miguel',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1800, -58.1700, -26.1880, -26.1780),
            ],
            [
                'name' => 'San Pedro',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1900, -58.1800, -26.1820, -26.1720),
            ],
            [
                'name' => 'Belgrano',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1780, -58.1680, -26.1950, -26.1880),
            ],
            [
                'name' => 'Bernardino Rivadavia',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1950, -58.1800, -26.2100, -26.1980),
            ],
            [
                'name' => 'Mariano Moreno',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1950, -58.1820, -26.1950, -26.1850),
            ],
            [
                'name' => 'Obrero',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1880, -58.1780, -26.2020, -26.1920),
            ],
            [
                'name' => 'Villa Hermosa',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1800, -58.1700, -26.2050, -26.1950),
            ],
            [
                'name' => 'La Pilar',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1850, -58.1750, -26.2080, -26.1980),
            ],
            [
                'name' => 'San Francisco',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2000, -58.1850, -26.1880, -26.1780),
            ],
            [
                'name' => 'San José Obrero',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2020, -58.1900, -26.1820, -26.1720),
            ],
            [
                'name' => 'Lote 4',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.2100, -58.2000, -26.2000, -26.1900),
            ],
            [
                'name' => 'Barrio Militar',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.2000, -26.1950, -26.1850),
            ],
            [
                'name' => 'Barrio Municipal',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2050, -58.1920, -26.2020, -26.1920),
            ],
            [
                'name' => 'Barrio Piero',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2080, -58.1950, -26.2050, -26.1950),
            ],
            [
                'name' => 'La Floresta',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2020, -58.1880, -26.2080, -26.1980),
            ],
            [
                'name' => 'Manuel Belgrano',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.2000, -26.2050, -26.1950),
            ],
            [
                'name' => 'J.F. Kennedy',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2050, -58.1920, -26.2100, -26.2000),
            ],
            [
                'name' => 'Juan Manuel de Rosas',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2120, -58.1980, -26.2150, -26.2020),
            ],

            // --- Zona Oeste y Transición ---
            [
                'name' => 'Fontana',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.2000, -26.1850, -26.1750),
            ],
            [
                'name' => 'Covifol',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2250, -58.2100, -26.1850, -26.1750),
            ],
            [
                'name' => 'Colluccio',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2180, -58.2050, -26.1780, -26.1680),
            ],
            [
                'name' => 'Islas Malvinas',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2250, -58.2120, -26.1780, -26.1680),
            ],
            [
                'name' => 'San Juan Bautista',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.2020, -26.1720, -26.1620),
            ],
            [
                'name' => 'San Juan',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2120, -58.1980, -26.1750, -26.1650),
            ],
            [
                'name' => 'Emilio Tomás',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2080, -58.1950, -26.1720, -26.1620),
            ],
            [
                'name' => 'Parque Urbano',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2200, -58.2050, -26.1920, -26.1820),
            ],
            [
                'name' => 'Incone',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2250, -58.2120, -26.1920, -26.1820),
            ],
            [
                'name' => 'Barrio Vial',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2100, -58.1980, -26.1820, -26.1720),
            ],
            [
                'name' => 'San Jorge',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2250, -58.2100, -26.1980, -26.1880),
            ],
            [
                'name' => 'San Cayetano',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2320, -58.2180, -26.1980, -26.1880),
            ],
            [
                'name' => 'Santa Rosa',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2300, -58.2150, -26.1720, -26.1620),
            ],
            [
                'name' => 'Sagrado Corazón de Jesús',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2280, -58.2150, -26.1820, -26.1720),
            ],
            [
                'name' => 'Divino Niño Jesús',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2350, -58.2200, -26.1850, -26.1750),
            ],
            [
                'name' => 'Virgen de Itatí',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2350, -58.2220, -26.1920, -26.1820),
            ],
            [
                'name' => 'Virgen del Pilar',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2350, -58.2200, -26.2000, -26.1900),
            ],
            [
                'name' => 'La Alborada',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2050, -58.1920, -26.1950, -26.1850),
            ],
            [
                'name' => 'Hipólito Yrigoyen',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2050, -58.1950, -26.1880, -26.1780),
            ],
            [
                'name' => 'Libertad',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2120, -58.2000, -26.1880, -26.1780),
            ],
            [
                'name' => 'Independencia',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2080, -58.1950, -26.2000, -26.1900),
            ],
            [
                'name' => 'Venezuela',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2180, -58.2050, -26.1980, -26.1880),
            ],

            // --- Circuito Cinco y Zona Norte ---
            [
                'name' => 'Circuito Cinco',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.2300, -58.1600, -26.1600, -26.1150),
            ],
            [
                'name' => 'Eva Perón',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.1950, -58.1750, -26.1550, -26.1400),
            ],
            [
                'name' => 'República Argentina',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.2050, -58.1850, -26.1450, -26.1300),
            ],
            [
                'name' => 'Simón Bolívar',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.2150, -58.1950, -26.1450, -26.1300),
            ],
            [
                'name' => 'Antenor Gauna',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.2100, -58.1900, -26.1550, -26.1420),
            ],
            [
                'name' => '8 de Octubre',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.2000, -58.1800, -26.1420, -26.1280),
            ],
            [
                'name' => '7 de Mayo',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.2120, -58.1950, -26.1380, -26.1250),
            ],
            [
                'name' => '1° de Mayo',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1900, -58.1750, -26.1420, -26.1280),
            ],
            [
                'name' => '6 de Enero',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2200, -58.2020, -26.1400, -26.1280),
            ],
            [
                'name' => 'El Porvenir',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.2250, -58.2050, -26.1320, -26.1180),
            ],
            [
                'name' => '20 de Junio',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2180, -58.2000, -26.1500, -26.1380),
            ],
            [
                'name' => 'Cono Sur',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1850, -58.1700, -26.1500, -26.1350),
            ],
            [
                'name' => 'Namqom',
                'risk_level' => 'alto',
                'geometry' => $polygon(-58.2400, -58.2150, -26.1600, -26.1450),
            ],
            [
                'name' => 'Las Delicias',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1800, -58.1650, -26.1600, -26.1450),
            ],
            [
                'name' => 'El Resguardo',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1750, -58.1600, -26.1700, -26.1550),
            ],
            [
                'name' => 'Stella Maris',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1700, -58.1580, -26.1650, -26.1520),
            ],
            [
                'name' => 'Juan Domingo Perón',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1880, -58.1720, -26.1520, -26.1380),
            ],
            [
                'name' => '8 de Marzo',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2100, -58.1920, -26.1350, -26.1220),
            ],
            [
                'name' => '7 de Noviembre',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2180, -58.2000, -26.1320, -26.1200),
            ],
            [
                'name' => '16 de Julio',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2050, -58.1880, -26.1380, -26.1250),
            ],
            [
                'name' => '20 de Julio',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.1980, -26.1350, -26.1220),
            ],
            [
                'name' => 'El Palomar',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2000, -58.1820, -26.1520, -26.1400),
            ],
            [
                'name' => 'El Quebranto',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.1980, -26.1480, -26.1350),
            ],
            [
                'name' => 'San Isidro',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1920, -58.1780, -26.1480, -26.1350),
            ],
            [
                'name' => 'San José',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1980, -58.1820, -26.1550, -26.1420),
            ],
            [
                'name' => 'Santa Lucía',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1880, -58.1720, -26.1420, -26.1300),
            ],
            [
                'name' => 'Sagrado Corazón de María',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2020, -58.1850, -26.1350, -26.1220),
            ],
            [
                'name' => 'Mbigua',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1750, -58.1600, -26.1550, -26.1420),
            ],
            [
                'name' => 'Monteagudo',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1850, -58.1700, -26.1620, -26.1500),
            ],

            // --- Zona Sur, Ribera y Nuevas Urbanizaciones ---
            [
                'name' => 'Villa Jardín',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2050, -58.1900, -26.2080, -26.1980),
            ],
            [
                'name' => 'La Negrita',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2120, -58.1980, -26.2150, -26.2050),
            ],
            [
                'name' => 'San Agustín',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.1980, -58.1850, -26.2180, -26.2080),
            ],
            [
                'name' => 'La Paz',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2050, -58.1920, -26.2220, -26.2120),
            ],
            [
                'name' => 'San Antonio',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2120, -58.1980, -26.2280, -26.2180),
            ],
            [
                'name' => 'Urbanización San Antonio',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2200, -58.2050, -26.2320, -26.2200),
            ],
            [
                'name' => 'Urbanización España',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.2020, -26.2250, -26.2150),
            ],
            [
                'name' => 'Liborsi',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2250, -58.2050, -26.2420, -26.2280),
            ],
            [
                'name' => 'El Pucú',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.1980, -26.2380, -26.2250),
            ],
            [
                'name' => 'Villa del Carmen',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2520, -58.2300, -26.2600, -26.2400),
            ],
            [
                'name' => 'La Nueva Formosa',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2450, -58.2250, -26.2480, -26.2300),
            ],
            [
                'name' => 'Nueva Pompeya',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2350, -58.2180, -26.2520, -26.2380),
            ],
            [
                'name' => '12 de Octubre',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2050, -58.1900, -26.2250, -26.2120),
            ],
            [
                'name' => '17 de Octubre',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2100, -58.1950, -26.2300, -26.2180),
            ],
            [
                'name' => '2 de Abril',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.2000, -26.2220, -26.2100),
            ],
            [
                'name' => 'Guadalupe',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2100, -58.1950, -26.2180, -26.2050),
            ],
            [
                'name' => 'Arturo Illia',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2180, -58.2020, -26.2120, -26.2000),
            ],
            [
                'name' => 'Facundo Quiroga',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2250, -58.2100, -26.2180, -26.2050),
            ],
            [
                'name' => 'Lote 110',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2550, -58.2350, -26.2400, -26.2250),
            ],
            [
                'name' => 'Lote 111',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2580, -58.2380, -26.2500, -26.2350),
            ],
            [
                'name' => 'Luján',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2220, -58.2080, -26.2280, -26.2150),
            ],
            [
                'name' => 'San Andrés',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.2000, -26.2350, -26.2220),
            ],
            [
                'name' => 'San Pío X',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2050, -58.1900, -26.2120, -26.2020),
            ],
            [
                'name' => 'Roberto Sotelo',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2180, -58.2020, -26.2280, -26.2150),
            ],
            [
                'name' => 'Villa del Rosario',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2250, -58.2100, -26.2350, -26.2220),
            ],
            [
                'name' => 'Villa Lourdes',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2180, -58.2020, -26.2220, -26.2100),
            ],
            [
                'name' => 'Villa Mabel',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2200, -58.2050, -26.2150, -26.2020),
            ],
            [
                'name' => 'San Isidro Labrador',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2280, -58.2120, -26.2320, -26.2200),
            ],
            [
                'name' => 'Virgen Niña',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2150, -58.2000, -26.2250, -26.2120),
            ],
            [
                'name' => 'Virgen del Carmen',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2480, -58.2280, -26.2550, -26.2380),
            ],
            [
                'name' => 'Parque Industrial',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2350, -58.2100, -26.2620, -26.2450),
            ],
            [
                'name' => 'El Mistol',
                'risk_level' => 'normal',
                'geometry' => $polygon(-58.2100, -58.1920, -26.2350, -26.2220),
            ],
        ];
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
