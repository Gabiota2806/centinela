<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-16: Pruebas de integración de autenticación, redirección por rol y Gates RBAC.
 */
class AuthRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole, string, string}>
     */
    public static function rolesAndPostLoginRedirectsProvider(): array
    {
        return [
            'admission redirects to /pacientes' => [
                UserRole::ADMISSION,
                '/pacientes',
                'Admisión / Mesa de Entrada',
            ],
            'triage_nurse redirects to /triage' => [
                UserRole::TRIAGE_NURSE,
                '/triage',
                'Enfermero de Triage',
            ],
            'doctor redirects to /consultas' => [
                UserRole::DOCTOR,
                '/consultas',
                'Médico de Guardia',
            ],
            'director redirects to /dashboard' => [
                UserRole::DIRECTOR,
                '/dashboard',
                'Dirección de CAPS',
            ],
        ];
    }

    /**
     * Valida que los 4 roles puedan autenticarse en /login y sean redirigidos a su ruta asignada.
     */
    #[DataProvider('rolesAndPostLoginRedirectsProvider')]
    public function test_each_role_can_authenticate_and_redirects_to_expected_route(
        UserRole $role,
        string $expectedPath,
        string $expectedRoleLabel
    ): void {
        $user = User::factory()->create([
            'name' => 'Profesional CAPS',
            'role' => $role,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect($expectedPath);

        $this->get($expectedPath)
            ->assertOk()
            ->assertSee('Profesional CAPS')
            ->assertSee($expectedRoleLabel);
    }

    /**
     * @return array<string, array{UserRole, string, int}>
     */
    public static function routeAccessMatrixProvider(): array
    {
        return [
            'admission -> /pacientes (200)' => [UserRole::ADMISSION, '/pacientes', 200],
            'admission -> /pacientes/nuevo (200)' => [UserRole::ADMISSION, '/pacientes/nuevo', 200],
            'admission -> /triage (403)' => [UserRole::ADMISSION, '/triage', 403],
            'admission -> /consultas (403)' => [UserRole::ADMISSION, '/consultas', 403],
            'triage_nurse -> /pacientes (200)' => [UserRole::TRIAGE_NURSE, '/pacientes', 200],
            'triage_nurse -> /pacientes/nuevo (200)' => [UserRole::TRIAGE_NURSE, '/pacientes/nuevo', 200],
            'triage_nurse -> /triage (200)' => [UserRole::TRIAGE_NURSE, '/triage', 200],
            'triage_nurse -> /consultas (403)' => [UserRole::TRIAGE_NURSE, '/consultas', 403],
            'doctor -> /pacientes (403)' => [UserRole::DOCTOR, '/pacientes', 403],
            'doctor -> /pacientes/nuevo (403)' => [UserRole::DOCTOR, '/pacientes/nuevo', 403],
            'doctor -> /triage (200)' => [UserRole::DOCTOR, '/triage', 200],
            'doctor -> /consultas (200)' => [UserRole::DOCTOR, '/consultas', 200],
            'director -> /pacientes (200)' => [UserRole::DIRECTOR, '/pacientes', 200],
            'director -> /pacientes/nuevo (200)' => [UserRole::DIRECTOR, '/pacientes/nuevo', 200],
            'director -> /triage (200)' => [UserRole::DIRECTOR, '/triage', 200],
            'director -> /consultas (200)' => [UserRole::DIRECTOR, '/consultas', 200],
        ];
    }

    /**
     * Valida que las rutas protegidas retornen 200 para roles autorizados y 403 Forbidden ante accesos cruzados.
     */
    #[DataProvider('routeAccessMatrixProvider')]
    public function test_protected_routes_enforce_role_access_and_return_403_when_unauthorized(
        UserRole $role,
        string $uri,
        int $expectedStatus
    ): void {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get($uri)
            ->assertStatus($expectedStatus);
    }

    /**
     * Valida la matriz de Gates de autorización definida en AppServiceProvider para los 4 roles.
     */
    public function test_gates_authorization_matrix_for_all_roles(): void
    {
        $admission = User::factory()->make(['role' => UserRole::ADMISSION]);
        $nurse = User::factory()->make(['role' => UserRole::TRIAGE_NURSE]);
        $doctor = User::factory()->make(['role' => UserRole::DOCTOR]);
        $director = User::factory()->make(['role' => UserRole::DIRECTOR]);

        // Gate: access-admission
        $this->assertTrue(Gate::forUser($admission)->allows('access-admission'));
        $this->assertTrue(Gate::forUser($nurse)->allows('access-admission'));
        $this->assertFalse(Gate::forUser($doctor)->allows('access-admission'));
        $this->assertTrue(Gate::forUser($director)->allows('access-admission'));

        // Gate: access-triage
        $this->assertFalse(Gate::forUser($admission)->allows('access-triage'));
        $this->assertTrue(Gate::forUser($nurse)->allows('access-triage'));
        $this->assertTrue(Gate::forUser($doctor)->allows('access-triage'));
        $this->assertTrue(Gate::forUser($director)->allows('access-triage'));

        // Gate: access-doctor
        $this->assertFalse(Gate::forUser($admission)->allows('access-doctor'));
        $this->assertFalse(Gate::forUser($nurse)->allows('access-doctor'));
        $this->assertTrue(Gate::forUser($doctor)->allows('access-doctor'));
        $this->assertTrue(Gate::forUser($director)->allows('access-doctor'));

        // Gate: access-analytics
        $this->assertFalse(Gate::forUser($admission)->allows('access-analytics'));
        $this->assertFalse(Gate::forUser($nurse)->allows('access-analytics'));
        $this->assertFalse(Gate::forUser($doctor)->allows('access-analytics'));
        $this->assertTrue(Gate::forUser($director)->allows('access-analytics'));
    }

    /**
     * Valida que un visitante no autenticado sea redirigido a /login al intentar acceder a rutas protegidas.
     */
    public function test_unauthenticated_guest_is_redirected_to_login(): void
    {
        foreach (['/pacientes', '/triage', '/consultas', '/dashboard'] as $protectedUri) {
            $this->get($protectedUri)->assertRedirect('/login');
        }
    }
}
