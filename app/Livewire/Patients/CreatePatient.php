<?php

declare(strict_types=1);

namespace App\Livewire\Patients;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Livewire\Triage\PrintTicket;
use App\Models\Neighborhood;
use App\Models\Patient;
use App\Models\Triage;
use App\Services\ManchesterTriageService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * CEN-34 / CEN-74: admisión + triage unificados en /pacientes/nuevo (docs/specs/pacientes/spec.md).
 */
class CreatePatient extends Component
{
    /** Estados de triage que bloquean un nuevo ingreso duplicado en la cola. */
    public const ACTIVE_TRIAGE_STATUSES = [
        'pending_consultation',
        'called',
        'in_consultation',
    ];

    public const GENDERS = [
        'femenino' => 'Femenino',
        'masculino' => 'Masculino',
        'otro' => 'Otro',
    ];

    public string $dni = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $birth_date = '';

    public string $gender = '';

    public string $phone = '';

    public string $address = '';

    /** @var int|string */
    public $neighborhood_id = '';

    /** Término de búsqueda para el autocompletado reactivo de barrios. */
    public string $neighborhoodSearch = '';

    /** Nombre del barrio seleccionado para visualización en la interfaz. */
    public string $selectedNeighborhoodName = '';

    /** Estado de detección de paciente recurrente por DNI y bloqueo por triage activo. */
    public ?int $existingPatientId = null;

    public bool $isRecurringPatient = false;

    public ?int $activeTriageId = null;

    public ?string $activeTriageTicket = null;

    /** Signos vitales cuantitativos obligatorios (RF-PAC-06). */
    public string|float|null $temperature = '';

    public string|int|null $systolic_bp = '';

    public string|int|null $diastolic_bp = '';

    public string|int|null $heart_rate = '';

    public string|int|null $respiratory_rate = '';

    public string|int|null $spo2 = '';

    /** Motivo de consulta y síntomas (obligatorio, ubicado antes de discriminadores). */
    public string $symptoms = '';

    /**
     * Discriminadores clínicos MTS opcionales [codigo => bool].
     *
     * @var array<string, bool>
     */
    public array $discriminators = [];

    /** Cálculo reactivo MTS en tiempo real. */
    public string $priorityValue = 'blue';

    public string $priorityLabel = 'Azul (No Urgente)';

    public int $targetWaitMinutes = 240;

    public string $activeDiscriminator = 'Consulta no urgente / chequeo general';

    public string $badgeClasses = 'bg-mts-blue text-white';

    /** Confirmación del último ingreso unificado registrado. */
    public ?int $createdPatientId = null;

    public string $createdPatientName = '';

    public string $createdPatientDni = '';

    public ?int $createdTriageId = null;

    public string $createdTicketNumber = '';

    public string $createdPriorityLabel = '';

    public string $createdPriorityBadgeClasses = '';

    public int $createdTargetWaitMinutes = 240;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'dni' => ['required', 'regex:/^\d+$/', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(array_keys(self::GENDERS))],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'neighborhood_id' => ['required', 'integer', Rule::exists('neighborhoods', 'id')],
            'temperature' => ['required', 'numeric', 'between:30,45'],
            'systolic_bp' => ['required', 'integer', 'between:40,260'],
            'diastolic_bp' => ['required', 'integer', 'between:20,180'],
            'heart_rate' => ['required', 'integer', 'between:20,260'],
            'respiratory_rate' => ['required', 'integer', 'between:5,80'],
            'spo2' => ['required', 'integer', 'between:40,100'],
            'symptoms' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'max' => 'No puede superar los :max caracteres.',
            'dni.regex' => 'El DNI solo puede contener números.',
            'birth_date.date' => 'Ingresá una fecha válida.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'gender.in' => 'Elegí una opción de la lista.',
            'neighborhood_id.integer' => 'Elegí un barrio de la lista.',
            'neighborhood_id.exists' => 'Elegí un barrio de la lista.',
            'temperature.required' => 'La temperatura es obligatoria.',
            'temperature.numeric' => 'Ingresá un valor numérico válido para la temperatura.',
            'temperature.between' => 'La temperatura debe encontrarse entre 30°C y 45°C.',
            'systolic_bp.required' => 'La presión sistólica es obligatoria.',
            'systolic_bp.integer' => 'Ingresá un número entero para la presión sistólica.',
            'systolic_bp.between' => 'La presión sistólica debe encontrarse entre 40 y 260 mmHg.',
            'diastolic_bp.required' => 'La presión diastólica es obligatoria.',
            'diastolic_bp.integer' => 'Ingresá un número entero para la presión diastólica.',
            'diastolic_bp.between' => 'La presión diastólica debe encontrarse entre 20 y 180 mmHg.',
            'heart_rate.required' => 'La frecuencia cardíaca es obligatoria.',
            'heart_rate.integer' => 'Ingresá un número entero para la frecuencia cardíaca.',
            'heart_rate.between' => 'La frecuencia cardíaca debe encontrarse entre 20 y 260 lpm.',
            'respiratory_rate.required' => 'La frecuencia respiratoria es obligatoria.',
            'respiratory_rate.integer' => 'Ingresá un número entero para la frecuencia respiratoria.',
            'respiratory_rate.between' => 'La frecuencia respiratoria debe encontrarse entre 5 y 80 rpm.',
            'spo2.required' => 'La saturación de oxígeno (SpO2) es obligatoria.',
            'spo2.integer' => 'Ingresá un número entero para la saturación de oxígeno.',
            'spo2.between' => 'La saturación de oxígeno (SpO2) debe encontrarse entre 40% y 100%.',
            'symptoms.required' => 'El motivo de consulta / síntomas es obligatorio.',
            'symptoms.min' => 'El motivo de consulta debe tener al menos 3 caracteres.',
        ];
    }

    public function mount(): void
    {
        $this->authorize('access-admission');

        if (request()->filled('dni')) {
            $this->dni = trim((string) request()->query('dni'));
            $this->lookupPatientByDni();
        }

        $this->recalculatePriority();
    }

    /**
     * Validación en tiempo real, precarga por DNI y recálculo reactivo MTS.
     */
    public function updated(string $property): void
    {
        if ($property === 'temperature' && is_string($this->temperature)) {
            $this->temperature = str_replace(',', '.', trim($this->temperature));
        }

        if ($property === 'dni') {
            $this->lookupPatientByDni();
        }

        if (array_key_exists($property, $this->rules())) {
            $this->validateOnly($property);
        }

        if (
            in_array($property, ['temperature', 'systolic_bp', 'diastolic_bp', 'heart_rate', 'respiratory_rate', 'spo2'], true)
            || str_starts_with($property, 'discriminators')
        ) {
            $this->recalculatePriority();
        }
    }

    /**
     * Busca un paciente existente por DNI (incluyendo soft-deleted) para precargar sus datos
     * editables y verificar si posee un triage activo en cola o consultorio.
     */
    public function lookupPatientByDni(): void
    {
        $this->dni = trim($this->dni);

        if ($this->dni === '' || ! preg_match('/^\d+$/', $this->dni)) {
            $this->resetRecurringState();

            return;
        }

        $patient = Patient::withTrashed()
            ->with('neighborhood')
            ->where('dni', $this->dni)
            ->first();

        if ($patient === null) {
            $this->resetRecurringState();

            return;
        }

        $this->existingPatientId = $patient->id;
        $this->isRecurringPatient = true;
        $this->first_name = $patient->first_name;
        $this->last_name = $patient->last_name;
        $this->birth_date = $patient->birth_date ? $patient->birth_date->format('Y-m-d') : '';
        $normalizedGender = strtolower(trim((string) $patient->gender));
        $this->gender = array_key_exists($normalizedGender, self::GENDERS) ? $normalizedGender : '';
        $this->phone = (string) ($patient->phone ?? '');
        $this->address = $patient->address;
        $this->neighborhood_id = $patient->neighborhood_id;
        $this->selectedNeighborhoodName = $patient->neighborhood?->name ?? '';
        $this->neighborhoodSearch = $this->selectedNeighborhoodName;

        $this->resetValidation([
            'dni',
            'first_name',
            'last_name',
            'birth_date',
            'gender',
            'phone',
            'address',
            'neighborhood_id',
        ]);

        $activeTriage = $this->findActiveTriageForPatient($patient->id);

        if ($activeTriage !== null) {
            $this->activeTriageId = $activeTriage->id;
            $this->activeTriageTicket = PrintTicket::ticketNumber($activeTriage->id);
        } else {
            $this->activeTriageId = null;
            $this->activeTriageTicket = null;
        }
    }

    public function recalculatePriority(): void
    {
        /** @var ManchesterTriageService $service */
        $service = app(ManchesterTriageService::class);

        $normalizedTemperature = is_string($this->temperature)
            ? str_replace(',', '.', trim($this->temperature))
            : $this->temperature;

        $vitalSigns = [
            'temperature' => $normalizedTemperature,
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
        $this->authorize('access-admission');

        if (is_string($this->temperature)) {
            $this->temperature = str_replace(',', '.', trim($this->temperature));
        }

        $this->dni = trim($this->dni);

        if ($this->dni !== '' && preg_match('/^\d+$/', $this->dni)) {
            $existing = Patient::withTrashed()->where('dni', $this->dni)->first();
            if ($existing !== null) {
                $activeTriage = $this->findActiveTriageForPatient($existing->id);
                if ($activeTriage !== null) {
                    $this->existingPatientId = $existing->id;
                    $this->isRecurringPatient = true;
                    $this->activeTriageId = $activeTriage->id;
                    $this->activeTriageTicket = PrintTicket::ticketNumber($activeTriage->id);
                    $this->addError('dni', "El paciente ya posee un triage activo en curso (#{$this->activeTriageTicket}). No se puede registrar un nuevo ingreso duplicado.");

                    return;
                }
            }
        }

        $data = $this->validate();
        $data['phone'] = $data['phone'] ?: null;

        /** @var ManchesterTriageService $service */
        $service = app(ManchesterTriageService::class);

        $vitalSigns = $service->normalizeVitalSigns([
            'temperature' => $data['temperature'],
            'systolic_bp' => $data['systolic_bp'],
            'diastolic_bp' => $data['diastolic_bp'],
            'heart_rate' => $data['heart_rate'],
            'respiratory_rate' => $data['respiratory_rate'],
            'spo2' => $data['spo2'],
        ]);

        $evaluation = $service->evaluate($vitalSigns, $this->discriminators);
        /** @var TriagePriority $priority */
        $priority = $evaluation['priority'];
        $activeDiscriminatorCodes = array_keys(array_filter($this->discriminators));

        [$patient, $triage] = DB::transaction(function () use ($data, $vitalSigns, $evaluation, $priority, $activeDiscriminatorCodes): array {
            $patientAttributes = [
                'dni' => $data['dni'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'neighborhood_id' => (int) $data['neighborhood_id'],
            ];

            $patient = Patient::withTrashed()->where('dni', $data['dni'])->first();

            if ($patient !== null) {
                if ($patient->trashed()) {
                    $patient->restore();
                }
                $patient->update($patientAttributes);
            } else {
                $patient = Patient::create($patientAttributes);
            }

            $triage = Triage::create([
                'patient_id' => $patient->id,
                'user_id' => Auth::id(),
                'symptoms' => trim((string) $data['symptoms']),
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

            return [$patient, $triage];
        });

        $this->createdPatientId = $patient->id;
        $this->createdPatientName = $patient->full_name;
        $this->createdPatientDni = $patient->dni;
        $this->createdTriageId = $triage->id;
        $this->createdTicketNumber = PrintTicket::ticketNumber($triage->id);
        $this->createdPriorityLabel = $priority->label();
        $this->createdPriorityBadgeClasses = $priority->badgeClasses();
        $this->createdTargetWaitMinutes = $evaluation['target_wait_minutes'];

        session()->flash('status', "Paciente y triage registrados: {$patient->full_name} (DNI {$patient->dni}) · Ticket #{$this->createdTicketNumber}.");

        $this->clear();

        $this->dispatch('triage-saved', triageId: $triage->id);
    }

    /**
     * Vuelve a abrir el modal del ticket digital del último triage registrado.
     */
    public function showTicket(): void
    {
        if ($this->createdTriageId !== null) {
            $this->dispatch('triage-saved', triageId: $this->createdTriageId);
        }
    }

    /**
     * Refleja en la confirmación la prioridad reclasificada si se ejecuta un Clinical Override.
     */
    #[On('triage-overridden')]
    public function onTriageOverridden(int $triageId): void
    {
        if ($triageId !== $this->createdTriageId) {
            return;
        }

        $triage = Triage::find($triageId);
        if ($triage === null) {
            return;
        }

        $this->createdPriorityLabel = $triage->final_priority->label();
        $this->createdPriorityBadgeClasses = $triage->final_priority->badgeClasses();
        $this->createdTargetWaitMinutes = $triage->target_wait_minutes;
    }

    /**
     * Selección reactiva de barrio desde el autocompletado.
     */
    public function selectNeighborhood(int $id, string $name): void
    {
        $this->neighborhood_id = $id;
        $this->selectedNeighborhoodName = $name;
        $this->neighborhoodSearch = $name;
        $this->resetValidation('neighborhood_id');
    }

    /**
     * Restablece la selección del barrio para permitir una nueva búsqueda.
     */
    public function clearNeighborhood(): void
    {
        $this->neighborhood_id = '';
        $this->selectedNeighborhoodName = '';
        $this->neighborhoodSearch = '';
    }

    /**
     * Sincroniza el nombre del barrio si neighborhood_id cambia externamente.
     */
    public function updatedNeighborhoodId($value): void
    {
        if ($value) {
            $barrio = Neighborhood::find($value);
            if ($barrio) {
                $this->selectedNeighborhoodName = $barrio->name;
                $this->neighborhoodSearch = $barrio->name;
            }
        } else {
            $this->selectedNeighborhoodName = '';
            $this->neighborhoodSearch = '';
        }
    }

    /**
     * Vacía el formulario y sus errores manteniendo la confirmación del último alta.
     */
    public function clear(): void
    {
        $this->reset([
            'dni',
            'first_name',
            'last_name',
            'birth_date',
            'gender',
            'phone',
            'address',
            'neighborhood_id',
            'neighborhoodSearch',
            'selectedNeighborhoodName',
            'existingPatientId',
            'isRecurringPatient',
            'activeTriageId',
            'activeTriageTicket',
            'temperature',
            'systolic_bp',
            'diastolic_bp',
            'heart_rate',
            'respiratory_rate',
            'spo2',
            'symptoms',
            'discriminators',
        ]);
        $this->resetValidation();
        $this->recalculatePriority();
    }

    /**
     * Oculta el aviso del último alta para registrar otro paciente.
     */
    public function newPatient(): void
    {
        $this->reset([
            'createdPatientId',
            'createdPatientName',
            'createdPatientDni',
            'createdTriageId',
            'createdTicketNumber',
            'createdPriorityLabel',
            'createdPriorityBadgeClasses',
            'createdTargetWaitMinutes',
        ]);
    }

    private function resetRecurringState(): void
    {
        $this->existingPatientId = null;
        $this->isRecurringPatient = false;
        $this->activeTriageId = null;
        $this->activeTriageTicket = null;
    }

    private function findActiveTriageForPatient(int $patientId): ?Triage
    {
        return Triage::query()
            ->where('patient_id', $patientId)
            ->whereIn('status', self::ACTIVE_TRIAGE_STATUSES)
            ->latest('id')
            ->first(['id', 'status']);
    }

    public function render(): View
    {
        $query = Neighborhood::query()->orderBy('name');

        if (trim($this->neighborhoodSearch) !== '' && $this->neighborhoodSearch !== $this->selectedNeighborhoodName) {
            $query->search($this->neighborhoodSearch);
        }

        /** @var ManchesterTriageService $service */
        $service = app(ManchesterTriageService::class);

        return view('livewire.patients.create-patient', [
            'neighborhoods' => $query->get(['id', 'name']),
            'genders' => self::GENDERS,
            'catalogue' => $service->getDiscriminatorCatalogue(),
        ]);
    }
}
