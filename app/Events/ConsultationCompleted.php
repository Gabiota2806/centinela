<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Consultation;
use App\Models\Triage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de dominio emitido al cerrar una consulta médica en consultorio.
 * Desacopla el cierre asistencial de la cola de telemetría epidemiológica anonimizada.
 */
class ConsultationCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Consultation $consultation,
        public readonly Triage $triage,
    ) {}
}
