<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Enums\UserRole;
use App\Livewire\Patients\CreatePatient;
use App\Livewire\Triage\PrintTicket;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-34 / CEN-74: formulario reactivo unificado de admisión + triage (docs/specs/pacientes/spec.md).
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
            'temperature' => '36.8',
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'heart_rate' => 75,
            'respiratory_rate' => 16,
            'spo2' => 98,
            'symptoms' => 'Cefalea leve y control general de presión arterial.',
        ];
    }

    private function completar(Testable $form, array $datos): Testable
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

    public function test_carga_exitosa_persiste_paciente_y_triage_atomicamente(): void
    {
        $user = User::factory()->admission()->create();
        $barrio = Neighborhood::factory()->create(['name' => 'San Miguel']);

        $form = $this->completar(Livewire::actingAs($user)->test(CreatePatient::class), $this->datosValidos($barrio))
            ->call('save')
            ->assertHasNoErrors();

        $paciente = Patient::where('dni', '23456789')->firstOrFail();
        $triage = Triage::where('patient_id', $paciente->id)->firstOrFail();

        $this->assertSame('Marta', $paciente->first_name);
        $this->assertSame('femenino', $paciente->gender);
        $this->assertSame($barrio->id, $paciente->neighborhood_id);

        $this->assertSame($user->id, $triage->user_id);
        $this->assertSame(TriageStatus::PENDING_CONSULTATION, $triage->status);
        $this->assertSame(TriagePriority::BLUE, $triage->final_priority);
        $this->assertSame(36.8, $triage->vital_signs['temperature']);
        $this->assertSame(120, $triage->vital_signs['systolic_bp']);
        $this->assertSame(80, $triage->vital_signs['diastolic_bp']);
        $this->assertSame(75, $triage->vital_signs['heart_rate']);
        $this->assertSame(16, $triage->vital_signs['respiratory_rate']);
        $this->assertSame(98, $triage->vital_signs['spo2']);

        $ticket = PrintTicket::ticketNumber($triage->id);

        $form->assertDispatched('triage-saved', triageId: $triage->id)
            ->assertSee('Paciente registrado')
            ->assertSee('Marta Rodríguez · DNI 23456789')
            ->assertSee($ticket);

        // El aviso se mantiene aunque el formulario se vuelva a renderizar.
        $form->set('dni', '1')->assertSee('Marta Rodríguez · DNI 23456789');
    }

    public function test_calcula_prioridad_mts_en_tiempo_real_al_cambiar_signos_y_discriminadores(): void
    {
        $user = User::factory()->admission()->create();

        Livewire::actingAs($user)->test(CreatePatient::class)
            ->assertSet('priorityValue', 'blue')
            ->set('temperature', '39.4')
            ->set('discriminators.dengue_suspicion', true)
            ->assertSet('priorityValue', 'yellow')
            ->assertSet('targetWaitMinutes', 60)
            ->set('spo2', 82)
            ->assertSet('priorityValue', 'red')
            ->assertSet('targetWaitMinutes', 0);
    }

    public function test_paciente_recurrente_por_dni_sin_triage_activo_precarga_datos_y_crea_nuevo_triage_sin_duplicar_paciente(): void
    {
        $user = User::factory()->admission()->create();
        $barrioOriginal = Neighborhood::factory()->create(['name' => 'San Miguel']);
        $barrioNuevo = Neighborhood::factory()->create(['name' => 'Don Bosco']);

        $existente = Patient::factory()->create([
            'dni' => '23456789',
            'first_name' => 'Marta',
            'last_name' => 'Rodríguez',
            'birth_date' => '1972-03-14',
            'gender' => 'femenino',
            'phone' => '370 400-0000',
            'address' => 'Domicilio Anterior 100',
            'neighborhood_id' => $barrioOriginal->id,
        ]);

        // Triage histórico ya completado (no activo)
        Triage::factory()->create([
            'patient_id' => $existente->id,
            'user_id' => $user->id,
            'status' => TriageStatus::COMPLETED,
        ]);

        $form = Livewire::actingAs($user)->test(CreatePatient::class)
            ->set('dni', '23456789')
            ->assertHasNoErrors(['dni'])
            ->assertSet('isRecurringPatient', true)
            ->assertSet('existingPatientId', $existente->id)
            ->assertSet('first_name', 'Marta')
            ->assertSet('last_name', 'Rodríguez')
            ->assertSet('address', 'Domicilio Anterior 100')
            ->assertSet('neighborhood_id', $barrioOriginal->id)
            ->assertSet('activeTriageId', null);

        // Actualiza domicilio y barrio, carga signos vitales obligatorios y guarda
        $form->set('address', 'Nuevo Domicilio 456')
            ->call('selectNeighborhood', $barrioNuevo->id, 'Don Bosco')
            ->set('temperature', '38,6')
            ->set('systolic_bp', 125)
            ->set('diastolic_bp', 82)
            ->set('heart_rate', 88)
            ->set('respiratory_rate', 18)
            ->set('spo2', 97)
            ->set('symptoms', 'Síndrome febril agudo de 24 horas de evolución.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('triages', 2);

        $existente->refresh();
        $this->assertSame('Nuevo Domicilio 456', $existente->address);
        $this->assertSame($barrioNuevo->id, $existente->neighborhood_id);
    }

    public function test_paciente_recurrente_soft_deleted_se_precarga_con_with_trashed_y_se_restaura_al_guardar(): void
    {
        $user = User::factory()->admission()->create();
        $barrio = Neighborhood::factory()->create(['name' => 'La Paz']);

        $eliminado = Patient::factory()->create([
            'dni' => '29876543',
            'first_name' => 'Jorge',
            'last_name' => 'Acosta',
            'neighborhood_id' => $barrio->id,
        ]);
        $eliminado->delete();

        $this->assertSoftDeleted('patients', ['id' => $eliminado->id]);

        Livewire::actingAs($user)->test(CreatePatient::class)
            ->set('dni', '29876543')
            ->assertSet('isRecurringPatient', true)
            ->assertSet('first_name', 'Jorge')
            ->set('temperature', '36.6')
            ->set('systolic_bp', 118)
            ->set('diastolic_bp', 76)
            ->set('heart_rate', 72)
            ->set('respiratory_rate', 15)
            ->set('spo2', 99)
            ->set('symptoms', 'Control clínico de rutina.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('patients', 1);
        $this->assertNotSoftDeleted('patients', ['id' => $eliminado->id]);
        $this->assertDatabaseCount('triages', 1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function estadosDeTriageActivo(): array
    {
        return [
            'en espera de consulta' => ['pending_consultation'],
            'llamado a consultorio' => ['called'],
            'en consulta médica' => ['in_consultation'],
        ];
    }

    #[DataProvider('estadosDeTriageActivo')]
    public function test_bloquea_doble_ingreso_si_el_paciente_posee_triage_activo(string $estadoActivo): void
    {
        $user = User::factory()->admission()->create();
        $barrio = Neighborhood::factory()->create(['name' => 'San Miguel']);

        $paciente = Patient::factory()->create([
            'dni' => '23456789',
            'first_name' => 'Marta',
            'last_name' => 'Rodríguez',
            'neighborhood_id' => $barrio->id,
        ]);

        $triageActivo = Triage::factory()->create([
            'patient_id' => $paciente->id,
            'user_id' => $user->id,
            'status' => TriageStatus::PENDING_CONSULTATION,
        ]);

        if ($estadoActivo !== TriageStatus::PENDING_CONSULTATION->value) {
            DB::table('triages')->where('id', $triageActivo->id)->update(['status' => $estadoActivo]);
        }

        $ticketEsperado = PrintTicket::ticketNumber($triageActivo->id);

        $form = Livewire::actingAs($user)->test(CreatePatient::class)
            ->set('dni', '23456789')
            ->assertSet('activeTriageId', $triageActivo->id)
            ->assertSet('activeTriageTicket', $ticketEsperado)
            ->assertSee('#'.$ticketEsperado);

        $this->completar($form, $this->datosValidos($barrio))
            ->call('save')
            ->assertHasErrors(['dni']);

        $this->assertDatabaseCount('triages', 1);
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
            'temperatura vacía' => ['temperature', ''],
            'temperatura fuera de rango' => ['temperature', '48.0'],
            'presión sistólica vacía' => ['systolic_bp', ''],
            'presión sistólica fuera de rango' => ['systolic_bp', 300],
            'presión diastólica vacía' => ['diastolic_bp', ''],
            'presión diastólica fuera de rango' => ['diastolic_bp', 10],
            'frecuencia cardíaca vacía' => ['heart_rate', ''],
            'frecuencia cardíaca fuera de rango' => ['heart_rate', 300],
            'frecuencia respiratoria vacía' => ['respiratory_rate', ''],
            'frecuencia respiratoria fuera de rango' => ['respiratory_rate', 2],
            'saturación SpO2 vacía' => ['spo2', ''],
            'saturación SpO2 fuera de rango' => ['spo2', 105],
            'motivo de consulta vacío' => ['symptoms', ''],
            'motivo de consulta demasiado corto' => ['symptoms', 'ab'],
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
        $this->assertDatabaseCount('triages', 0);
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
            'temperature' => '37.1',
            'systolic_bp' => 118,
            'diastolic_bp' => 76,
            'heart_rate' => 70,
            'respiratory_rate' => 16,
            'spo2' => 98,
            'symptoms' => 'Dolor osteomuscular leve en hombro derecho.',
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
        $this->assertDatabaseHas('triages', [
            'patient_id' => $patient->id,
            'status' => TriageStatus::PENDING_CONSULTATION->value,
        ]);
    }

    public function test_calcula_y_muestra_posicion_estimada_en_cola_segun_jerarquia_mts_y_fifo_y_recalcula_tras_override(): void
    {
        $user = User::factory()->admission()->create();
        $doctor = User::factory()->doctor()->create();
        $barrio = Neighborhood::factory()->create(['name' => 'San Miguel']);

        // 1 paciente Rojo en espera (#1)
        $triageRojo = Triage::factory()->create([
            'user_id' => $user->id,
            'algorithmic_priority' => TriagePriority::RED,
            'final_priority' => TriagePriority::RED,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes(15),
        ]);

        // 1 paciente Verde previo en espera (#2)
        $triageVerdePrevio = Triage::factory()->create([
            'user_id' => $user->id,
            'algorithmic_priority' => TriagePriority::GREEN,
            'final_priority' => TriagePriority::GREEN,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes(10),
        ]);

        // 1 paciente Azul en espera (irá detrás del nuevo Verde)
        $triageAzul = Triage::factory()->create([
            'user_id' => $user->id,
            'algorithmic_priority' => TriagePriority::BLUE,
            'final_priority' => TriagePriority::BLUE,
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes(20),
        ]);

        // 1 paciente Rojo ya en consulta (no cuenta en pending_consultation)
        Triage::factory()->create([
            'user_id' => $user->id,
            'algorithmic_priority' => TriagePriority::RED,
            'final_priority' => TriagePriority::RED,
            'status' => TriageStatus::IN_CONSULTATION,
            'created_at' => now()->subMinutes(25),
        ]);

        // Nuevo ingreso con febrícula leve (38.0°C -> prioridad Verde)
        $datos = array_merge($this->datosValidos($barrio), [
            'temperature' => '38.0',
            'symptoms' => 'Febrícula de 12 horas sin signos de alarma.',
        ]);

        $form = $this->completar(Livewire::actingAs($user)->test(CreatePatient::class), $datos)
            ->call('save')
            ->assertHasNoErrors();

        $nuevoTriage = Triage::whereHas('patient', fn ($q) => $q->where('dni', '23456789'))->firstOrFail();

        $this->assertSame(TriagePriority::GREEN, $nuevoTriage->final_priority);
        $this->assertSame(1, $triageRojo->estimatedQueuePosition());
        $this->assertSame(2, $triageVerdePrevio->estimatedQueuePosition());
        $this->assertSame(3, $nuevoTriage->estimatedQueuePosition());
        $this->assertSame(4, $triageAzul->estimatedQueuePosition());

        $form->assertSet('createdQueuePosition', 3)
            ->assertSee('Posición estimada en espera: #3');

        // Si se ejecuta Clinical Override del nuevo triage a Naranja, pasa al #2 (detrás del Rojo y delante del Verde previo)
        $nuevoTriage->overridePriority(
            TriagePriority::ORANGE,
            'Taquicardia progresiva y dolor abdominal agudo en sala de espera.',
            $doctor
        );

        $form->dispatch('triage-overridden', triageId: $nuevoTriage->id)
            ->assertSet('createdQueuePosition', 2)
            ->assertSee('Posición estimada en espera: #2')
            ->assertSee(TriagePriority::ORANGE->label());
    }
}
