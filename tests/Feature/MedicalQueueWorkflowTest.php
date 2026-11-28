<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Events\ConsultationCompleted;
use App\Livewire\Doctor\ConsultationRoom;
use App\Livewire\Doctor\MedicalQueue;
use App\Livewire\Patients\CreatePatient;
use App\Livewire\Triage\ClinicalOverrideModal;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\TriageAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CEN-46: flujo completo de la sala de espera, desde la admisión con triage
 * hasta el alta médica (docs/specs/consultas/spec.md, Gherkin de HU03).
 *
 * Admisión + triage (/pacientes/nuevo) ➔ override (/triage) ➔ cola (/consultas)
 * ➔ llamado (volver a espera y ausente) ➔ en consulta ➔ completado.
 */
class MedicalQueueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admision;

    private User $enfermera;

    private User $medico;

    private Neighborhood $barrio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admision = User::factory()->admission()->create();
        $this->enfermera = User::factory()->triageNurse()->create();
        $this->medico = User::factory()->doctor()->create();
        $this->barrio = Neighborhood::factory()->create(['name' => 'San Miguel']);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function admitir(string $dni, string $nombre, string $apellido, array $extra = []): Triage
    {
        $form = Livewire::actingAs($this->admision)->test(CreatePatient::class)->set('dni', $dni);

        $datos = [
            'first_name' => $nombre,
            'last_name' => $apellido,
            'birth_date' => '1985-06-10',
            'gender' => 'otro',
            'address' => 'Calle 1 123',
            'neighborhood_id' => $this->barrio->id,
            'temperature' => '36.8',
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'heart_rate' => 75,
            'respiratory_rate' => 16,
            'spo2' => 98,
            'symptoms' => 'Consulta clínica para evaluación en guardia.',
            ...$extra,
        ];

        foreach ($datos as $campo => $valor) {
            $form->set($campo, $valor);
        }

        $form->call('save')->assertHasNoErrors();

        return Triage::query()->whereRelation('patient', 'dni', $dni)->latest('id')->firstOrFail();
    }

    private function cola(): Testable
    {
        return Livewire::actingAs($this->medico)->test(MedicalQueue::class);
    }

    public function test_circuito_completo_desde_la_admision_hasta_el_alta_medica(): void
    {
        // 1. Admisión + triage unificados: Marta sin urgencia, Carlos febril con sospecha de dengue.
        $marta = $this->admitir('23456789', 'Marta', 'Rodriguez');
        $carlos = $this->admitir('40123456', 'Carlos', 'Villalba', [
            'temperature' => '39.4',
            'discriminators.dengue_suspicion' => true,
        ]);

        $this->assertSame(TriagePriority::BLUE, $marta->final_priority);
        $this->assertSame(TriagePriority::YELLOW, $carlos->final_priority);
        $this->cola()->assertSeeInOrder(['#1', 'Carlos Villalba', '#2', 'Marta Rodriguez']);

        // 2. Override clínico desde /triage: Marta pasa a Naranja con motivo auditado.
        Livewire::actingAs($this->enfermera)
            ->test(ClinicalOverrideModal::class)
            ->dispatch('open-clinical-override', triageId: $marta->id)
            ->set('newPriority', TriagePriority::ORANGE->value)
            ->set('reason', 'Dolor torácico referido durante la espera')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, TriageAuditLog::where('triage_id', $marta->id)->count());

        // 3. Cola médica en /consultas reordenada por MTS.
        $this->actingAs($this->medico)->get('/consultas')->assertOk();
        $cola = $this->cola()->assertSeeInOrder(['#1', 'Marta Rodriguez', '#2', 'Carlos Villalba']);

        // 4. Llamado y retorno a espera conservando la posición.
        $cola->call('callPatient', $marta->id)->assertSee('Llamados a consultorio');
        $this->assertSame(TriageStatus::CALLED, $marta->refresh()->status);

        $cola->call('returnToQueue', $marta->id);
        $this->assertSame(TriageStatus::PENDING_CONSULTATION, $marta->refresh()->status);
        $this->assertSame(1, $marta->estimatedQueuePosition());

        // 5. Carlos es llamado y no se presenta: ausente, sale de la cola y puede re-admitirse.
        $cola->call('callPatient', $carlos->id)->call('markAbsent', $carlos->id);
        $this->assertSame(TriageStatus::CANCELLED, $carlos->refresh()->status);
        $cola->assertSee('Carlos Villalba se marcó como ausente')
            ->assertDontSeeHtml('waiting-'.$carlos->id)
            ->assertDontSeeHtml('called-'.$carlos->id);

        $readmision = $this->admitir('40123456', 'Carlos', 'Villalba');
        $this->assertNotSame($carlos->id, $readmision->id);
        $this->assertSame(1, Patient::where('dni', '40123456')->count());

        // 6. Marta ingresa a consulta con el médico de guardia (vuelve a la terminal médica).
        $cola = $this->cola();
        $cola->call('callPatient', $marta->id)
            ->call('startConsultation', $marta->id)
            ->assertRedirect(route('consultations.show', $marta));
        $marta->refresh();
        $this->assertSame(TriageStatus::IN_CONSULTATION, $marta->status);
        $this->assertSame($this->medico->id, $marta->consultation->doctor_id);
        $this->assertNotNull($marta->consultation->started_at);

        // 7. Alta médica desde el consultorio: el diagnóstico es obligatorio.
        Event::fake([ConsultationCompleted::class]);
        $this->actingAs($this->medico)->get(route('consultations.show', $marta))->assertOk()->assertSee('Marta Rodriguez');

        Livewire::actingAs($this->medico)
            ->test(ConsultationRoom::class, ['triage' => $marta])
            ->call('save')
            ->assertHasErrors(['diagnosis' => 'required'])
            ->set('diagnosis', 'otro_clinico')
            ->set('clinicalNotes', 'Dolor osteomuscular.')
            ->set('prescription', 'Ibuprofeno 400 mg.')
            ->call('save')
            ->assertRedirect(route('consultations.index'));

        $marta->refresh();
        $this->assertSame(TriageStatus::COMPLETED, $marta->status);
        $this->assertSame(ConsultationStatus::COMPLETED, $marta->consultation->status);
        $this->assertSame('otro_clinico', $marta->consultation->diagnosis);
        $this->assertNotNull($marta->consultation->completed_at);
        Event::assertDispatched(ConsultationCompleted::class, fn (ConsultationCompleted $e) => $e->triage->is($marta));

        $this->cola()
            ->assertSee('Consulta de Marta Rodriguez cerrada')
            ->assertDontSeeHtml('consulting-'.$marta->id)
            ->assertDontSeeHtml('waiting-'.$marta->id)
            ->assertSee('Carlos Villalba')
            ->assertSee('#1');

        $this->assertSame([$readmision->id], Triage::query()->active()->pluck('id')->all());
    }

    public function test_admision_y_enfermeria_no_acceden_a_la_cola_medica(): void
    {
        $this->actingAs($this->admision)->get('/consultas')->assertForbidden();
        $this->actingAs($this->enfermera)->get('/consultas')->assertForbidden();
    }
}
