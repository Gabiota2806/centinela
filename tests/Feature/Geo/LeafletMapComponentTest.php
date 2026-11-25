<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-60: Integración del visor geoespacial Leaflet.js sobre OpenStreetMap en el dashboard
 * (docs/specs/geo/spec.md RF-GEO-12 y docs/specs/00_contracts.md §4.3 y §6).
 */
class LeafletMapComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesConAccesoEpidemiologico(): array
    {
        return [
            'dirección de CAPS' => ['director'],
            'médico de guardia' => ['doctor'],
            'enfermería de triage' => ['triageNurse'],
        ];
    }

    #[DataProvider('rolesConAccesoEpidemiologico')]
    public function test_dashboard_muestra_el_visor_leaflet_para_roles_autorizados(string $roleState): void
    {
        $user = User::factory()->{$roleState}()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('data-centinela-map', false)
            ->assertSee('data-map-canvas', false)
            ->assertSee('data-center-lat="-26.18"', false)
            ->assertSee('data-center-lng="-58.17"', false)
            ->assertSee('data-zoom="13"', false)
            ->assertSee('data-endpoint="/api/v1/geo/neighborhoods"', false)
            ->assertSee('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', false)
            ->assertSee('Mapa Epidemiológico de Formosa Capital');
    }

    public function test_dashboard_responde_ok_para_admision_sin_montar_el_visor_epidemiologico(): void
    {
        $admission = User::factory()->admission()->create();

        $response = $this->actingAs($admission)->get('/dashboard');

        $response->assertOk()
            ->assertDontSee('data-centinela-map', false)
            ->assertDontSee('/api/v1/geo/neighborhoods', false)
            ->assertSee('Panel de inicio');
    }

    public function test_invitado_no_autenticado_es_redirigido_al_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_el_componente_leaflet_map_permite_configuracion_personalizada_y_aislamiento_visual(): void
    {
        $view = $this->blade(
            '<x-leaflet-map endpoint="/api/v1/geo/custom" :center-lat="-26.2" :center-lng="-58.2" :zoom="14" height-class="h-[520px]" />'
        );

        $view->assertSee('data-centinela-map', false)
            ->assertSee('data-endpoint="/api/v1/geo/custom"', false)
            ->assertSee('data-center-lat="-26.2"', false)
            ->assertSee('data-center-lng="-58.2"', false)
            ->assertSee('data-zoom="14"', false)
            ->assertSee('h-[520px]', false)
            ->assertSee('isolate', false)
            ->assertSee('role="region"', false)
            ->assertSee('data-map-recenter', false)
            ->assertSee('data-map-refresh', false);
    }

    public function test_el_visor_es_offline_first_sin_cdns_externos_ni_emojis(): void
    {
        $director = User::factory()->director()->create();
        $html = $this->actingAs($director)->get('/dashboard')->assertOk()->getContent();

        $this->assertIsString($html);
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html);
        $this->assertStringContainsString('<svg', $html);
    }
}
