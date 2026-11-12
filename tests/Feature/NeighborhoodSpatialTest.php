<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CEN-16: Pruebas de integración espacial de barrios de Formosa Capital con PostGIS.
 */
class NeighborhoodSpatialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica que un punto geográfico (lat/lng en Formosa Capital) se asocie
     * correctamente a su polígono de barrio mediante funciones espaciales de PostGIS.
     */
    public function test_point_inside_formosa_neighborhood_polygon_matches_correctly(): void
    {
        // Polígono representativo del Barrio Lote 4 (zona CAPS Dr. Luis María Codda, Formosa Capital)
        $lote4 = Neighborhood::factory()->create([
            'name' => 'Lote 4',
            'geometry' => DB::raw(
                "ST_SetSRID(ST_GeomFromText('POLYGON((-58.210 -26.190, -58.200 -26.190, -58.200 -26.200, -58.210 -26.200, -58.210 -26.190))'), 4326)"
            ),
            'risk_level' => 'alto',
        ]);

        // Polígono representativo del Barrio San Miguel (Formosa Capital)
        $sanMiguel = Neighborhood::factory()->create([
            'name' => 'San Miguel',
            'geometry' => DB::raw(
                "ST_SetSRID(ST_GeomFromText('POLYGON((-58.180 -26.178, -58.170 -26.178, -58.170 -26.188, -58.180 -26.188, -58.180 -26.178))'), 4326)"
            ),
            'risk_level' => 'normal',
        ]);

        // Punto dentro de Lote 4 (lat: -26.195, lng: -58.205)
        $matchedLote4 = Neighborhood::containingPoint(-26.195, -58.205)->first();
        $this->assertNotNull($matchedLote4);
        $this->assertSame($lote4->id, $matchedLote4->id);
        $this->assertSame('Lote 4', $matchedLote4->name);

        // Punto dentro de San Miguel (lat: -26.182, lng: -58.175)
        $matchedSanMiguel = Neighborhood::containingPoint(-26.182, -58.175)->first();
        $this->assertNotNull($matchedSanMiguel);
        $this->assertSame($sanMiguel->id, $matchedSanMiguel->id);
        $this->assertSame('San Miguel', $matchedSanMiguel->name);
    }

    /**
     * Verifica que una coordenada fuera de los polígonos registrados no retorne falsos positivos.
     */
    public function test_point_outside_all_polygons_returns_no_neighborhood(): void
    {
        Neighborhood::factory()->create([
            'name' => 'Lote 4',
            'geometry' => DB::raw(
                "ST_SetSRID(ST_GeomFromText('POLYGON((-58.210 -26.190, -58.200 -26.190, -58.200 -26.200, -58.210 -26.200, -58.210 -26.190))'), 4326)"
            ),
        ]);

        // Coordenada alejada fuera del polígono (ej. zona rural / otro sector)
        $outsideMatch = Neighborhood::containingPoint(-26.120, -58.150)->first();

        $this->assertNull($outsideMatch);
    }

    /**
     * Verifica que un paciente pueda asociarse al barrio resuelto espacialmente por PostGIS y valide SRID 4326.
     */
    public function test_patient_belongs_to_neighborhood_resolved_by_postgis_point(): void
    {
        Neighborhood::factory()->create([
            'name' => 'Don Bosco',
            'geometry' => DB::raw(
                "ST_SetSRID(ST_GeomFromText('POLYGON((-58.175 -26.170, -58.165 -26.170, -58.165 -26.178, -58.175 -26.178, -58.175 -26.170))'), 4326)"
            ),
        ]);

        $resolvedNeighborhood = Neighborhood::containingPoint(-26.174, -58.170)->firstOrFail();

        $patient = Patient::factory()->create([
            'neighborhood_id' => $resolvedNeighborhood->id,
        ]);

        $this->assertSame('Don Bosco', $patient->neighborhood->name);

        $srid = DB::selectOne(
            'SELECT ST_SRID(geometry) AS srid FROM neighborhoods WHERE id = ?',
            [$resolvedNeighborhood->id]
        );

        $this->assertSame(4326, (int) $srid->srid);
    }
}
