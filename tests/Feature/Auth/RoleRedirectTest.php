<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole, string}>
     */
    public static function rolesYDestinos(): array
    {
        return [
            'admisión' => [UserRole::ADMISSION, '/pacientes'],
            'enfermería de triage' => [UserRole::TRIAGE_NURSE, '/triage'],
            'médico de guardia' => [UserRole::DOCTOR, '/consultas'],
            'dirección' => [UserRole::DIRECTOR, '/dashboard'],
        ];
    }

    #[DataProvider('rolesYDestinos')]
    public function test_cada_rol_es_redirigido_a_su_pantalla_despues_del_login(UserRole $role, string $destino): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect($destino);
    }

    public function test_enfermero_de_triage_ve_su_nombre_y_rol_al_ingresar(): void
    {
        $user = User::factory()->triageNurse()->create(['name' => 'Lucía Gómez']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/triage');

        $this->get('/triage')
            ->assertOk()
            ->assertSee('Lucía Gómez')
            ->assertSee('Enfermero de Triage');
    }
}
