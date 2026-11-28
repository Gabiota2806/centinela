<?php

declare(strict_types=1);

namespace App\Livewire\Doctor;

use App\Enums\TriageStatus;
use App\Livewire\Triage\PrintTicket;
use App\Models\Triage;
use Carbon\CarbonInterface;
use Closure;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * CEN-42: panel médico de la cola de espera con ordenamiento dinámico por MTS.
 *
 * Muestra a los pacientes llamados, en consulta y en espera (Rojo ➔ Azul, FIFO),
 * y delega las transiciones de estado en el modelo Triage (CEN-44).
 * Referencia: docs/specs/consultas/spec.md RF-CONS-01, 02, 04 y 06.
 */
class MedicalQueue extends Component
{
    /** Mensaje de la última acción ('success' o 'error'). */
    public ?string $notice = null;

    public string $noticeType = 'success';

    public function mount(): void
    {
        $this->authorize('access-doctor');

        // Aviso que deja el consultorio al cerrar una consulta (CEN-47).
        if (session()->has('queue-notice')) {
            $this->flash((string) session('queue-notice'));
        }
    }

    /**
     * Hora local del CAPS; agrega el día si no es de hoy (24/11 19:07).
     */
    public static function localTime(?CarbonInterface $moment): string
    {
        if ($moment === null) {
            return '—';
        }

        $tz = config('centinela.timezone');
        $local = $moment->copy()->timezone($tz);

        return $local->isSameDay(now()->timezone($tz)) ? $local->format('H:i') : $local->format('d/m H:i');
    }

    /**
     * Cola activa completa en orden canónico MTS + FIFO (CEN-45).
     *
     * @return Collection<int, Triage>
     */
    #[Computed]
    public function activeTriages(): Collection
    {
        return Triage::query()->activeQueue()->get();
    }

    /**
     * @return Collection<int, Triage>
     */
    #[Computed]
    public function waiting(): Collection
    {
        return $this->byStatus(TriageStatus::PENDING_CONSULTATION);
    }

    /**
     * @return Collection<int, Triage>
     */
    #[Computed]
    public function called(): Collection
    {
        return $this->byStatus(TriageStatus::CALLED);
    }

    /**
     * @return Collection<int, Triage>
     */
    #[Computed]
    public function inConsultation(): Collection
    {
        return $this->byStatus(TriageStatus::IN_CONSULTATION);
    }

    /**
     * CEN-43: refresca la cola ante triages nuevos, editados o reclasificados.
     * Entre terminales de la LAN la sincronización la resuelve wire:poll.5s.
     */
    #[On('patient-triaged')]
    #[On('triage-updated')]
    #[On('triage-overridden')]
    public function refreshQueue(): void
    {
        unset($this->activeTriages, $this->waiting, $this->called, $this->inConsultation);
    }

    public function callPatient(int $triageId): void
    {
        $this->act($triageId, fn (Triage $t) => $t->callForConsultation(), 'Se llamó a :name a consultorio.');
    }

    public function returnToQueue(int $triageId): void
    {
        $this->act($triageId, fn (Triage $t) => $t->returnToQueue(), ':name volvió a la espera en su posición original.');
    }

    public function markAbsent(int $triageId): void
    {
        $this->act($triageId, fn (Triage $t) => $t->markAsAbsent(), ':name se marcó como ausente y salió de la cola.');
    }

    public function startConsultation(int $triageId): void
    {
        $triage = $this->act(
            $triageId,
            fn (Triage $t) => $t->startConsultation(Auth::user()),
            ':name ingresó a consulta.',
        );

        // El consultorio (/consultas/{triage}) se habilita con el cierre diagnóstico.
        if ($triage !== null && Route::has('consultations.show')) {
            $this->redirectRoute('consultations.show', $triage, navigate: false);
        }
    }

    /**
     * RF-CONS-04: abre el Clinical Override del triage desde su tarjeta.
     */
    public function reclassify(int $triageId): void
    {
        $this->authorize('access-doctor');

        $this->dispatch('open-clinical-override', triageId: $triageId);
    }

    /**
     * Duración legible para el cronómetro de espera (45 min, 2 h 5 min, 3 d 4 h).
     * Debe coincidir con fmt() del cronómetro Alpine de la vista.
     */
    public static function formatMinutes(int $minutes): string
    {
        return match (true) {
            $minutes < 60 => "{$minutes} min",
            $minutes < 1440 => intdiv($minutes, 60).' h '.($minutes % 60).' min',
            default => intdiv($minutes, 1440).' d '.intdiv($minutes % 1440, 60).' h',
        };
    }

    public static function ticketNumber(Triage $triage): string
    {
        return PrintTicket::ticketNumber($triage->id);
    }

    public function render(): View
    {
        return view('livewire.doctor.medical-queue');
    }

    /**
     * @param  Closure(Triage): mixed  $transition
     */
    private function act(int $triageId, Closure $transition, string $message): ?Triage
    {
        // Cada acción se autoriza de nuevo: el permiso de mount() no cubre las llamadas posteriores.
        $this->authorize('access-doctor');

        $triage = Triage::query()->with('patient')->find($triageId);

        if ($triage === null) {
            $this->flash('El paciente ya no está en la cola. Se actualizó el listado.', 'error');

            return null;
        }

        try {
            $transition($triage);
        } catch (DomainException $e) {
            $this->flash($e->getMessage(), 'error');

            return null;
        } finally {
            $this->refreshQueue();
        }

        $this->flash(str_replace(':name', $triage->patient->full_name, $message));

        return $triage;
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $this->notice = $message;
        $this->noticeType = $type;
    }

    /**
     * @return Collection<int, Triage>
     */
    private function byStatus(TriageStatus $status): Collection
    {
        return $this->activeTriages->filter(fn (Triage $t) => $t->status === $status)->values();
    }
}
