<?php

declare(strict_types=1);

namespace App\Livewire\Patients;

use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CEN-73: padrón y buscador de pacientes del CAPS en /pacientes (docs/specs/pacientes/spec.md RF-PAC-08).
 */
class PatientDirectory extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('access-admission');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('access-admission');

        $term = trim($this->search);

        $query = Patient::query()
            ->with('neighborhood')
            ->withCount('triages')
            ->withExists([
                'triages as has_active_triage' => fn (Builder $triageQuery) => $triageQuery->whereIn('status', CreatePatient::ACTIVE_TRIAGE_STATUSES),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($term !== '') {
            $like = '%'.mb_strtolower($term).'%';

            $query->where(function (Builder $patientQuery) use ($term, $like): void {
                $patientQuery->where('dni', 'like', "%{$term}%")
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(last_name || ' ' || first_name) LIKE ?", [$like]);
            });
        }

        return view('livewire.patients.patient-directory', [
            'patients' => $query->paginate(12),
            'hasSearch' => $term !== '',
        ]);
    }
}
