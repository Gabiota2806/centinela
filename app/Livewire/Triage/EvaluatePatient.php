<?php

declare(strict_types=1);

namespace App\Livewire\Triage;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Models\Patient;
use App\Models\Triage;
use App\Services\ManchesterTriageService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Componente reactivo para evaluación clínica de triage según el Sistema Manchester (MTS).
 * Referencia: docs/specs/triage/spec.md §5 y docs/backlog.md §CEN-35.
 */
class EvaluatePatient extends Component
{
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

    public string $searchPatient = '';

    public function mount(?Patient $patient = null, ?int $patientId = null): void
    {
        $this->authorize('access-triage');

        $resolved = $patient ?? $this->patient;
        if ($resolved instanceof Patient) {
            $this->selectPatient($resolved->id);
        } elseif ($patientId !== null) {
            $this->selectPatient($patientId);
        }

        $this->recalculatePriority();
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
        $this->recalculatePriority();
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
        $priority = $evaluation['priority'];

        $activeDiscriminatorCodes = array_keys(array_filter($this->discriminators));

        $triage = Triage::create([
            'patient_id' => $this->patientId,
            'user_id' => Auth::id(),
            'symptoms' => $this->symptoms,
            'vital_signs' => array_merge($vitalSigns, [
                'discriminators' => $activeDiscriminatorCodes,
                'calculated_discriminator' => $evaluation['discriminator'],
            ]),
            'algorithmic_priority' => $priority,
            'final_priority' => $priority,
            'is_override' => false,
            'target_wait_minutes' => $evaluation['target_wait_minutes'],
            'status' => TriageStatus::PENDING_CONSULTATION,
        ]);

        $this->savedSuccessfully = true;
        $this->lastSavedTriageId = $triage->id;

        session()->flash('status', "Triage registrado con éxito en prioridad {$priority->label()} (Espera máxima: {$evaluation['target_wait_minutes']} min).");
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
            'searchPatient',
        ]);

        $this->recalculatePriority();
    }

    public function render(): View
    {
        /** @var ManchesterTriageService $service */
        $service = app(ManchesterTriageService::class);

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
        ]);
    }
}
