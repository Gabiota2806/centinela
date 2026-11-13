<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Enums\UserRole;
use App\Livewire\Patients\CreatePatient;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-34: formulario reactivo de admisión (docs/specs/pacientes/spec.md).
 */
class CreatePatientTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(Neighborhood $barrio): array
    {
        return [
            'dni' => '23456789',
            'first_name' => 'Marta',
            'last_name' => 'Rodríguez',
            'birth_date' => '1972-03-14',
            'gender' => 'femenino',
            'phone' => '370 455-1234',
            'address' => 'Calle Los Lapachos 123',
            'neighborhood_id' => $barrio->id,
        ];
    }

    private function completar(\Livewire\Features\SupportTesting\Testable $form, array $datos): \Livewire\Features\SupportTesting\Testable
    {
        foreach ($datos as $campo => $valor) {
            $form->set($campo, $valor);
        }

        return $form;
    }

    public function test_admision_ve_el_formulario_de_alta(): void
    {
        $user = User::factory()->admission()->create();

        $this->actingAs($user)->get('/pacientes/nuevo')
            ->assertOk()
            ->assertSeeLivewire(CreatePatient::class);
    }

    /**
     * @return array<string, array{UserRole, int}>
     */
    public static function accesoPorRol(): array
    {
        return [
            'admisión' => [UserRole::ADMISSION, 200],
            'enfermería de triage' => [UserRole::TRIAGE_NURSE, 403],
            'médico de guardia' => [UserRole::DOCTOR, 403],
            'dirección' => [UserRole::DIRECTOR, 200],
        ];
    }

    #[DataProvider('accesoPorRol')]
    public function test_solo_los_roles_de_admision_acceden_al_formulario(UserRole $role, int $codigo): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get('/pacientes/nuevo')->assertStatus($codigo);
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get('/pacientes/nuevo')->assertRedirect('/login');
    }

    public function test_carga_exitosa_persiste_el_paciente_y_permite_iniciar_triage(): void
    {
        $user = User::factory()->admission()->create();
        $barrio = Neighborhood::factory()->create(['name' => 'San Miguel']);

        $form = $this->completar(Livewire::actingAs($user)->test(CreatePatient::class), $this->datosValidos($barrio))
            ->call('save')
            ->assertHasNoErrors();

        $paciente = Patient::where('dni', '23456789')->firstOrFail();

        $this->assertSame('Marta', $paciente->first_name);
        $this->assertSame('femenino', $paciente->gender);
        $this->assertSame($barrio->id, $paciente->neighborhood_id);

        $form->assertSee('Paciente registrado')
            ->assertSee('Marta Rodríguez · DNI 23456789')
            ->assertSee(route('triage.index', ['patient' => $paciente->id]), false);

        // El aviso se mantiene aunque el formulario se vuelva a renderizar.
        $form->set('dni', '1')->assertSee('Marta Rodríguez · DNI 23456789');
    }

    public function test_el_formulario_valida_en_tiempo_real(): void
    {
        $user = User::factory()->admission()->create();

        Livewire::actingAs($user)->test(CreatePatient::class)
            ->set('dni', '23A456')
            ->assertHasErrors(['dni']);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'DNI con letras' => ['dni', '23A456'],
            'DNI vacío' => ['dni', ''],
            'nombre vacío' => ['first_name', ''],
            'apellido vacío' => ['last_name', ''],
            'fecha de nacimiento futura' => ['birth_date', now()->addDay()->toDateString()],
            'género fuera de la lista' => ['gender', 'desconocido'],
            'domicilio vacío' => ['address', ''],
            'barrio inexistente' => ['neighborhood_id', 999999],
        ];
    }

    #[DataProvider('datosInvalidos')]
    public function test_rechaza_datos_invalidos(string $campo, mixed $valor): void
    {
        $user = User::factory()->admission()->create();
        $barrio = Neighborhood::factory()->create();

        $datos = array_merge($this->datosValidos($barrio), [$campo => $valor]);

        $this->completar(Livewire::actingAs($user)->test(CreatePatient::class), $datos)
            ->call('save')
            ->assertHasErrors([$campo]);

        $this->assertDatabaseCount('patients', 0);
    }

    public function test_rechaza_un_dni_ya_registrado(): void
    {
        $user = User::factory()->admission()->create();
        $barrio = Neighborhood::factory()->create();
        Patient::factory()->create(['dni' => '23456789', 'neighborhood_id' => $barrio->id]);

        $this->completar(Livewire::actingAs($user)->test(CreatePatient::class), $this->datosValidos($barrio))
            ->call('save')
            ->assertHasErrors(['dni' => 'unique']);

        $this->assertDatabaseCount('patients', 1);
    }

    public function test_el_formulario_lista_los_barrios_existentes(): void
    {
        $user = User::factory()->admission()->create();
        Neighborhood::factory()->create(['name' => 'Circuito Cinco']);

        Livewire::actingAs($user)->test(CreatePatient::class)
            ->assertSee('Circuito Cinco');
    }

    public function test_autocompletado_filtra_barrios_reactivamente_por_nombre(): void
    {
        $user = User::factory()->admission()->create();
        Neighborhood::factory()->create(['name' => 'San Martín']);
        Neighborhood::factory()->create(['name' => 'San Miguel']);
        Neighborhood::factory()->create(['name' => 'Fontana']);

        Livewire::actingAs($user)->test(CreatePatient::class)
            ->set('neighborhoodSearch', 'San')
            ->assertSee('San Martín')
            ->assertSee('San Miguel')
            ->assertDontSee('Fontana');
    }

    public function test_seleccionar_barrio_desde_autocompletado_asigna_id_y_nombre(): void
    {
        $user = User::factory()->admission()->create();
        $barrio = Neighborhood::factory()->create(['name' => 'Eva Perón']);

        Livewire::actingAs($user)->test(CreatePatient::class)
            ->call('selectNeighborhood', $barrio->id, 'Eva Perón')
            ->assertSet('neighborhood_id', $barrio->id)
            ->assertSet('selectedNeighborhoodName', 'Eva Perón')
            ->assertSet('neighborhoodSearch', 'Eva Perón')
            ->assertHasNoErrors(['neighborhood_id']);
    }

    public function test_limpiar_barrio_restablece_la_seleccion(): void
    {
        $user = User::factory()->admission()->create();
        $barrio = Neighborhood::factory()->create(['name' => 'Don Bosco']);

        Livewire::actingAs($user)->test(CreatePatient::class)
            ->call('selectNeighborhood', $barrio->id, 'Don Bosco')
            ->assertSet('neighborhood_id', $barrio->id)
            ->call('clearNeighborhood')
            ->assertSet('neighborhood_id', '')
            ->assertSet('selectedNeighborhoodName', '')
            ->assertSet('neighborhoodSearch', '');
    }

    public function test_carga_exitosa_seleccionando_barrio_con_autocompletado(): void
    {
        $user = User::factory()->admission()->create();
        $barrio = Neighborhood::factory()->create(['name' => 'República Argentina']);

        $datos = [
            'dni' => '34567890',
            'first_name' => 'Carlos',
            'last_name' => 'Gómez',
            'birth_date' => '1985-06-20',
            'gender' => 'masculino',
            'phone' => '370 411-2233',
            'address' => 'Manzana 40 Casa 12',
        ];

        $component = Livewire::actingAs($user)->test(CreatePatient::class);

        foreach ($datos as $campo => $valor) {
            $component->set($campo, $valor);
        }

        $component->call('selectNeighborhood', $barrio->id, 'República Argentina')
            ->call('save')
            ->assertHasNoErrors();

        $patient = Patient::where('dni', '34567890')->firstOrFail();
        $this->assertSame($barrio->id, $patient->neighborhood_id);
        $this->assertSame('Carlos Gómez', $patient->full_name);
    }
}
