<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DispatchTelemetryJob;
use App\Models\OutboxTelemetryEvent;
use Illuminate\Console\Command;

/**
 * Reencola o despacha eventos Outbox pendientes tras cortes de conectividad con el Hub.
 */
class DispatchPendingTelemetryCommand extends Command
{
    protected $signature = 'telemetry:dispatch-pending
                            {--limit=100 : Cantidad máxima de eventos pendientes a procesar por corrida}
                            {--sync : Ejecutar el envío de forma síncrona sin pasar por la cola}';

    protected $description = 'Despacha eventos de telemetría anonimizada pendientes en el Outbox hacia el Hub FastAPI';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $sync = (bool) $this->option('sync');

        $events = OutboxTelemetryEvent::query()
            ->pending()
            ->limit($limit)
            ->get(['id', 'event_uuid']);

        if ($events->isEmpty()) {
            $this->info('No existen eventos de telemetría pendientes en el Outbox.');

            return self::SUCCESS;
        }

        foreach ($events as $event) {
            if ($sync) {
                (new DispatchTelemetryJob($event->id))->handle();
            } else {
                DispatchTelemetryJob::dispatch($event->id);
            }
        }

        $this->info("Se procesaron {$events->count()} evento(s) pendientes del Outbox.");

        return self::SUCCESS;
    }
}
