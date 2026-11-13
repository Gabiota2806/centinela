<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Neighborhood;
use Database\Seeders\NeighborhoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CEN-36: Seeder de barrios de Formosa Capital con delimitación poligonal PostGIS (SRID 4326).
 */
class NeighborhoodSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_neighborhood_seeder_populates_official_formosa_neighborhoods(): void
    {
        $exitCode = Artisan::call('db:seed', [
            '--class' => NeighborhoodSeeder::class,
        ]);

        $this->assertSame(0, $exitCode);

        // Se verifica que se hayan cargado todos los barrios oficiales de la capital (al menos 80 barrios)
        $total = Neighborhood::count();
        $this->assertGreaterThanOrEqual(80, $total);

        // Validar presencia de barrios clave especificados en los requerimientos
        $keyNeighborhoods = [
            'Lote 4',
            'Bernardino Rivadavia',
            'San Martín',
            'Don Bosco',
            'Eva Perón',
            'Fontana',
            'San Miguel',
            'Circuito Cinco',
            'Guadalupe',
            'Centro',
            'República Argentina',
            'Simón Bolívar',
            'Antenor Gauna',
            '8 de Octubre',
            'La Nueva Formosa',
            'Villa Hermosa',
            'Liborsi',
            'San Francisco',
            'San Pedro',
            'Mariano Moreno',
            'Obrero',
            'El Porvenir',
            '1° de Mayo',
            '7 de Mayo',
            'Namqom',
        ];

        foreach ($keyNeighborhoods as $name) {
            $this->assertTrue(
                Neighborhood::where('name', $name)->exists(),
                "El barrio '{$name}' debe existir en la base de datos tras la ejecución del seeder."
            );
        }
    }

    public function test_all_seeded_neighborhood_geometries_are_valid_in_postgis_srid_4326(): void
    {
        Artisan::call('db:seed', [
            '--class' => NeighborhoodSeeder::class,
        ]);

        $invalidGeometries = DB::select(
            'SELECT id, name, ST_IsValid(geometry) as is_valid, ST_SRID(geometry) as srid, ST_GeometryType(geometry) as geom_type FROM neighborhoods WHERE ST_IsValid(geometry) = false OR ST_SRID(geometry) != 4326'
        );

        $this->assertEmpty(
            $invalidGeometries,
            'Todos los polígonos de barrios deben ser geométricamente válidos según PostGIS y estar en SRID 4326.'
        );
    }

    public function test_neighborhood_seeder_is_idempotent(): void
    {
        // Primera ejecución
        Artisan::call('db:seed', ['--class' => NeighborhoodSeeder::class]);
        $countFirstRun = Neighborhood::count();

        // Segunda ejecución
        $exitCode = Artisan::call('db:seed', ['--class' => NeighborhoodSeeder::class]);
        $this->assertSame(0, $exitCode);

        $countSecondRun = Neighborhood::count();
        $this->assertSame($countFirstRun, $countSecondRun, 'El seeder debe ser idempotente y no duplicar registros.');
    }

    public function test_spatial_query_matches_point_inside_seeded_neighborhood(): void
    {
        Artisan::call('db:seed', ['--class' => NeighborhoodSeeder::class]);

        // Verificamos que contenga puntos representativos dentro de barrios oficiales
        $lote4 = Neighborhood::where('name', 'Lote 4')->firstOrFail();
        
        // Obtenemos el centroide del polígono cargado y validamos que containingPoint lo resuelva
        $centroid = DB::selectOne(
            'SELECT ST_X(ST_Centroid(geometry)) as lon, ST_Y(ST_Centroid(geometry)) as lat FROM neighborhoods WHERE id = ?',
            [$lote4->id]
        );

        $matched = Neighborhood::containingPoint((float) $centroid->lat, (float) $centroid->lon)->first();
        $this->assertNotNull($matched);
        $this->assertSame($lote4->id, $matched->id);
    }
}
