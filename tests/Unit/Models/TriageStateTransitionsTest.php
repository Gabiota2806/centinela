<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\ConsultationStatus;
use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Models\Consultation;
use App\Models\Triage;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CEN-44: máquina de estados clínicos del triage
 * (docs/specs/00_contracts.md §1.2 y docs/specs/consultas/spec.md RF-CONS-05).
 */
class TriageStateTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private function triage(TriageStatus $status = TriageStatus::PENDING_CONSULTATION, array $attributes = []): Triage
    {
        return Triage::factory()->create(['status' => $status, ...$attributes]);
    }

    public function test_llamar_a_consultorio_pasa_de_espera_a_llamado(): void
    {
        $triage = $this->triage();

        $triage->callForConsultation();

        $this->assertSame(TriageStatus::CALLED, $triage->refresh()->status);
    }

    public function test_ingresar_a_consulta_crea_la_consulta_en_curso_con_el_medico(): void
    {
        Carbon::setTestNow('2026-11-24 15:30:00');
        $triage = $this->triage(TriageStatus::CALLED);
        $medico = User::factory()->doctor()->create();

        $consulta = $triage->startConsultation($medico);

        $this->assertSame(TriageStatus::IN_CONSULTATION, $triage->refresh()->status);
        $this->assertTrue($consulta->exists);
        $this->assertSame($triage->id, $consulta->triage_id);
        $this->assertSame($medico->id, $consulta->doctor_id);
        $this->assertSame(ConsultationStatus::IN_PROGRESS, $consulta->status);
        $this->assertTrue($consulta->started_at->equalTo(now()));
        $this->assertNull($consulta->completed_at);
    }

    public function test_volver_a_espera_conserva_la_posicion_fifo_original(): void
    {
        $primero = $this->triage(TriageStatus::PENDING_CONSULTATION, [
            'final_priority' => TriagePriority::YELLOW,
            'created_at' => now()->subMinutes(30),
        ]);
        $segundo = $this->triage(TriageStatus::PENDING_CONSULTATION, [
            'final_priority' => TriagePriority::YELLOW,
            'created_at' => now()->subMinutes(10),
        ]);
        $llegadaOriginal = $primero->created_at->toIso8601String();

        $primero->callForConsultation();
        $this->assertSame(1, $segundo->estimatedQueuePosition());

        $primero->returnToQueue();

        $primero->refresh();
        $this->assertSame(TriageStatus::PENDING_CONSULTATION, $primero->status);
        $this->assertSame($llegadaOriginal, $primero->created_at->toIso8601String());
        $this->assertSame(1, $primero->estimatedQueuePosition());
        $this->assertSame(2, $segundo->estimatedQueuePosition());
    }

    public function test_ausente_cancela_el_triage_y_libera_el_episodio_activo(): void
    {
        $triage = $this->triage(TriageStatus::CALLED);

        $triage->markAsAbsent();

        $this->assertSame(TriageStatus::CANCELLED, $triage->refresh()->status);
        $this->assertFalse($triage->patient->triages()->active()->exists());
    }

    public function test_el_scope_active_considera_espera_llamado_y_en_consulta(): void
    {
        foreach (TriageStatus::cases() as $status) {
            $this->triage($status);
        }

        $activos = Triage::query()->active()->pluck('status')->map->value->sort()->values()->all();

        $this->assertSame(['called', 'in_consultation', 'pending_consultation'], $activos);
    }

    public function test_finalizar_la_consulta_cierra_consulta_y_triage(): void
    {
        Carbon::setTestNow('2026-11-24 16:00:00');
        $triage = $this->triage(TriageStatus::CALLED);
        $triage->startConsultation(User::factory()->doctor()->create());

        $consulta = $triage->finishConsultation(
            ConsultationStatus::COMPLETED,
            'sospecha_dengue',
            'Fiebre de 3 días con mialgias.',
            'Paracetamol 500 mg c/8 h e hidratación.',
        );

        $this->assertSame(TriageStatus::COMPLETED, $triage->refresh()->status);
        $this->assertSame(ConsultationStatus::COMPLETED, $consulta->status);
        $this->assertSame('sospecha_dengue', $consulta->diagnosis);
        $this->assertSame('Fiebre de 3 días con mialgias.', $consulta->clinical_notes);
        $this->assertSame('Paracetamol 500 mg c/8 h e hidratación.', $consulta->prescription);
        $this->assertTrue($consulta->completed_at->equalTo(now()));
    }

    public function test_finalizar_permite_derivar_por_sipec(): void
    {
        $triage = $this->triage(TriageStatus::CALLED);
        $triage->startConsultation(User::factory()->doctor()->create());

        $consulta = $triage->finishConsultation(ConsultationStatus::REFERRED_SIPEC, 'ira');

        $this->assertSame(ConsultationStatus::REFERRED_SIPEC, $consulta->status);
        $this->assertSame(TriageStatus::COMPLETED, $triage->refresh()->status);
    }

    public function test_finalizar_no_acepta_el_estado_en_curso(): void
    {
        $triage = $this->triage(TriageStatus::CALLED);
        $triage->startConsultation(User::factory()->doctor()->create());

        $this->expectException(DomainException::class);
        $triage->finishConsultation(ConsultationStatus::IN_PROGRESS, 'ira');
    }

    public function test_dos_terminales_no_pueden_llamar_al_mismo_paciente(): void
    {
        $triage = $this->triage();
        $copiaDesactualizada = Triage::findOrFail($triage->id);

        $triage->callForConsultation();

        $this->expectException(DomainException::class);
        $copiaDesactualizada->callForConsultation();
    }

    /**
     * @return array<string, array{0: TriageStatus, 1: string}>
     */
    public static function transicionesInvalidas(): array
    {
        return [
            'llamar a quien ya fue llamado' => [TriageStatus::CALLED, 'call'],
            'llamar a quien está en consulta' => [TriageStatus::IN_CONSULTATION, 'call'],
            'llamar a un completado' => [TriageStatus::COMPLETED, 'call'],
            'ingresar sin haber sido llamado' => [TriageStatus::PENDING_CONSULTATION, 'start'],
            'ingresar un cancelado' => [TriageStatus::CANCELLED, 'start'],
            'devolver a espera sin llamado' => [TriageStatus::PENDING_CONSULTATION, 'return'],
            'devolver a espera en consulta' => [TriageStatus::IN_CONSULTATION, 'return'],
            'ausente sin llamado' => [TriageStatus::PENDING_CONSULTATION, 'absent'],
            'ausente en consulta' => [TriageStatus::IN_CONSULTATION, 'absent'],
            'finalizar sin estar en consulta' => [TriageStatus::CALLED, 'finish'],
            'finalizar un completado' => [TriageStatus::COMPLETED, 'finish'],
        ];
    }

    #[DataProvider('transicionesInvalidas')]
    public function test_las_transiciones_invalidas_lanzan_excepcion_de_dominio(TriageStatus $desde, string $accion): void
    {
        $triage = $this->triage($desde);

        try {
            match ($accion) {
                'call' => $triage->callForConsultation(),
                'start' => $triage->startConsultation(User::factory()->doctor()->create()),
                'return' => $triage->returnToQueue(),
                'absent' => $triage->markAsAbsent(),
                'finish' => $triage->finishConsultation(ConsultationStatus::COMPLETED, 'ira'),
            };
            $this->fail('Se esperaba DomainException.');
        } catch (DomainException) {
            $this->assertSame($desde, $triage->refresh()->status);
            $this->assertSame(0, Consultation::count());
        }
    }
}
