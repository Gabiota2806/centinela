<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Enums\ConsultationStatus;
use App\Enums\OutboxTelemetryStatus;
use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Events\ConsultationCompleted;
use App\Jobs\DispatchTelemetryJob;
use App\Models\Consultation;
use App\Models\Neighborhood;
use App\Models\OutboxTelemetryEvent;
use App\Models\Patient;
use App\Models\Triage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Pruebas de integración del Patrón Outbox Offline-First y despacho asíncrono al Hub FastAPI.
 * Referencia: docs/specs/geo/spec.md RF-GEO-04, RF-GEO-05 y Gherkin §3.
 */
class TelemetryOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('centinela.caps_code', 'CAPS-CODDA-01');
        config()->set('centinela.timezone', 'America/Argentina/Cordoba');
        config()->set('centinela.hub.url', 'http://hub_analytics:8001');
        config()->set('centinela.hub.api_key', 'test-caps-secret-key');
        config()->set('centinela.hub.timeout_seconds', 5);
    }

    /**
     * @return array{0: Consultation, 1: Triage, 2: Patient}
     */
    private function createCompletedClinicalCase(): array
    {
        $neighborhood = Neighborhood::factory()->create(['name' => 'San Miguel']);

        $patient = Patient::factory()->create([
            'dni' => '38123456',
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'birth_date' => now()->subYears(32)->format('Y-m-d'),
            'phone' => '+54 370 4998877',
            'address' => 'Calle Rivadavia 900',
            'neighborhood_id' => $neighborhood->id,
        ]);

        $triage = Triage::factory()->create([
            'patient_id' => $patient->id,
            'symptoms' => 'Fiebre alta, mialgias y cefalea retroocular',
            'vital_signs' => [
                'temperature' => 39.2,
                'discriminators' => ['fiebre', 'mialgias', 'cefalea'],
            ],
            'algorithmic_priority' => TriagePriority::YELLOW,
            'final_priority' => TriagePriority::YELLOW,
            'status' => TriageStatus::COMPLETED,
        ]);

        $consultation = Consultation::factory()->create([
            'triage_id' => $triage->id,
            'diagnosis' => 'sospecha_dengue',
            'clinical_notes' => 'Seguimiento clínico en 24hs',
            'status' => ConsultationStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        return [$consultation, $triage, $patient];
    }

    public function test_evento_consultation_completed_crea_registro_outbox_sin_pii_y_encola_job(): void
    {
        Queue::fake();

        [$consultation, $triage] = $this->createCompletedClinicalCase();

        ConsultationCompleted::dispatch($consultation, $triage);

        $this->assertDatabaseCount('outbox_telemetry_events', 1);

        /** @var OutboxTelemetryEvent $event */
        $event = OutboxTelemetryEvent::query()->firstOrFail();

        $this->assertSame($consultation->id, $event->consultation_id);
        $this->assertSame(OutboxTelemetryStatus::PENDING, $event->status);
        $this->assertSame(0, $event->attempts);
        $this->assertNull($event->sent_at);

        $serialized = json_encode($event->payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('38123456', $serialized);
        $this->assertStringNotContainsString('Juan', $serialized);
        $this->assertStringNotContainsString('Pérez', $serialized);
        $this->assertStringNotContainsString('4998877', $serialized);
        $this->assertStringNotContainsString('Rivadavia', $serialized);
        $this->assertSame('sospecha_dengue', $event->payload['presumptive_diagnosis']);
        $this->assertSame('yellow', $event->payload['triage_priority']);
        $this->assertSame('adulto', $event->payload['age_group']);

        Queue::assertPushed(
            DispatchTelemetryJob::class,
            fn (DispatchTelemetryJob $job): bool => $job->outboxEventId === $event->id
        );
    }

    public function test_resiliencia_offline_conserva_evento_pending_y_envia_al_recuperarse_el_hub(): void
    {
        [$consultation, $triage] = $this->createCompletedClinicalCase();

        // 1) Primer intento: Hub caído (ConnectionException).
        // 2) Segundo intento: Hub recuperado (HTTP 202 Accepted).
        $callCount = 0;

        Http::fake([
            'http://hub_analytics:8001/api/v1/telemetry' => function (Request $request) use (&$callCount) {
                $callCount++;

                if ($callCount === 1) {
                    throw new ConnectionException('Connection refused');
                }

                return Http::response([
                    'status' => 'accepted',
                    'event_uuid' => $request['event_uuid'],
                ], 202);
            },
        ]);

        ConsultationCompleted::dispatch($consultation, $triage);

        /** @var OutboxTelemetryEvent $event */
        $event = OutboxTelemetryEvent::query()->firstOrFail();

        $this->assertSame(OutboxTelemetryStatus::PENDING, $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertNotNull($event->last_error);
        $this->assertStringContainsString('Connection refused', $event->last_error);
        $this->assertNull($event->sent_at);

        // Hub vuelve en línea: el Job reenvía con X-CAPS-Key y marca sent
        (new DispatchTelemetryJob($event->id))->handle();

        $event->refresh();

        $this->assertSame(OutboxTelemetryStatus::SENT, $event->status);
        $this->assertSame(2, $event->attempts);
        $this->assertNull($event->last_error);
        $this->assertNotNull($event->sent_at);

        Http::assertSent(function (Request $request) use ($event): bool {
            return $request->url() === 'http://hub_analytics:8001/api/v1/telemetry'
                && $request->method() === 'POST'
                && $request->hasHeader('X-CAPS-Key', 'test-caps-secret-key')
                && $request['event_uuid'] === $event->event_uuid
                && ! array_key_exists('dni', $request->data());
        });
    }

    public function test_error_http_5xx_conserva_evento_en_pending_para_reintento_exponencial(): void
    {
        Queue::fake();

        [$consultation, $triage] = $this->createCompletedClinicalCase();
        ConsultationCompleted::dispatch($consultation, $triage);

        /** @var OutboxTelemetryEvent $event */
        $event = OutboxTelemetryEvent::query()->firstOrFail();

        Http::fake([
            'http://hub_analytics:8001/api/v1/telemetry' => Http::response(['detail' => 'Service Unavailable'], 503),
        ]);

        (new DispatchTelemetryJob($event->id))->handle();

        $event->refresh();

        $this->assertSame(OutboxTelemetryStatus::PENDING, $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertStringContainsString('503', (string) $event->last_error);
        $this->assertNull($event->sent_at);
    }

    public function test_error_http_422_marca_evento_como_failed_sin_bucle_infinito(): void
    {
        Queue::fake();

        [$consultation, $triage] = $this->createCompletedClinicalCase();
        ConsultationCompleted::dispatch($consultation, $triage);

        /** @var OutboxTelemetryEvent $event */
        $event = OutboxTelemetryEvent::query()->firstOrFail();

        Http::fake([
            'http://hub_analytics:8001/api/v1/telemetry' => Http::response(['detail' => 'Extra inputs are not permitted'], 422),
        ]);

        (new DispatchTelemetryJob($event->id))->handle();

        $event->refresh();

        $this->assertSame(OutboxTelemetryStatus::FAILED, $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertStringContainsString('422', (string) $event->last_error);
    }

    public function test_comando_drenado_reenvia_eventos_pendientes_acumulados(): void
    {
        Queue::fake();

        [$consultation, $triage] = $this->createCompletedClinicalCase();
        ConsultationCompleted::dispatch($consultation, $triage);

        /** @var OutboxTelemetryEvent $event */
        $event = OutboxTelemetryEvent::query()->firstOrFail();

        Http::fake([
            'http://hub_analytics:8001/api/v1/telemetry' => Http::response([
                'status' => 'accepted',
                'event_uuid' => $event->event_uuid,
            ], 202),
        ]);

        $this->artisan('telemetry:dispatch-pending', ['--sync' => true])
            ->assertExitCode(0);

        $event->refresh();
        $this->assertSame(OutboxTelemetryStatus::SENT, $event->status);
    }

    public function test_no_reenvia_eventos_ya_marcados_como_sent(): void
    {
        Queue::fake();

        [$consultation, $triage] = $this->createCompletedClinicalCase();
        ConsultationCompleted::dispatch($consultation, $triage);

        /** @var OutboxTelemetryEvent $event */
        $event = OutboxTelemetryEvent::query()->firstOrFail();
        $event->update([
            'status' => OutboxTelemetryStatus::SENT,
            'attempts' => 1,
            'sent_at' => now(),
        ]);

        Http::fake();

        (new DispatchTelemetryJob($event->id))->handle();

        Http::assertNothingSent();
        $this->assertSame(1, $event->fresh()->attempts);
    }
}
