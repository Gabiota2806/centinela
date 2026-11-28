<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use App\Enums\OutboxTelemetryStatus;
use App\Models\Consultation;
use App\Models\OutboxTelemetryEvent;
use App\Models\Patient;
use App\Models\Triage;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Servicio de disociación irreversible y anonimización clínica (Ley Nacional N° 25.326).
 *
 * Construye exclusivamente la lista blanca de campos del contrato §4.1 de
 * docs/specs/00_contracts.md, excluyendo de forma irreversible todo dato
 * identificatorio (DNI, nombre, apellido, teléfono, domicilio y fecha de nacimiento).
 */
class AnonymizerService
{
    /**
     * Catálogo canónico de diagnósticos presuntivos (docs/specs/00_contracts.md §5.1).
     *
     * @var list<string>
     */
    public const VALID_DIAGNOSES = [
        'sindrome_febril_inespecifico',
        'sospecha_dengue',
        'ira',
        'gastroenteritis',
        'traumatismo_herida',
        'hipertension_cardiovascular',
        'otro_clinico',
    ];

    private const CLINICAL_TEMP_MIN_C = 34.0;

    private const CLINICAL_TEMP_MAX_C = 43.0;

    /**
     * Construye el payload estrictamente disociado listo para transmisión al Hub FastAPI.
     *
     * @return array{
     *     event_uuid: string,
     *     caps_code: string,
     *     neighborhood_id: int,
     *     neighborhood_name: string,
     *     age_group: string,
     *     triage_priority: string,
     *     presumptive_diagnosis: string,
     *     symptoms_summary: string,
     *     temperature_c: float|null,
     *     recorded_at: string
     * }
     */
    public function buildPayload(Consultation $consultation, Triage $triage, ?string $eventUuid = null): array
    {
        if ($triage->exists) {
            $triage->loadMissing('patient.neighborhood');
        }

        /** @var Patient|null $patient */
        $patient = $triage->patient;
        $neighborhood = $patient?->neighborhood;

        $timezone = (string) config('centinela.timezone', 'America/Argentina/Cordoba');
        $recordedMoment = ($consultation->completed_at ?? now())->copy()->setTimezone($timezone);

        $priority = $triage->final_priority?->value
            ?? $triage->algorithmic_priority?->value
            ?? 'green';

        $vitalSigns = is_array($triage->vital_signs) ? $triage->vital_signs : [];

        return [
            'event_uuid' => $eventUuid ?? (string) Str::uuid(),
            'caps_code' => (string) config('centinela.caps_code', 'CAPS-CODDA-01'),
            'neighborhood_id' => (int) ($neighborhood?->id ?? $patient?->neighborhood_id ?? 0),
            'neighborhood_name' => (string) ($neighborhood?->name ?? 'Sin barrio asignado'),
            'age_group' => $this->resolveAgeGroup($patient?->birth_date, $recordedMoment),
            'triage_priority' => $priority,
            'presumptive_diagnosis' => $this->normalizeDiagnosis($consultation->diagnosis),
            'symptoms_summary' => $this->sanitizeSymptomsSummary($triage, $patient),
            'temperature_c' => $this->normalizeTemperature($vitalSigns['temperature'] ?? null),
            'recorded_at' => $recordedMoment->toIso8601String(),
        ];
    }

    /**
     * Registra de forma idempotente el evento anonimizado en la tabla Outbox en estado pending.
     */
    public function recordOutboxEvent(Consultation $consultation, Triage $triage): OutboxTelemetryEvent
    {
        if ($consultation->exists) {
            $existing = OutboxTelemetryEvent::query()
                ->where('consultation_id', $consultation->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $eventUuid = (string) Str::uuid();
        $payload = $this->buildPayload($consultation, $triage, $eventUuid);

        return OutboxTelemetryEvent::query()->create([
            'event_uuid' => $eventUuid,
            'consultation_id' => $consultation->exists ? $consultation->id : null,
            'payload' => $payload,
            'status' => OutboxTelemetryStatus::PENDING,
            'attempts' => 0,
        ]);
    }

    /**
     * Clasifica la fecha de nacimiento en uno de los 4 grupos etarios canónicos (§5.2).
     */
    public function resolveAgeGroup(?CarbonInterface $birthDate, ?CarbonInterface $referenceDate = null): string
    {
        if ($birthDate === null) {
            return 'adulto';
        }

        $reference = $referenceDate ?? now();

        if ($birthDate->isAfter($reference)) {
            return 'infante';
        }

        $age = (int) floor($birthDate->diffInYears($reference));

        return match (true) {
            $age < 12 => 'infante',
            $age < 18 => 'adolescente',
            $age < 60 => 'adulto',
            default => 'mayor',
        };
    }

    /**
     * Normaliza el diagnóstico presuntivo contra el catálogo canónico (§5.1).
     */
    public function normalizeDiagnosis(?string $diagnosis): string
    {
        $normalized = strtolower(trim((string) $diagnosis));

        if (in_array($normalized, self::VALID_DIAGNOSES, true)) {
            return $normalized;
        }

        return 'otro_clinico';
    }

    /**
     * Extrae y valida la temperatura corporal dentro del rango fisiológico [34.0, 43.0] °C.
     */
    public function normalizeTemperature(mixed $rawTemperature): ?float
    {
        if ($rawTemperature === null || $rawTemperature === '' || ! is_numeric($rawTemperature)) {
            return null;
        }

        $temp = round((float) $rawTemperature, 1);

        if ($temp < self::CLINICAL_TEMP_MIN_C || $temp > self::CLINICAL_TEMP_MAX_C) {
            return null;
        }

        return $temp;
    }

    /**
     * Genera un resumen clínico sintético libre de datos identificatorios personales.
     */
    private function sanitizeSymptomsSummary(Triage $triage, ?Patient $patient): string
    {
        $vitalSigns = is_array($triage->vital_signs) ? $triage->vital_signs : [];
        $discriminators = $vitalSigns['discriminators'] ?? null;

        if (is_array($discriminators) && $discriminators !== []) {
            $tokens = [];
            foreach ($discriminators as $item) {
                if (! is_string($item)) {
                    continue;
                }
                $slug = Str::slug($item, '_');
                if ($slug !== '') {
                    $tokens[] = $slug;
                }
            }

            if ($tokens !== []) {
                return Str::limit(implode('_', array_values(array_unique($tokens))), 120, '');
            }
        }

        $rawText = (string) ($triage->symptoms ?? '');
        $cleaned = $this->stripPatientIdentifiers($rawText, $patient);
        $slugged = Str::slug($cleaned, '_');

        if ($slugged === '') {
            return 'sin_sintomas_registrados';
        }

        return Str::limit($slugged, 120, '');
    }

    /**
     * Elimina cualquier ocurrencia accidental de datos identificatorios del paciente en texto libre.
     */
    private function stripPatientIdentifiers(string $text, ?Patient $patient): string
    {
        $sanitized = Str::ascii(mb_strtolower($text));

        // Quitar secuencias numéricas largas (potenciales DNI, teléfonos o alturas de calle)
        $sanitized = (string) preg_replace('/\b\d{5,}\b/', ' ', $sanitized);

        if ($patient !== null) {
            $piiFragments = array_filter([
                $patient->dni,
                $patient->first_name,
                $patient->last_name,
                $patient->phone,
                $patient->address,
            ], fn (?string $value): bool => $value !== null && trim($value) !== '');

            foreach ($piiFragments as $fragment) {
                $normalizedFragment = Str::ascii(mb_strtolower($fragment));
                $words = preg_split('/[\s\W]+/', $normalizedFragment, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                foreach ($words as $word) {
                    if (strlen($word) < 3) {
                        continue;
                    }
                    $sanitized = (string) preg_replace('/\b'.preg_quote($word, '/').'\b/', ' ', $sanitized);
                }
            }
        }

        // Quitar prefijos administrativos comunes junto a identificadores
        $sanitized = (string) preg_replace('/\b(dni|documento|tel|telefono|paciente)\b/', ' ', $sanitized);

        return trim((string) preg_replace('/\s+/', ' ', $sanitized));
    }
}
