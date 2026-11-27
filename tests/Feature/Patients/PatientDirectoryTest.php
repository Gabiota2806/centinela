<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Enums\TriageStatus;
use App\Enums\UserRole;
use App\Livewire\Patients\CreatePatient;
use App\Livewire\Patients\PatientDirectory;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-73: padrón y buscador de pacientes en /pacientes (docs/specs/pacientes/spec.md RF-PAC-01 y RF-PAC-08).
 */
class PatientDirectoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole, int}>
     */
    public static function accesoPorRol(): array
    {
        return [
            'admisión' => [UserRole::ADMISSION, 200],
            'enfermería de triage' => [UserRole::TRIAGE_NURSE, 200],
            'médico de guardia' => [UserRole::DOCTOR, 403],
            'dirección' => [UserRole::DIRECTOR, 200],
        ];
    }

    #[DataProvider('accesoPorRol')]
    public function test_control_de_acceso_por_rol_al_padron(UserRole $role, int $expectedStatus): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get('/pacientes')
            ->assertStatus($expectedStatus);
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/pacientes')->assertRedirect('/login');
    }

    public function test_medico_recibe_403_al_montar_el_componente_directamente(): void
    {
        Livewire::actingAs(User::factory()->doctor()->create())
            ->test(PatientDirectory::class)
            ->assertForbidden();
    }

    public function test_la_ruta_pacientes_renderiza_el_padron_en_lugar_del_placeholder(): void
    {
        $nurse = User::factory()->triageNurse()->create();

        $this->actingAs($nurse)
            ->get('/pacientes')
            ->assertOk()
            ->assertSeeLivewire(PatientDirectory::class)
            ->assertSee('Padrón de Pacientes')
            ->assertDontSee('en construcción')
            ->assertDontSee('Sprint 3');
    }

    public function test_muestra_barrio_cantidad_de_atenciones_estado_activo_y_enlace_a_nueva_atencion(): void
    {
        $user = User::factory()->admission()->create();
        $barrioSanMiguel = Neighborhood::factory()->create(['name' => 'San Miguel']);
        $barrioDonBosco = Neighborhood::factory()->create(['name' => 'Don Bosco']);

        $marta = Patient::factory()->create([
            'dni' => '23456789',
            'first_name' => 'Marta',
            'last_name' => 'Rodríguez',
            'neighborhood_id' => $barrioSanMiguel->id,
        ]);

        // Dos atenciones previas finalizadas
        Triage::factory()->count(2)->create([
            'patient_id' => $marta->id,
            'status' => TriageStatus::COMPLETED,
        ]);

        $carlos = Patient::factory()->create([
            'dni' => '31987654',
            'first_name' => 'Carlos',
            'last_name' => 'Gómez',
            'neighborhood_id' => $barrioDonBosco->id,
        ]);

        // Una atención actualmente en espera
        Triage::factory()->create([
            'patient_id' => $carlos->id,
            'status' => TriageStatus::PENDING_CONSULTATION,
        ]);

        Livewire::actingAs($user)
            ->test(PatientDirectory::class)
            ->assertSee('Marta Rodríguez')
            ->assertSee('23456789')
            ->assertSee('San Miguel')
            ->assertSee('2 atenciones')
            ->assertSee(route('patients.create', ['dni' => '23456789']), false)
            ->assertSee('Carlos Gómez')
            ->assertSee('31987654')
            ->assertSee('Don Bosco')
            ->assertSee('1 atención')
            ->assertSee('En atención')
            ->assertSee(route('patients.create', ['dni' => '31987654']), false);
    }

    public function test_busca_pacientes_por_dni_nombre_apellido_y_nombre_completo_insensible_a_mayusculas(): void
    {
        $user = User::factory()->triageNurse()->create();

        Patient::factory()->create([
            'dni' => '25111222',
            'first_name' => 'Sofía',
            'last_name' => 'Benítez',
        ]);

        Patient::factory()->create([
            'dni' => '40999888',
            'first_name' => 'Lucas',
            'last_name' => 'Pereyra',
        ]);

        $componente = Livewire::actingAs($user)->test(PatientDirectory::class);

        // Búsqueda por DNI parcial
        $componente->set('search', '2511')
            ->assertSee('Sofía Benítez')
            ->assertDontSee('Lucas Pereyra');

        // Búsqueda por nombre en minúsculas
        $componente->set('search', 'lucas')
            ->assertSee('Lucas Pereyra')
            ->assertDontSee('Sofía Benítez');

        // Búsqueda por apellido
        $componente->set('search', 'BENÍTEZ')
            ->assertSee('Sofía Benítez')
            ->assertDontSee('Lucas Pereyra');

        // Búsqueda por nombre completo
        $componente->set('search', 'lucas pereyra')
            ->assertSee('Lucas Pereyra')
            ->assertDontSee('Sofía Benítez');

        // Sin coincidencias
        $componente->set('search', '99999999')
            ->assertSee('No se encontraron pacientes')
            ->assertDontSee('Sofía Benítez')
            ->assertDontSee('Lucas Pereyra');
    }

    public function test_muestra_estado_vacio_cuando_no_hay_pacientes_registrados(): void
    {
        Livewire::actingAs(User::factory()->admission()->create())
            ->test(PatientDirectory::class)
            ->assertSee('Todavía no hay pacientes registrados')
            ->assertSee(route('patients.create'), false);
    }

    public function test_pagina_resultados_y_evita_consultas_n_mas_1(): void
    {
        $user = User::factory()->admission()->create();
        $barrios = Neighborhood::factory()->count(3)->create();

        for ($i = 1; $i <= 18; $i++) {
            $patient = Patient::factory()->create([
                'dni' => (string) (30000000 + $i),
                'first_name' => "Paciente{$i}",
                'last_name' => 'Demo',
                'neighborhood_id' => $barrios[$i % 3]->id,
            ]);

            Triage::factory()->create([
                'patient_id' => $patient->id,
                'status' => TriageStatus::COMPLETED,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $componente = Livewire::actingAs($user)
            ->test(PatientDirectory::class);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Paginación + count + eager load de neighborhood: debe mantenerse acotado (sin 18+ consultas N+1)
        $this->assertLessThanOrEqual(8, $queryCount, "Se ejecutaron demasiadas consultas SQL ({$queryCount}), posible N+1.");

        // Al escribir un filtro desde una página posterior, reinicia la paginación y encuentra al paciente
        $componente->set('search', '30000001')
            ->assertSee('Paciente1 Demo')
            ->assertDontSee('Paciente18 Demo');
    }

    public function test_seguir_el_enlace_del_padron_precarga_el_dni_en_nuevo_paciente(): void
    {
        $nurse = User::factory()->triageNurse()->create();
        $barrio = Neighborhood::factory()->create(['name' => 'San Martín']);
        Patient::factory()->create([
            'dni' => '27654321',
            'first_name' => 'Elena',
            'last_name' => 'Ramírez',
            'neighborhood_id' => $barrio->id,
        ]);

        $this->actingAs($nurse)
            ->get(route('patients.create', ['dni' => '27654321']))
            ->assertOk()
            ->assertSeeLivewire(CreatePatient::class)
            ->assertSee('Paciente recurrente detectado en el padrón');

        Livewire::actingAs($nurse)
            ->withQueryParams(['dni' => '27654321'])
            ->test(CreatePatient::class)
            ->assertSet('isRecurringPatient', true)
            ->assertSet('dni', '27654321')
            ->assertSet('first_name', 'Elena')
            ->assertSet('last_name', 'Ramírez')
            ->assertSet('neighborhood_id', $barrio->id);
    }
}
