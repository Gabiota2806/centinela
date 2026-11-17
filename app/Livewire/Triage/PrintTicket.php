<?php

declare(strict_types=1);

namespace App\Livewire\Triage;

use App\Models\Triage;
use Illuminate\Contracts\View\View;
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
        $this->authorize('access-triage');
    }

    /**
     * Abre el ticket cuando EvaluatePatient registra un triage.
     */
    #[On('triage-saved')]
    public function open(int $triageId): void
    {
        $this->authorize('access-triage');

        $this->triageId = $triageId;
        $this->show = true;
        unset($this->triage);
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
