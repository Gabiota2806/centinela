<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Database\Seeders\NeighborhoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GeoSpatialNeighborhoodsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_receive_401_json(): void
    {
        $this->getJson('/api/v1/geo/neighborhoods')
            ->assertUnauthorized();

        $this->get('/api/v1/geo/neighborhoods')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_admission_role_is_forbidden_while_clinical_and_director_roles_can_read(): void
    {
        Neighborhood::factory()->create(['name' => 'Centro']);

        $admission = User::factory()->admission()->create();
        $director = User::factory()->director()->create();
        $doctor = User::factory()->doctor()->create();
        $nurse = User::factory()->triageNurse()->create();

        $this->actingAs($admission)
            ->getJson('/api/v1/geo/neighborhoods')
            ->assertForbidden();

        foreach ([$director, $doctor, $nurse] as $authorizedUser) {
            $this->actingAs($authorizedUser)
                ->getJson('/api/v1/geo/neighborhoods')
                ->assertOk()
                ->assertJsonPath('type', 'FeatureCollection')
                ->assertJsonCount(1, 'features');
        }
    }

    public function test_returns_valid_geojson_with_7d_febrile_counts_centroids_and_zero_pii(): void
    {
        $director = User::factory()->director()->create();
        $doctor = User::factory()->doctor()->create();

        $highRiskBarrio = Neighborhood::factory()->create([
            'name' => 'San Miguel',
            'risk_level' => 'alto',
            'risk_score' => 0.8421,
            'risk_updated_at' => '2026-11-28 15:35:00',
        ]);
        $normalBarrio = Neighborhood::factory()->create([
            'name' => 'Centro',
            'risk_level' => 'normal',
            'risk_score' => null,
            'risk_updated_at' => null,
        ]);

        $patient = Patient::factory()->create([
            'dni' => '38999111',
            'first_name' => 'Juan Carlos',
            'last_name' => 'Perez Confidencial',
            'phone' => '3704123456',
            'address' => 'Av. 25 de Mayo 999',
            'neighborhood_id' => $highRiskBarrio->id,
        ]);

        // 3 consultas febriles dentro de los últimos 7 días
        foreach (['sospecha_dengue', 'sindrome_febril_inespecifico', 'ira'] as $febrileDiagnosis) {
            $triage = Triage::factory()->create(['patient_id' => $patient->id]);
            Consultation::factory()->create([
                'triage_id' => $triage->id,
                'doctor_id' => $doctor->id,
                'diagnosis' => $febrileDiagnosis,
                'status' => ConsultationStatus::COMPLETED,
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subDays(2),
            ]);
        }

        // 1 consulta no febril reciente (suma a total_consultations_7d, no a febrile_cases_7d)
        $nonFebrileTriage = Triage::factory()->create(['patient_id' => $patient->id]);
        Consultation::factory()->create([
            'triage_id' => $nonFebrileTriage->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'gastroenteritis',
            'status' => ConsultationStatus::COMPLETED,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        // 1 consulta febril antigua (> 7 días) que debe quedar excluida
        $oldTriage = Triage::factory()->create(['patient_id' => $patient->id]);
        Consultation::factory()->create([
            'triage_id' => $oldTriage->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'sospecha_dengue',
            'status' => ConsultationStatus::COMPLETED,
            'started_at' => now()->subDays(12),
            'completed_at' => now()->subDays(12),
        ]);

        $response = $this->actingAs($director)
            ->getJson('/api/v1/geo/neighborhoods')
            ->assertOk()
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'id',
                        'geometry' => ['type', 'coordinates'],
                        'properties' => [
                            'id',
                            'name',
                            'risk_level',
                            'risk_score',
                            'febrile_cases_7d',
                            'total_consultations_7d',
                            'risk_updated_at',
                            'centroid_lat',
                            'centroid_lng',
                        ],
                    ],
                ],
            ]);

        $payload = $response->json();
        $featuresById = collect($payload['features'])->keyBy('id');

        $sanMiguel = $featuresById->get($highRiskBarrio->id);
        $this->assertNotNull($sanMiguel);
        $this->assertSame('Feature', $sanMiguel['type']);
        $this->assertSame('Polygon', $sanMiguel['geometry']['type']);
        $this->assertSame('San Miguel', $sanMiguel['properties']['name']);
        $this->assertSame('high', $sanMiguel['properties']['risk_level']);
        $this->assertEqualsWithDelta(0.8421, $sanMiguel['properties']['risk_score'], 0.0001);
        $this->assertSame(3, $sanMiguel['properties']['febrile_cases_7d']);
        $this->assertSame(4, $sanMiguel['properties']['total_consultations_7d']);
        $this->assertStringEndsWith('-03:00', (string) $sanMiguel['properties']['risk_updated_at']);
        $this->assertEqualsWithDelta(-26.185, $sanMiguel['properties']['centroid_lat'], 0.01);
        $this->assertEqualsWithDelta(-58.175, $sanMiguel['properties']['centroid_lng'], 0.01);

        $centro = $featuresById->get($normalBarrio->id);
        $this->assertNotNull($centro);
        $this->assertSame('low', $centro['properties']['risk_level']);
        $this->assertNull($centro['properties']['risk_score']);
        $this->assertNull($centro['properties']['risk_updated_at']);
        $this->assertSame(0, $centro['properties']['febrile_cases_7d']);
        $this->assertSame(0, $centro['properties']['total_consultations_7d']);

        // Ley N° 25.326: nunca exponer datos identificatorios de pacientes en la capa GeoJSON
        $rawContent = (string) $response->getContent();
        $this->assertStringNotContainsString('38999111', $rawContent);
        $this->assertStringNotContainsString('Juan Carlos', $rawContent);
        $this->assertStringNotContainsString('Perez Confidencial', $rawContent);
        $this->assertStringNotContainsString('3704123456', $rawContent);
        $this->assertStringNotContainsString('Av. 25 de Mayo 999', $rawContent);
    }

    public function test_serves_all_seeded_formosa_neighborhoods_in_a_single_postgis_query(): void
    {
        $this->seed(NeighborhoodSeeder::class);
        $expectedCount = count(NeighborhoodSeeder::barrios());
        $director = User::factory()->director()->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($director)
            ->getJson('/api/v1/geo/neighborhoods')
            ->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $spatialQueries = array_values(array_filter(
            $queries,
            static fn (array $entry): bool => str_contains($entry['query'], 'ST_AsGeoJSON'),
        ));

        $this->assertCount(1, $spatialQueries, 'El endpoint debe resolver toda la colección en una única consulta PostGIS sin N+1.');
        $this->assertCount($expectedCount, $response->json('features'));
    }
}
