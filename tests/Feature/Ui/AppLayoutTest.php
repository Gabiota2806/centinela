<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

class AppLayoutTest extends TestCase
{
    private function usuario(): User
    {
        // Usuario en memoria: el layout no necesita persistencia.
        return User::factory()->make(['id' => 1, 'name' => 'Lucía Gómez']);
    }

    public function test_el_layout_muestra_el_nodo_caps_y_el_perfil_activo(): void
    {
        config(['centinela.node_name' => 'CAPS B° San Miguel']);

        $response = $this->actingAs($this->usuario())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('CAPS B° San Miguel');
        $response->assertSee('Lucía Gómez');
    }

    public function test_el_layout_muestra_la_etiqueta_del_rol(): void
    {
        $user = User::factory()->director()->make(['id' => 1]);

        $this->actingAs($user)->get('/dashboard')->assertSee('Dirección de CAPS');
    }

    public function test_el_layout_incluye_la_barra_lateral_colapsable(): void
    {
        $response = $this->actingAs($this->usuario())->get('/dashboard');

        $response->assertSee('data-testid="sidebar"', false);
        $response->assertSee('data-testid="sidebar-collapse"', false);
        $response->assertSee('data-testid="sidebar-open"', false);
    }

    public function test_la_barra_lateral_oculta_items_cuya_ruta_no_existe(): void
    {
        config(['centinela.navigation' => [
            ['label' => 'Inicio', 'route' => 'dashboard', 'icon' => 'home'],
            ['label' => 'Módulo futuro', 'route' => 'modulo.inexistente', 'icon' => 'activity'],
        ]]);

        $response = $this->actingAs($this->usuario())->get('/dashboard');

        $response->assertSee('Inicio');
        $response->assertDontSee('Módulo futuro');
    }

    public function test_el_layout_no_muestra_el_logo_de_laravel(): void
    {
        $response = $this->actingAs($this->usuario())->get('/dashboard');

        $response->assertDontSee('M305.8 81.125', false);
    }
}
