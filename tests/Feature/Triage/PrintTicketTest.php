<?php

declare(strict_types=1);

namespace Tests\Feature\Triage;

use App\Enums\TriagePriority;
use App\Livewire\Triage\EvaluatePatient;
use App\Livewire\Triage\PrintTicket;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-37: ticket digital de triage (docs/specs/triage/spec.md RF-TRIAGE-07 y RF-TRIAGE-08).
 */
class PrintTicketTest extends TestCase
{
    use RefreshDatabase;

    private function triage(TriagePriority $priority = TriagePriority::YELLOW): Triage
    {
        $barrio = Neighborhood::factory()->create(['name' => 'San Miguel']);
        $paciente = Patient::factory()->create([
            'first_name' => 'Marta',
            'last_name' => 'Rodríguez',
            'dni' => '23456789',
            'neighborhood_id' => $barrio->id,
        ]);
        $enfermera = User::factory()->triageNurse()->create(['name' => 'Lucía Gómez']);

        return Triage::factory()->create([
            'patient_id' => $paciente->id,
            'user_id' => $enfermera->id,
            'algorithmic_priority' => $priority,
            'final_priority' => $priority,
            'target_wait_minutes' => $priority->targetWaitMinutes(),
        ]);
    }

    public function test_el_ticket_muestra_todos_los_datos_del_triage(): void
    {
        $triage = $this->triage();
        $numero = 'TR-'.str_pad((string) $triage->id, 5, '0', STR_PAD_LEFT);

        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(PrintTicket::class)
            ->dispatch('triage-saved', triageId: $triage->id)
            ->assertSet('show', true)
            ->assertSee($numero)
            ->assertSee('Marta Rodríguez')
            ->assertSee('23456789')
            ->assertSee('San Miguel')
            ->assertSee($triage->created_at->timezone('America/Argentina/Cordoba')->format('d/m/Y H:i'))
            ->assertSee('60 min')
            ->assertSee('Lucía Gómez');
    }

    /**
     * @return array<string, array{TriagePriority}>
     */
    public static function prioridades(): array
    {
        return array_combine(
            array_map(fn (TriagePriority $p) => $p->value, TriagePriority::cases()),
            array_map(fn (TriagePriority $p) => [$p], TriagePriority::cases()),
        );
    }

    #[DataProvider('prioridades')]
    public function test_el_ticket_muestra_el_nivel_y_color_mts(TriagePriority $priority): void
    {
        $triage = $this->triage($priority);

        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(PrintTicket::class)
            ->dispatch('triage-saved', triageId: $triage->id)
            ->assertSee($priority->label())
            ->assertSee($priority->badgeClasses(), false)
            ->assertSee('data-mts-level="'.$priority->level().'"', false);
    }

    public function test_la_hora_de_ingreso_se_muestra_en_hora_local_de_formosa(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-16 21:28:00', 'UTC'));
        $triage = $this->triage();

        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(PrintTicket::class)
            ->dispatch('triage-saved', triageId: $triage->id)
            ->assertSee('16/11/2026 18:28')
            ->assertDontSee('16/11/2026 21:28');
    }

    public function test_el_ticket_esta_cerrado_hasta_recibir_un_triage(): void
    {
        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(PrintTicket::class)
            ->assertSet('show', false)
            ->assertDontSee('TR-');
    }

    public function test_el_ticket_incluye_el_formato_de_impresion_de_80_mm(): void
    {
        $triage = $this->triage();

        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(PrintTicket::class)
            ->dispatch('triage-saved', triageId: $triage->id)
            ->assertSee('ticket-80mm', false)
            ->assertSee('window.print()', false);
    }

    public function test_admision_no_puede_ver_tickets_de_triage(): void
    {
        Livewire::actingAs(User::factory()->admission()->create())
            ->test(PrintTicket::class)
            ->assertForbidden();
    }

    public function test_registrar_un_triage_emite_el_evento_del_ticket(): void
    {
        $enfermera = User::factory()->triageNurse()->create();
        $paciente = Patient::factory()->create();

        Livewire::actingAs($enfermera)
            ->test(EvaluatePatient::class)
            ->call('selectPatient', $paciente->id)
            ->set('symptoms', 'Fiebre y cefalea desde hace 3 días')
            ->set('temperature', 39.5)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('triage-saved', function (string $name, array $params) {
                return $params['triageId'] === Triage::query()->latest('id')->value('id');
            });
    }

    public function test_al_registrar_el_formulario_queda_limpio_para_el_siguiente_paciente(): void
    {
        $paciente = Patient::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Villalba']);

        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(EvaluatePatient::class)
            ->call('selectPatient', $paciente->id)
            ->set('symptoms', 'Fiebre y cefalea')
            ->set('temperature', 38.9)
            ->set('spo2', 97)
            ->set('discriminators.severe_pain', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('patientId', null)
            ->assertSet('symptoms', '')
            ->assertSet('temperature', null)
            ->assertSet('spo2', null)
            ->assertSet('discriminators', [])
            ->assertSet('lastSavedTriageId', Triage::query()->latest('id')->value('id'));
    }

    public function test_la_confirmacion_de_guardado_sigue_visible_al_seguir_usando_la_pantalla(): void
    {
        $paciente = Patient::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Villalba']);

        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(EvaluatePatient::class)
            ->call('selectPatient', $paciente->id)
            ->set('symptoms', 'Fiebre y cefalea')
            ->call('save')
            ->set('searchPatient', 'Marta') // nueva petición: un flash de sesión ya se habría consumido
            ->assertSee('Triage registrado con éxito')
            ->assertSee('Carlos Villalba')
            ->assertSee('Ver ticket');
    }

    public function test_ver_ticket_vuelve_a_abrir_el_ultimo_triage_registrado(): void
    {
        $paciente = Patient::factory()->create();

        $componente = Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(EvaluatePatient::class)
            ->call('selectPatient', $paciente->id)
            ->set('symptoms', 'Fiebre y cefalea')
            ->call('save');

        $id = Triage::query()->latest('id')->value('id');

        $componente->call('showTicket')
            ->assertDispatched('triage-saved', triageId: $id);
    }

    public function test_si_faltan_datos_el_error_se_muestra_junto_al_boton_de_registrar(): void
    {
        Livewire::actingAs(User::factory()->triageNurse()->create())
            ->test(EvaluatePatient::class)
            ->call('save')
            ->assertHasErrors(['patientId', 'symptoms'])
            ->assertSee('No se pudo registrar el triage')
            ->assertSeeHtml('id="triage-save-errors"')
            ->assertNotDispatched('triage-saved');

        $this->assertSame(0, Triage::count());
    }

    public function test_la_pantalla_de_triage_incluye_el_ticket(): void
    {
        $this->actingAs(User::factory()->triageNurse()->create())
            ->get('/triage')
            ->assertOk()
            ->assertSeeLivewire(PrintTicket::class);
    }
}
