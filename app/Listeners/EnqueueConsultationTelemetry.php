<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ConsultationCompleted;
use App\Jobs\DispatchTelemetryJob;
use App\Services\Telemetry\AnonymizerService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Escucha el cierre de consulta médica, anonimiza el episodio y encola el evento Outbox.
 * Nunca propaga fallos de red hacia el flujo clínico del consultorio (Offline-First).
 */
class EnqueueConsultationTelemetry
{
    public function __construct(
        private readonly AnonymizerService $anonymizer,
    ) {}

    public function handle(ConsultationCompleted $event): void
    {
        $outboxEvent = $this->anonymizer->recordOutboxEvent(
            $event->consultation,
            $event->triage,
        );

        try {
            DispatchTelemetryJob::dispatch($outboxEvent->id)->afterCommit();
        } catch (Throwable $exception) {
            Log::warning('No se pudo encolar el despacho inmediato de telemetría; el evento permanece en Outbox.', [
                'event_uuid' => $outboxEvent->event_uuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
