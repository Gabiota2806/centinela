<?php

declare(strict_types=1);

namespace Tests\Feature\Doctor;

use App\Enums\ConsultationStatus;
use App\Enums\PresumptiveDiagnosis;
use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Events\ConsultationCompleted;
use App\Livewire\Doctor\ConsultationRoom;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-47: consultorio con ficha clínica y cierre por diagnóstico presuntivo
 * (docs/specs/consultas/spec.md RF-CONS-08, 09 y 10).
 */
class ConsultationRoomTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-27 15:00:00');
        $this->medico = User::factory()->doctor()->create();
    }

    private function enConsulta(array $atributos = []): Triage
    {
        $paciente = Patient::factory()->create([
            'first_name' => 'Marta',
            'last_name' => 'Rodriguez',
            'dni' => '23456789',
            'birth_date' => '1972-03-14',
            'gender' => 'femenino',
            'neighborhood_id' => Neighborhood::factory()->create(['name' => 'San Miguel'])->id,
        ]);

        $triage = Triage::factory()->create([
            'patient_id' => $paciente->id,
            'algorithmic_priority' => TriagePriority::YELLOW,
            'final_priority' => TriagePriority::YELLOW,
            'target_wait_minutes' => 60,
            'status' => TriageStatus::CALLED,
            'symptoms' => 'Fiebre de 3 días con dolor retroocular y mialgias.',
            'vital_signs' => [
                'temperature' => 39.4, 'systolic_bp' => 118, 'diastolic_bp' => 76,
                'heart_rate' => 96, 'respiratory_rate' => 18, 'spo2' => 97,
                'discriminators' => ['dengue_suspicion'],
                'calculated_discriminator' => 'Sospecha febril de dengue (fiebre + cefalea/mialgias)',
            ],
            'created_at' => now()->subMinutes(40),
            ...$atributos,
        ]);

        $triage->startConsultation($this->medico);

        return $triage->refresh();
    }

    private function consultorio(Triage $triage, ?User $usuario = null)
    {
        return Livewire::actingAs($usuario ?? $this->medico)->test(ConsultationRoom::class, ['triage' => $triage]);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function accesoPorRol(): array
    {
        return [
            'admisión' => ['admission', 403],
            'enfermería de triage' => ['triageNurse', 403],
            'médico de guardia' => ['doctor', 200],
            'dirección' => ['director', 200],
        ];
    }

    #[DataProvider('accesoPorRol')]
    public function test_solo_medicos_y_direccion_acceden_al_consultorio(string $rol, int $esperado): void
    {
        $triage = $this->enConsulta();

        $this->actingAs(User::factory()->{$rol}()->create())
            ->get("/consultas/{$triage->id}")
            ->assertStatus($esperado);
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $triage = $this->enConsulta();

        $this->get("/consultas/{$triage->id}")->assertRedirect('/login');
    }

    public function test_la_ruta_renderiza_el_consultorio(): void
    {
        $triage = $this->enConsulta();

        $this->actingAs($this->medico)
            ->get(route('consultations.show', $triage))
            ->assertOk()
            ->assertSeeLivewire(ConsultationRoom::class);
    }

    public function test_el_formulario_de_cierre_queda_dentro_de_la_raiz_del_componente(): void
    {
        $html = $this->actingAs($this->medico)
            ->get(route('consultations.show', $this->enConsulta()))
            ->assertOk()
            ->getContent();

        // Si el formulario queda fuera del elemento con wire:id, el navegador lo
        // envía como GET nativo y el cierre no se guarda.
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $root = (new \DOMXPath($dom))->query('//*[@*[name()="wire:name"]="doctor.consultation-room"]')->item(0);

        $this->assertNotNull($root);
        $this->assertSame(1, (new \DOMXPath($dom))->query('.//form[@*[name()="wire:submit"]="save"]', $root)->length);
    }

    public function test_un_triage_que_no_esta_en_consulta_muestra_el_aviso_sin_formulario(): void
    {
        $enEspera = Triage::factory()->create(['status' => TriageStatus::PENDING_CONSULTATION]);

        // El aviso se muestra en la misma pantalla: no depende de la sesión, que
        // otra pestaña con la cola (wire:poll) podría pisar.
        $this->actingAs($this->medico)
            ->get(route('consultations.show', $enEspera))
            ->assertOk()
            ->assertSee('no está en consulta')
            ->assertSee('En espera')
            ->assertSee('Volver a la cola')
            ->assertDontSee('Cerrar consulta');

        $this->consultorio($enEspera)
            ->assertNoRedirect()
            ->set('diagnosis', 'ira')
            ->call('save')
            ->assertNoRedirect();

        $this->assertNull($enEspera->refresh()->consultation);
        $this->assertSame(TriageStatus::PENDING_CONSULTATION, $enEspera->status);
    }

    public function test_la_ficha_muestra_el_resumen_clinico_completo(): void
    {
        $triage = $this->enConsulta();

        $this->consultorio($triage)
            ->assertSee('Marta Rodriguez')
            ->assertSee('23456789')
            ->assertSee('54 años')
            ->assertSee('San Miguel')
            ->assertSee('TR-'.str_pad((string) $triage->id, 5, '0', STR_PAD_LEFT))
            ->assertSee('Amarillo (Urgente)')
            ->assertSee('Fiebre de 3 días con dolor retroocular y mialgias.')
            ->assertSee('Sospecha febril de dengue (fiebre + cefalea/mialgias)')
            ->assertSee('39.4')
            ->assertSee('118/76')
            ->assertSee('96')
            ->assertSee('18')
            ->assertSee('97')
            // Hora local del CAPS (UTC-3): llegada 14:20 UTC y consulta desde 15:00 UTC.
            ->assertSee('27/11/2026 11:20')
            ->assertSee('27/11/2026 12:00');
    }

    public function test_muestra_el_catalogo_de_diagnosticos_y_marca_los_febriles(): void
    {
        $componente = $this->consultorio($this->enConsulta());

        foreach (PresumptiveDiagnosis::cases() as $diagnostico) {
            $componente->assertSee($diagnostico->label())
                ->assertSeeHtml('data-diagnosis="'.$diagnostico->value.'"');
        }

        $componente->assertSee('Vigilancia epidemiológica');
    }

    public function test_cerrar_sin_diagnostico_muestra_error_y_no_cierra(): void
    {
        Event::fake([ConsultationCompleted::class]);
        $triage = $this->enConsulta();

        $this->consultorio($triage)
            ->call('save')
            ->assertHasErrors(['diagnosis' => 'required'])
            ->assertSee('Seleccioná el diagnóstico presuntivo')
            // El error queda visible aunque el médico esté al final del formulario.
            ->assertSeeHtml('id="diagnosis-error" x-init="$el.scrollIntoView');

        $this->assertSame(TriageStatus::IN_CONSULTATION, $triage->refresh()->status);
        $this->assertSame(ConsultationStatus::IN_PROGRESS, $triage->consultation->status);
        Event::assertNotDispatched(ConsultationCompleted::class);
    }

    public function test_cierre_con_sospecha_de_dengue_completa_la_consulta_y_despacha_el_evento(): void
    {
        // Gherkin RF-CONS-09/10.
        Event::fake([ConsultationCompleted::class]);
        $triage = $this->enConsulta();

        $this->consultorio($triage)
            ->set('diagnosis', 'sospecha_dengue')
            ->set('clinicalNotes', 'Prueba del lazo positiva. Sin signos de alarma.')
            ->set('prescription', 'Paracetamol 500 mg cada 8 h e hidratación oral.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('consultations.index'));

        $triage->refresh();
        $consulta = $triage->consultation;

        $this->assertSame(TriageStatus::COMPLETED, $triage->status);
        $this->assertSame(ConsultationStatus::COMPLETED, $consulta->status);
        $this->assertSame('sospecha_dengue', $consulta->diagnosis);
        $this->assertSame('Prueba del lazo positiva. Sin signos de alarma.', $consulta->clinical_notes);
        $this->assertSame('Paracetamol 500 mg cada 8 h e hidratación oral.', $consulta->prescription);
        $this->assertTrue($consulta->completed_at->equalTo(now()));
        $this->assertFalse(Triage::query()->active()->whereKey($triage->id)->exists());

        Event::assertDispatched(ConsultationCompleted::class, fn (ConsultationCompleted $e) => $e->consultation->is($consulta) && $e->triage->is($triage));
        $this->assertStringContainsString('Marta Rodriguez', session('queue-notice'));
    }

    public function test_permite_derivar_por_sipec(): void
    {
        Event::fake([ConsultationCompleted::class]);
        $triage = $this->enConsulta();

        $this->consultorio($triage)
            ->set('diagnosis', 'ira')
            ->set('outcome', 'referred_sipec')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(ConsultationStatus::REFERRED_SIPEC, $triage->refresh()->consultation->status);
        $this->assertSame(TriageStatus::COMPLETED, $triage->status);
        Event::assertDispatchedTimes(ConsultationCompleted::class, 1);
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: string}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'diagnóstico fuera del catálogo' => ['diagnosis', 'gripe_comun', 'diagnosis'],
            'resultado en curso' => ['outcome', 'in_progress', 'outcome'],
            'resultado inexistente' => ['outcome', 'alta_voluntaria', 'outcome'],
            'notas demasiado largas' => ['clinicalNotes', null, 'clinicalNotes'],
            'prescripción demasiado larga' => ['prescription', null, 'prescription'],
        ];
    }

    #[DataProvider('datosInvalidos')]
    public function test_rechaza_datos_invalidos(string $campo, mixed $valor, string $error): void
    {
        $triage = $this->enConsulta();

        $this->consultorio($triage)
            ->set('diagnosis', 'otro_clinico')
            ->set($campo, $valor ?? Str::repeat('a', 2001))
            ->call('save')
            ->assertHasErrors([$error]);

        $this->assertSame(TriageStatus::IN_CONSULTATION, $triage->refresh()->status);
    }

    public function test_cerrar_una_consulta_ya_cerrada_en_otra_terminal_muestra_error(): void
    {
        Event::fake([ConsultationCompleted::class]);
        $triage = $this->enConsulta();
        $componente = $this->consultorio($triage);

        Triage::findOrFail($triage->id)->finishConsultation(ConsultationStatus::COMPLETED, 'otro_clinico');

        $componente->set('diagnosis', 'ira')
            ->call('save')
            ->assertNoRedirect()
            ->assertSee('No se puede finalizar la consulta')
            ->assertSeeHtml('id="close-error" role="alert" tabindex="-1" x-init="$el.scrollIntoView');

        $this->assertSame('otro_clinico', $triage->refresh()->consultation->diagnosis);
        Event::assertNotDispatched(ConsultationCompleted::class);
    }

    public function test_cada_accion_vuelve_a_verificar_el_permiso(): void
    {
        $triage = $this->enConsulta();
        $componente = $this->consultorio($triage);

        $this->actingAs(User::factory()->triageNurse()->create());

        $componente->set('diagnosis', 'ira')->call('save')->assertForbidden();
        $this->assertSame(TriageStatus::IN_CONSULTATION, $triage->refresh()->status);
    }

    public function test_la_vista_no_usa_emojis(): void
    {
        $html = $this->consultorio($this->enConsulta())->html();

        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html);
    }
}
