<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admision_no_puede_acceder_a_consultas(): void
    {
        $user = User::factory()->admission()->create();

        $this->actingAs($user)->get('/consultas')->assertForbidden();
    }

    /**
     * Matriz de docs/specs/auth/spec.md §4: [rol, ruta, código esperado].
     *
     * @return array<string, array{UserRole, string, int}>
     */
    public static function matrizDeRutas(): array
    {
        return [
            'admisión → pacientes' => [UserRole::ADMISSION, '/pacientes', 200],
            'admisión → triage' => [UserRole::ADMISSION, '/triage', 403],
            'admisión → consultas' => [UserRole::ADMISSION, '/consultas', 403],
            'triage → pacientes' => [UserRole::TRIAGE_NURSE, '/pacientes', 200],
            'triage → triage' => [UserRole::TRIAGE_NURSE, '/triage', 200],
            'triage → consultas' => [UserRole::TRIAGE_NURSE, '/consultas', 403],
            'médico → pacientes' => [UserRole::DOCTOR, '/pacientes', 403],
            'médico → triage' => [UserRole::DOCTOR, '/triage', 200],
            'médico → consultas' => [UserRole::DOCTOR, '/consultas', 200],
            'dirección → pacientes' => [UserRole::DIRECTOR, '/pacientes', 200],
            'dirección → triage' => [UserRole::DIRECTOR, '/triage', 200],
            'dirección → consultas' => [UserRole::DIRECTOR, '/consultas', 200],
        ];
    }

    #[DataProvider('matrizDeRutas')]
    public function test_cada_rol_accede_solo_a_sus_rutas(UserRole $role, string $ruta, int $codigo): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get($ruta)->assertStatus($codigo);
    }

    public function test_solo_direccion_tiene_acceso_a_la_analitica(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->make(['role' => $role]);

            $this->assertSame(
                $role === UserRole::DIRECTOR,
                Gate::forUser($user)->allows('access-analytics'),
                "access-analytics para {$role->value}"
            );
        }
    }

    public function test_el_rol_por_defecto_es_admision(): void
    {
        DB::table('users')->insert([
            'name' => 'Sin rol explícito',
            'email' => 'sin-rol@caps.test',
            'password' => bcrypt('password'),
        ]);

        $user = User::where('email', 'sin-rol@caps.test')->firstOrFail();

        $this->assertSame(UserRole::ADMISSION, $user->role);
        $this->assertTrue($user->isAdmission());
    }

    public function test_la_barra_lateral_muestra_solo_los_modulos_del_rol(): void
    {
        $doctor = User::factory()->doctor()->create();

        $this->actingAs($doctor)->get('/consultas')
            ->assertSee('Consultas')
            ->assertSee('Triage MTS')
            ->assertDontSee('Admisión');

        $nurse = User::factory()->triageNurse()->create();

        $this->actingAs($nurse)->get('/triage')
            ->assertSee('Admisión')
            ->assertSee('Nuevo paciente')
            ->assertSee('Triage MTS')
            ->assertDontSee('Consultas');
    }
}
