<?php

declare(strict_types=1);

namespace App\Livewire\Doctor;

use App\Enums\ConsultationStatus;
use App\Enums\PresumptiveDiagnosis;
use App\Enums\TriageStatus;
use App\Events\ConsultationCompleted;
use App\Livewire\Triage\PrintTicket;
use App\Models\Triage;
use App\Services\ManchesterTriageService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * CEN-47: consultorio con la ficha clínica del paciente y el cierre de la
 * consulta por diagnóstico presuntivo estandarizado.
 * Referencia: docs/specs/consultas/spec.md RF-CONS-08, 09 y 10;
 * docs/specs/00_contracts.md §1.3 y §5.1.
 */
class ConsultationRoom extends Component
{
    #[Locked]
    public Triage $triage;

    public string $diagnosis = '';

    public string $outcome = 'completed';

    public ?string $clinicalNotes = '';

    public ?string $prescription = '';

    public ?string $error = null;

    /**
     * Aviso cuando el triage no está en consulta. Se muestra en la misma pantalla
     * (sin redirigir con flash): la cola abierta en otra pestaña refresca cada 5 s
     * y su escritura de sesión podía pisar el mensaje.
     */
    #[Locked]
    public ?string $unavailable = null;

    public function mount(Triage $triage): void
    {
        $this->authorize('access-doctor');
        $this->triage = $triage->load(['patient.neighborhood', 'user', 'consultation.doctor']);

        if ($triage->status !== TriageStatus::IN_CONSULTATION) {
            $this->unavailable = sprintf(
                'El ticket %s no está en consulta (%s).',
                PrintTicket::ticketNumber($triage->id),
                $triage->status->label(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'diagnosis' => ['required', Rule::enum(PresumptiveDiagnosis::class)],
            'outcome' => ['required', Rule::in([ConsultationStatus::COMPLETED->value, ConsultationStatus::REFERRED_SIPEC->value])],
            'clinicalNotes' => ['nullable', 'string', 'max:2000'],
            'prescription' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'diagnosis.required' => 'Seleccioná el diagnóstico presuntivo para cerrar la consulta.',
            'diagnosis.enum' => 'El diagnóstico no pertenece al catálogo estandarizado.',
            'outcome.*' => 'Elegí si el paciente recibe el alta o se deriva por SIPEC.',
            'clinicalNotes.max' => 'Las notas clínicas no pueden superar los 2000 caracteres.',
            'prescription.max' => 'La prescripción no puede superar los 2000 caracteres.',
        ];
    }

    /**
     * RF-CONS-10: persiste el cierre, completa el triage y despacha
     * ConsultationCompleted dentro de una única transacción.
     */
    public function save(): void
    {
        $this->authorize('access-doctor');

        if ($this->unavailable !== null) {
            return;
        }

        $this->error = null;
        $validated = $this->validate();

        try {
            DB::transaction(function () use ($validated): void {
                // Estado vigente en la BD: otra terminal pudo haber cerrado la consulta.
                $this->triage->refresh();

                $consultation = $this->triage->finishConsultation(
                    ConsultationStatus::from($validated['outcome']),
                    $validated['diagnosis'],
                    $this->clean($validated['clinicalNotes'] ?? null),
                    $this->clean($validated['prescription'] ?? null),
                );

                ConsultationCompleted::dispatch($consultation, $this->triage);
            });
        } catch (DomainException $e) {
            $this->triage->refresh();
            $this->error = $e->getMessage().' Volvé a la cola para ver su estado actual.';

            return;
        }

        $diagnosis = PresumptiveDiagnosis::from($validated['diagnosis']);
        session()->flash('queue-notice', sprintf(
            'Consulta de %s cerrada: %s (%s).',
            $this->triage->patient->full_name,
            $diagnosis->label(),
            ConsultationStatus::from($validated['outcome'])->label(),
        ));

        $this->redirectRoute('consultations.index');
    }

    /**
     * Discriminadores MTS registrados en el triage, con su etiqueta clínica.
     *
     * @return list<string>
     */
    public function discriminatorLabels(): array
    {
        $labels = collect(app(ManchesterTriageService::class)->getDiscriminatorCatalogue())
            ->flatMap(fn (array $group) => $group['items']);

        return collect($this->triage->vital_signs['discriminators'] ?? [])
            ->map(fn (string $code) => $labels[$code] ?? $code)
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.doctor.consultation-room', [
            'diagnoses' => PresumptiveDiagnosis::cases(),
        ]);
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }
}
