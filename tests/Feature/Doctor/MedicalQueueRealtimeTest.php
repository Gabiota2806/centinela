<?php

declare(strict_types=1);

namespace Tests\Feature\Doctor;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Livewire\Doctor\MedicalQueue;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-43: actualización reactiva de la cola médica
 * (docs/specs/consultas/spec.md RF-CONS-03 y RF-CONS-04).
 */
class MedicalQueueRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function paciente(string $nombre, TriagePriority $prioridad, int $minutosAtras): Triage
    {
        [$first, $last] = explode(' ', $nombre);

        return Triage::factory()->create([
            'patient_id' => Patient::factory()->create(['first_name' => $first, 'last_name' => $last])->id,
            'algorithmic_priority' => $prioridad,
            'final_priority' => $prioridad,
            'target_wait_minutes' => $prioridad->targetWaitMinutes(),
            'status' => TriageStatus::PENDING_CONSULTATION,
            'created_at' => now()->subMinutes($minutosAtras),
        ]);
    }

    public function test_la_cola_se_sincroniza_entre_terminales_cada_5_segundos(): void
    {
        $html = Livewire::actingAs(User::factory()->doctor()->create())
            ->test(MedicalQueue::class)
            ->html();

        $this->assertStringContainsString('wire:poll.5s', $html);
    }

    public function test_un_override_reordena_la_cola_sin_recargar(): void
    {
        $this->paciente('Ana Verde', TriagePriority::YELLOW, 40);
        $bruno = $this->paciente('Bruno Azul', TriagePriority::BLUE, 30);
        $doctor = User::factory()->doctor()->create();

        $cola = Livewire::actingAs($doctor)
            ->test(MedicalQueue::class)
            ->assertSeeInOrder(['Ana Verde', 'Bruno Azul']);

        $bruno->overridePriority(TriagePriority::RED, '', $doctor, deferred: true);

        $cola->dispatch('triage-overridden', triageId: $bruno->id)
            ->assertSeeInOrder(['#1', 'Bruno Azul', '#2', 'Ana Verde'])
            ->assertSee('Atención inmediata');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function eventosDeCola(): array
    {
        return [
            'paciente clasificado' => ['patient-triaged'],
            'triage editado' => ['triage-updated'],
        ];
    }

    #[DataProvider('eventosDeCola')]
    public function test_los_eventos_de_triage_refrescan_la_cola(string $evento): void
    {
        $cola = Livewire::actingAs(User::factory()->doctor()->create())
            ->test(MedicalQueue::class)
            ->assertSee('No hay pacientes en espera');

        $nuevo = $this->paciente('Carla Nueva', TriagePriority::ORANGE, 1);

        $cola->dispatch($evento, triageId: $nuevo->id)
            ->assertSee('Carla Nueva')
            ->assertDontSee('No hay pacientes en espera');
    }

    public function test_un_cambio_hecho_en_otra_terminal_aparece_al_sincronizar(): void
    {
        $triage = $this->paciente('Dora Llamada', TriagePriority::GREEN, 10);

        $cola = Livewire::actingAs(User::factory()->doctor()->create())
            ->test(MedicalQueue::class)
            ->assertSee('Llamar a consultorio');

        // Otra terminal llama al paciente; el sondeo vuelve a renderizar el componente.
        $triage->callForConsultation();

        $cola->call('$refresh')
            ->assertSee('Llamados a consultorio')
            ->assertSee('Volver a espera');
    }

    public function test_actuar_sobre_un_paciente_ya_llamado_en_otra_terminal_avisa_el_conflicto(): void
    {
        $triage = $this->paciente('Eva Doble', TriagePriority::GREEN, 10);

        $cola = Livewire::actingAs(User::factory()->doctor()->create())->test(MedicalQueue::class);

        Triage::findOrFail($triage->id)->callForConsultation();

        $cola->call('callPatient', $triage->id)
            ->assertSee('No se puede')
            ->assertSee('Volver a espera');
    }
}
