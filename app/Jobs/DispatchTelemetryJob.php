<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OutboxTelemetryStatus;
use App\Models\OutboxTelemetryEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envía de forma asíncrona un evento Outbox anonimizado hacia POST /api/v1/telemetry del Hub FastAPI.
 * Ante indisponibilidad de red o HTTP 5xx, conserva el evento en estado pending con backoff exponencial.
 */
class DispatchTelemetryJob implements ShouldQueue
{
    use Queueable;

    public const MAX_ATTEMPTS = 5;

    /**
     * Cantidad máxima de intentos gestionados por el worker de cola.
     */
    public int $tries = self::MAX_ATTEMPTS;

    public function __construct(
        public readonly int $outboxEventId,
    ) {}

    /**
     * Estrategia de retroceso exponencial en segundos (1m, 5m, 15m, 60m).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $event = OutboxTelemetryEvent::query()->find($this->outboxEventId);

        if ($event === null || $event->status === OutboxTelemetryStatus::SENT) {
            return;
        }

        $baseUrl = rtrim((string) config('centinela.hub.url', 'http://hub_analytics:8001'), '/');
        $apiKey = (string) config('centinela.hub.api_key', '');
        $timeout = (int) config('centinela.hub.timeout_seconds', 5);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-CAPS-Key' => $apiKey,
                ])
                ->post("{$baseUrl}/api/v1/telemetry", $event->payload);

            if (in_array($response->status(), [200, 201, 202], true)) {
                $event->update([
                    'status' => OutboxTelemetryStatus::SENT,
                    'attempts' => $event->attempts + 1,
                    'last_error' => null,
                    'sent_at' => now(),
                ]);

                return;
            }

            $status = $response->status();
            $detail = Str::limit($response->body(), 500);
            $errorMessage = "HTTP {$status}: {$detail}";

            // Errores transitorios del Hub (5xx, 408, 429) -> conservar pending y reintentar con backoff
            if ($status >= 500 || in_array($status, [408, 429], true)) {
                $this->recordTransientFailure($event, $errorMessage);

                return;
            }

            // Errores definitivos de contrato o credenciales (4xx como 401/422) -> marcar failed sin bucle infinito
            $event->update([
                'status' => OutboxTelemetryStatus::FAILED,
                'attempts' => $event->attempts + 1,
                'last_error' => $errorMessage,
            ]);

            Log::error('Telemetría rechazada por el Hub FastAPI con error no reintentable.', [
                'event_uuid' => $event->event_uuid,
                'http_status' => $status,
            ]);
        } catch (ConnectionException $exception) {
            $this->recordTransientFailure($event, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->recordTransientFailure($event, $exception->getMessage());
        }
    }

    private function recordTransientFailure(OutboxTelemetryEvent $event, string $errorMessage): void
    {
        $nextAttempt = $event->attempts + 1;
        $exhausted = $nextAttempt >= self::MAX_ATTEMPTS;

        $event->update([
            'status' => $exhausted ? OutboxTelemetryStatus::FAILED : OutboxTelemetryStatus::PENDING,
            'attempts' => $nextAttempt,
            'last_error' => Str::limit($errorMessage, 1000),
        ]);

        Log::warning('Entrega de telemetría diferida por indisponibilidad transitoria del Hub.', [
            'event_uuid' => $event->event_uuid,
            'attempts' => $nextAttempt,
            'status' => $event->status->value,
        ]);

        if (! $exhausted && $this->job !== null) {
            $delays = $this->backoff();
            $delay = $delays[min($nextAttempt - 1, count($delays) - 1)];
            $this->release($delay);
        }
    }
}
