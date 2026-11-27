<?php

declare(strict_types=1);

namespace App\Livewire\Triage;

use App\Models\Triage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * CEN-37: ticket digital del triage recién registrado (docs/specs/triage/spec.md RF-TRIAGE-07/08).
 */
class PrintTicket extends Component
{
    public ?int $triageId = null;

    public bool $show = false;

    public function mount(): void
    {
        abort_unless(Gate::any(['access-admission', 'access-triage']), 403);
    }

    /**
     * Abre el ticket cuando admisión o triage registra una atención.
     */
    #[On('triage-saved')]
    public function open(int $triageId): void
    {
        abort_unless(Gate::any(['access-admission', 'access-triage']), 403);

        $this->triageId = $triageId;
        $this->show = true;
        unset($this->triage, $this->queuePosition);
    }

    /**
     * Recalcula datos y posición estimada en cola tras un Clinical Override o edición.
     */
    #[On('triage-overridden')]
    #[On('triage-updated')]
    public function refreshTicket(?int $triageId = null): void
    {
        unset($this->triage, $this->queuePosition);
    }

    /**
     * Cierra el ticket y abre el modal de Clinical Override del mismo triage (solo roles con access-triage).
     */
    public function reclassify(): void
    {
        $this->authorize('access-triage');

        $this->show = false;

        if ($this->triageId !== null) {
            $this->dispatch('open-clinical-override', triageId: $this->triageId);
        }
    }

    public function close(): void
    {
        $this->show = false;
    }

    /**
     * Triage con paciente, barrio y profesional en una sola consulta.
     */
    #[Computed]
    public function triage(): ?Triage
    {
        if ($this->triageId === null) {
            return null;
        }

        return Triage::with(['patient.neighborhood', 'user'])->find($this->triageId);
    }

    /**
     * Posición estimada (#N) dentro de la lista de espera activa (pending_consultation).
     */
    #[Computed]
    public function queuePosition(): ?int
    {
        return $this->triage?->estimatedQueuePosition();
    }

    /**
     * Número de turno visible en el ticket (TR-00123).
     */
    public static function ticketNumber(int $triageId): string
    {
        return 'TR-'.str_pad((string) $triageId, 5, '0', STR_PAD_LEFT);
    }

    public function render(): View
    {
        return view('livewire.triage.ticket-modal');
    }
}
