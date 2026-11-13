<?php

declare(strict_types=1);

namespace App\Livewire\Patients;

use App\Models\Neighborhood;
use App\Models\Patient;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * CEN-34: alta de pacientes en la mesa de entrada (docs/specs/pacientes/spec.md).
 */
class CreatePatient extends Component
{
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

    /** Paciente recién registrado, para ofrecer el inicio del triage. */
    public ?int $createdPatientId = null;

    public string $createdPatientName = '';

    public string $createdPatientDni = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'dni' => ['required', 'regex:/^\d+$/', 'max:20', Rule::unique('patients', 'dni')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(array_keys(self::GENDERS))],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'neighborhood_id' => ['required', 'integer', Rule::exists('neighborhoods', 'id')],
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
            'dni.unique' => 'Ya hay un paciente registrado con este DNI.',
            'birth_date.date' => 'Ingresá una fecha válida.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'gender.in' => 'Elegí una opción de la lista.',
            'neighborhood_id.integer' => 'Elegí un barrio de la lista.',
            'neighborhood_id.exists' => 'Elegí un barrio de la lista.',
        ];
    }

    public function mount(): void
    {
        $this->authorize('access-admission');
    }

    /**
     * Validación en tiempo real del campo que se acaba de modificar.
     */
    public function updated(string $property): void
    {
        if (array_key_exists($property, $this->rules())) {
            $this->validateOnly($property);
        }
    }

    public function save(): void
    {
        $this->authorize('access-admission');

        $data = $this->validate();
        $data['phone'] = $data['phone'] ?: null;

        $patient = Patient::create($data);

        $this->createdPatientId = $patient->id;
        $this->createdPatientName = $patient->full_name;
        $this->createdPatientDni = $patient->dni;

        session()->flash('status', "Paciente registrado: {$patient->full_name} (DNI {$patient->dni}).");

        $this->clear();
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
     * Vacía el formulario y sus errores.
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
        ]);
        $this->resetValidation();
    }

    /**
     * Oculta el aviso del último alta para registrar otro paciente.
     */
    public function newPatient(): void
    {
        $this->reset(['createdPatientId', 'createdPatientName', 'createdPatientDni']);
    }

    public function render(): View
    {
        $query = Neighborhood::query()->orderBy('name');

        if (trim($this->neighborhoodSearch) !== '' && $this->neighborhoodSearch !== $this->selectedNeighborhoodName) {
            $query->search($this->neighborhoodSearch);
        }

        return view('livewire.patients.create-patient', [
            'neighborhoods' => $query->get(['id', 'name']),
            'genders' => self::GENDERS,
        ]);
    }
}
