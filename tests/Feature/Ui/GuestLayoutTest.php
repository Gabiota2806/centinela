<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

class GuestLayoutTest extends TestCase
{
    public function test_login_muestra_el_branding_de_centinela_en_pantalla_dividida(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Centinela');
        $response->assertSee('data-testid="brand-panel"', false);
        $response->assertSee('data-testid="guest-card"', false);
    }

    public function test_login_no_muestra_el_logo_de_laravel(): void
    {
        $response = $this->get('/login');

        // Primer trazo del isotipo de Laravel que trae Breeze por defecto.
        $response->assertDontSee('M305.8 81.125', false);
    }

    public function test_login_no_carga_tipografias_desde_cdns_externos(): void
    {
        $response = $this->get('/login');

        $response->assertDontSee('fonts.bunny.net', false);
        $response->assertDontSee('fonts.googleapis.com', false);
    }
}
