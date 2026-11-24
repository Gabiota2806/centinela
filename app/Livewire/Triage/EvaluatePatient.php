<?php

declare(strict_types=1);

namespace App\Livewire\Triage;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Models\Patient;
use App\Models\Triage;
use App\Services\ManchesterTriageService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente reactivo para gestión y evaluación clínica de triage según el Sistema Manchester (MTS).
 * Referencia: docs/specs/triage/spec.md §2 (RF-TRIAGE-01 a RF-TRIAGE-15).
 */
class EvaluatePatient extends Component
{
    use WithPagination;

    public ?int $patientId = null;

    public ?Patient $patient = null;

    // Signos vitales cuantitativos
    public ?float $temperature = null;

    public ?int $systolic_bp = null;

    public ?int $diastolic_bp = null;

    public ?int $heart_rate = null;

    public ?int $respiratory_rate = null;

    public ?int $spo2 = null;

    // Motivo de consulta y síntomas referidos
    public string $symptoms = '';

    /**
     * Discriminadores clínicos seleccionados [codigo => bool].
     *
     * @var array<string, bool>
     */
    public array $discriminators = [];

    // Cálculo reactivo en tiempo real
    public string $priorityValue = 'blue';

    public string $priorityLabel = 'Azul (No Urgente)';

    public int $targetWaitMinutes = 240;

    public string $activeDiscriminator = 'Consulta no urgente / chequeo general';

    public string $badgeClasses = 'bg-blue-600 text-white';

    // Estado del componente
    public bool $savedSuccessfully = false;

    public ?int $lastSavedTriageId = null;

    public string $savedMessage = '';

    public string $searchPatient = '';

    // Gestión de pestañas, búsqueda por DNI/nombre y edición auditada (RF-TRIAGE-12 / RF-TRIAGE-13)
    public string $activeTab = 'waiting';

    public string $searchTriage = '';

    public ?int $editingTriageId = null;

    public ?string $editingTicketNumber = null;

    public ?string $editingCurrentPriorityValue = null;

    public ?string $editingCurrentPriorityLabel = null;

    public string $editingCurrentPriorityBadgeClasses = '';

    public function mount(?Patient $patient = null, ?int $patientId = null): void
    {
        $this->authorize('access-triage');

        $resolved = $patient ?? $this->patient;
        if ($resolved instanceof Patient) {
            $this->selectPatient($resolved->id);
        } elseif ($patientId !== null) {
            $this->selectPatient($patientId);
        } elseif (request()->filled('patient')) {
            $this->selectPatient(request()->integer('patient'));
        }

        $this->recalculatePriority();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['waiting', 'history'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetPage('historyPage');
    }

    public function selectPatient(int $id): void
    {
        $this->patientId = $id;
        $this->patient = Patient::with('neighborhood')->find($id);
    }

    public function deselectPatient(): void
    {
        $this->patientId = null;
        $this->patient = null;
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'searchTriage') {
            $this->resetPage('historyPage');

            return;
        }

        $this->recalculatePriority();
    }

    /**
     * Carga un triage pendiente (pending_consultation) en el formulario para editar
     * sus signos vitales, motivo de consulta y discriminadores con auditoría.
     */
    public function startEditing(int $triageId): void
    {
        $this->authorize('access-triage');

        $triage = Triage::with('patient.neighborhood')->find($triageId);

        if ($triage === null) {
            $this->addError('editing', 'El triage seleccionado no existe.');

            return;
        }

        if ($triage->status !== TriageStatus::PENDING_CONSULTATION) {
            $this->addError('editing', 'Solo se pueden editar triages que se encuentran en espera de consulta.');

            return;
        }

        $this->resetValidation();
        $this->savedSuccessfully = false;

        $this->editingTriageId = $triage->id;
        $this->editingTicketNumber = PrintTicket::ticketNumber($triage->id);
        $this->editingCurrentPriorityValue = $triage->final_priority->value;
        $this->editingCurrentPriorityLabel = $triage->final_priority->label();
        $this->editingCurrentPriorityBadgeClasses = $triage->final_priority->badgeClasses();

        $this->patientId = $triage->patient_id;
        $this->patient = $triage->patient;
        $this->symptoms = $triage->symptoms;

        $vitals = is_array($triage->vital_signs) ? $triage->vital_signs : [];
        $this->temperature = isset($vitals['temperature']) && is_numeric($vitals['temperature']) ? (float) $vitals['temperature'] : null;
        $this->systolic_bp = isset($vitals['systolic_bp']) && is_numeric($vitals['systolic_bp']) ? (int) $vitals['systolic_bp'] : null;
        $this->diastolic_bp = isset($vitals['diastolic_bp']) && is_numeric($vitals['diastolic_bp']) ? (int) $vitals['diastolic_bp'] : null;
        $this->heart_rate = isset($vitals['heart_rate']) && is_numeric($vitals['heart_rate']) ? (int) $vitals['heart_rate'] : null;
        $this->respiratory_rate = isset($vitals['respiratory_rate']) && is_numeric($vitals['respiratory_rate']) ? (int) $vitals['respiratory_rate'] : null;
        $this->spo2 = isset($vitals['spo2']) && is_numeric($vitals['spo2']) ? (int) $vitals['spo2'] : null;

        $savedDiscriminators = is_array($vitals['discriminators'] ?? null) ? $vitals['discriminators'] : [];
        $this->discriminators = [];
        foreach ($savedDiscriminators as $code) {
            if (is_string($code) && $code !== '') {
                $this->discriminators[$code] = true;
            }
        }

        $this->recalculatePriority();
        $this->dispatch('triage-edit-started');
    }

    public function cancelEditing(): void
    {
        $this->resetEvaluation();
    }

    public function recalculatePriority(): void
    {
        /** @var ManchesterTriageService $service */
        $service = app(ManchesterTriageService::class);

        $vitalSigns = [
            'temperature' => $this->temperature,
            'systolic_bp' => $this->systolic_bp,
            'diastolic_bp' => $this->diastolic_bp,
            'heart_rate' => $this->heart_rate,
            'respiratory_rate' => $this->respiratory_rate,
            'spo2' => $this->spo2,
        ];

        $evaluation = $service->evaluate($vitalSigns, $this->discriminators);

        /** @var TriagePriority $priority */
        $priority = $evaluation['priority'];

        $this->priorityValue = $priority->value;
        $this->priorityLabel = $priority->label();
        $this->targetWaitMinutes = $evaluation['target_wait_minutes'];
        $this->activeDiscriminator = $evaluation['discriminator'];
        $this->badgeClasses = $priority->badgeClasses();
    }

    public function save(): void
    {
        $this->authorize('access-triage');

        $this->validate([
            'patientId' => ['required', 'exists:patients,id'],
            'symptoms' => ['required', 'string', 'min:3'],
            'temperature' => ['nullable', 'numeric', 'between:30,45'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,260'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,180'],
            'heart_rate' => ['nullable', 'integer', 'between:20,260'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'spo2' => ['nullable', 'integer', 'between:40,100'],
        ], [
            'patientId.required' => 'Debe seleccionar un paciente para categorizar el triage.',
            'patientId.exists' => 'El paciente seleccionado no es válido.',
            'symptoms.required' => 'El motivo de consulta / síntomas es obligatorio.',
            'symptoms.min' => 'El motivo de consulta debe tener al menos 3 caracteres.',
            'temperature.between' => 'La temperatura debe encontrarse entre 30°C y 45°C.',
            'systolic_bp.between' => 'La presión sistólica debe encontrarse entre 40 y 260 mmHg.',
            'diastolic_bp.between' => 'La presión diastólica debe encontrarse entre 20 y 180 mmHg.',
            'heart_rate.between' => 'La frecuencia cardíaca debe encontrarse entre 20 y 260 lpm.',
            'respiratory_rate.between' => 'La frecuencia respiratoria debe encontrarse entre 5 y 80 rpm.',
            'spo2.between' => 'La saturación de oxígeno (SpO2) debe encontrarse entre 40% y 100%.',
        ]);

        $vitalSigns = [
            'temperature' => $this->temperature,
            'systolic_bp' => $this->systolic_bp,
            'diastolic_bp' => $this->diastolic_bp,
            'heart_rate' => $this->heart_rate,
            'respiratory_rate' => $this->respiratory_rate,
            'spo2' => $this->spo2,
        ];

        if ($this->editingTriageId !== null) {
            $triage = Triage::with('patient')->find($this->editingTriageId);

            if ($triage === null || $triage->status !== TriageStatus::PENDING_CONSULTATION) {
                $this->addError('editing', 'El triage seleccionado ya no se encuentra en espera de consulta y no puede editarse.');

                return;
            }

            try {
                $auditLog = $triage->updateEvaluation(
                    $vitalSigns,
                    $this->symptoms,
                    $this->discriminators,
                    Auth::user()
                );
            } catch (InvalidArgumentException $exception) {
                $this->addError('editing', $exception->getMessage());

                return;
            }

            $patientName = $triage->patient->full_name;
            $ticketNumber = PrintTicket::ticketNumber($triage->id);
            $newPriorityLabel = $triage->final_priority->label();
            $waitMinutes = $triage->target_wait_minutes;

            $this->resetEvaluation();

            $this->savedSuccessfully = true;
            $this->lastSavedTriageId = $triage->id;
            $this->savedMessage = $auditLog !== null
                ? "Triage #{$ticketNumber} de {$patientName} actualizado. Prioridad recalculada a {$newPriorityLabel} con auditoría registrada (Espera máxima: {$waitMinutes} min)."
                : "Triage #{$ticketNumber} de {$patientName} actualizado sin cambios en prioridad ({$newPriorityLabel} · Espera máxima: {$waitMinutes} min).";

            $this->dispatch('triage-updated', triageId: $triage->id);

            return;
        }

        /** @var ManchesterTriageService $service */
        $service = app(ManchesterTriageService::class);
        $normalizedVitals = $service->normalizeVitalSigns($vitalSigns);
        $evaluation = $service->evaluate($normalizedVitals, $this->discriminators);
        $priority = $evaluation['priority'];
        $activeDiscriminatorCodes = array_keys(array_filter($this->discriminators));

        $triage = Triage::create([
            'patient_id' => $this->patientId,
            'user_id' => Auth::id(),
            'symptoms' => trim($this->symptoms),
            'vital_signs' => array_merge($normalizedVitals, [
                'discriminators' => $activeDiscriminatorCodes,
                'calculated_discriminator' => $evaluation['discriminator'],
            ]),
            'algorithmic_priority' => $priority,
            'final_priority' => $priority,
            'is_override' => false,
            'target_wait_minutes' => $evaluation['target_wait_minutes'],
            'status' => TriageStatus::PENDING_CONSULTATION,
        ]);

        $patientName = $this->patient?->full_name ?? '';

        $this->resetEvaluation();

        $this->savedSuccessfully = true;
        $this->lastSavedTriageId = $triage->id;
        $this->savedMessage = "Triage registrado con éxito para {$patientName} en prioridad {$priority->label()} (Espera máxima: {$evaluation['target_wait_minutes']} min).";

        $this->dispatch('triage-saved', triageId: $triage->id);
    }

    public function showTicket(): void
    {
        if ($this->lastSavedTriageId !== null) {
            $this->dispatch('triage-saved', triageId: $this->lastSavedTriageId);
        }
    }

    public function showTicketFor(int $triageId): void
    {
        $this->authorize('access-triage');

        $this->dispatch('triage-saved', triageId: $triageId);
    }

    public function openOverride(): void
    {
        if ($this->lastSavedTriageId !== null) {
            $this->dispatch('open-clinical-override', triageId: $this->lastSavedTriageId);
        }
    }

    public function openOverrideFor(int $triageId): void
    {
        $this->authorize('access-triage');

        $this->dispatch('open-clinical-override', triageId: $triageId);
    }

    #[On('triage-overridden')]
    public function onTriageOverridden(int $triageId): void
    {
        if ($this->editingTriageId === $triageId) {
            $triage = Triage::find($triageId);
            if ($triage !== null && $triage->status === TriageStatus::PENDING_CONSULTATION) {
                $this->editingCurrentPriorityValue = $triage->final_priority->value;
                $this->editingCurrentPriorityLabel = $triage->final_priority->label();
                $this->editingCurrentPriorityBadgeClasses = $triage->final_priority->badgeClasses();
            } else {
                $this->resetEvaluation();
            }
        }

        if ($triageId !== $this->lastSavedTriageId) {
            return;
        }

        $triage = Triage::with('patient')->find($triageId);
        if ($triage === null) {
            return;
        }

        $this->savedMessage = "Triage de {$triage->patient->full_name} reclasificado a prioridad {$triage->final_priority->label()} (Espera máxima: {$triage->target_wait_minutes} min).";
    }

    #[On('triage-updated')]
    #[On('patient-triaged')]
    public function refreshTriageLists(): void
    {
        // Dispara re-renderizado reactivo de las pestañas "En espera" e "Historial".
    }

    public function resetEvaluation(): void
    {
        $this->reset([
            'patientId',
            'patient',
            'temperature',
            'systolic_bp',
            'diastolic_bp',
            'heart_rate',
            'respiratory_rate',
            'spo2',
            'symptoms',
            'discriminators',
            'savedSuccessfully',
            'lastSavedTriageId',
            'savedMessage',
            'searchPatient',
            'editingTriageId',
            'editingTicketNumber',
            'editingCurrentPriorityValue',
            'editingCurrentPriorityLabel',
            'editingCurrentPriorityBadgeClasses',
        ]);
        $this->resetValidation();

        $this->recalculatePriority();
    }

    /**
     * @param  Builder<Triage>  $query
     */
    private function applyPatientSearch(Builder $query, string $term): void
    {
        $clean = trim($term);
        if ($clean === '') {
            return;
        }

        $like = '%'.mb_strtolower($clean).'%';

        $query->whereHas('patient', function (Builder $patientQuery) use ($clean, $like): void {
            $patientQuery->where('dni', 'like', "%{$clean}%")
                ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                ->orWhereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", [$like]);
        });
    }

    public function render(): View
    {
        /** @var ManchesterTriageService $service */
        $service = app(ManchesterTriageService::class);

        $queuePositions = Triage::pendingQueuePositions();

        $waitingBase = Triage::query()->whereIn('status', [
            TriageStatus::PENDING_CONSULTATION->value,
            TriageStatus::CALLED->value,
        ]);
        $this->applyPatientSearch($waitingBase, $this->searchTriage);

        $historyBase = Triage::query()->whereIn('status', [
            TriageStatus::IN_CONSULTATION->value,
            TriageStatus::COMPLETED->value,
            TriageStatus::CANCELLED->value,
        ]);
        $this->applyPatientSearch($historyBase, $this->searchTriage);

        $waitingCount = (clone $waitingBase)->count();
        $historyCount = (clone $historyBase)->count();

        $waitingTriages = collect();
        $historyTriages = null;

        if ($this->activeTab === 'waiting') {
            $waitingTriages = (clone $waitingBase)
                ->with(['patient.neighborhood', 'user'])
                ->withCount('auditLogs')
                ->orderByRaw("CASE WHEN status = 'called' THEN 0 ELSE 1 END ASC")
                ->orderedByPriority()
                ->get();
        } else {
            $historyTriages = (clone $historyBase)
                ->with(['patient.neighborhood', 'user'])
                ->withCount('auditLogs')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(10, ['*'], 'historyPage');
        }

        $patients = [];
        if ($this->patientId === null) {
            $query = Patient::with('neighborhood')->latest('id');
            if (! empty($this->searchPatient)) {
                $term = trim($this->searchPatient);
                $query->where(function ($q) use ($term) {
                    $q->where('dni', 'like', "%{$term}%")
                        ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.strtolower($term).'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.strtolower($term).'%']);
                });
            }
            $patients = $query->take(6)->get();
        }

        return view('livewire.triage.evaluate-patient', [
            'catalogue' => $service->getDiscriminatorCatalogue(),
            'availablePatients' => $patients,
            'waitingTriages' => $waitingTriages,
            'historyTriages' => $historyTriages,
            'waitingCount' => $waitingCount,
            'historyCount' => $historyCount,
            'queuePositions' => $queuePositions,
        ]);
    }
}
