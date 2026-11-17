<?php

declare(strict_types=1);

namespace App\Livewire\Triage;

use App\Enums\TriagePriority;
use App\Models\Triage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * CEN-39: Clinical Override rápido con justificación diferida.
 *
 * Se abre con el evento `open-clinical-override` (triageId) para poder reutilizarse
 * desde la pantalla de triage y, más adelante, desde la cola médica (CEN-42).
 * La persistencia y la auditoría inmutable las resuelve Triage::overridePriority() (CEN-40).
 * Referencia: docs/specs/triage/spec.md RF-TRIAGE-10 y RF-TRIAGE-11.
 */
class ClinicalOverrideModal extends Component
{
    public ?int $triageId = null;

    public bool $show = false;

    public ?string $newPriority = null;

    public bool $deferred = false;

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('access-triage');
    }

    #[On('open-clinical-override')]
    public function open(int $triageId): void
    {
        $this->authorize('access-triage');

        $this->resetForm();
        $this->triageId = $triageId;
        $this->show = true;
        unset($this->triage);
    }

    public function close(): void
    {
        $this->resetForm();
        $this->show = false;
    }

    #[Computed]
    public function triage(): ?Triage
    {
        return $this->triageId === null ? null : Triage::with('patient')->find($this->triageId);
    }

    public function save(): void
    {
        $this->authorize('access-triage');

        $triage = $this->triage;
        abort_if($triage === null, 404);

        $this->validate([
            'newPriority' => [
                'required',
                Rule::enum(TriagePriority::class),
                Rule::notIn([$triage->final_priority->value]),
            ],
            'reason' => $this->deferred
                ? ['nullable', 'string', 'max:1000']
                : ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'newPriority.required' => 'Seleccioná el nuevo nivel de prioridad MTS.',
            'newPriority.not_in' => 'El nuevo nivel debe ser distinto de la prioridad actual.',
            'newPriority.enum' => 'El nivel de prioridad seleccionado no es válido.',
            'reason.required' => 'La justificación clínica es obligatoria (o marcá "Justificación diferida" si es una emergencia).',
            'reason.min' => 'La justificación clínica debe tener al menos 10 caracteres.',
            'reason.max' => 'La justificación clínica no puede superar los 1000 caracteres.',
        ]);

        $triage->overridePriority(
            TriagePriority::from($this->newPriority),
            $this->reason,
            Auth::user(),
            $this->deferred,
        );

        $this->dispatch('triage-overridden', triageId: $triage->id);
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.triage.clinical-override-modal', [
            'priorities' => TriagePriority::cases(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['newPriority', 'deferred', 'reason']);
        $this->resetValidation();
    }
}
