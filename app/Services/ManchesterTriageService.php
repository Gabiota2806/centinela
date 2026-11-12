<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TriagePriority;

/**
 * Motor determinístico del Sistema Manchester de Triage (MTS).
 * Evalúa signos vitales y discriminadores clínicos para asignar la prioridad
 * clínica y el tiempo máximo objetivo de espera en CAPS de Formosa Capital.
 *
 * Referencias:
 * - docs/specs/triage/spec.md
 * - docs/specs/global_spec.md §2.B
 * - docs/backlog.md §CEN-35 / CEN-38
 */
class ManchesterTriageService
{
    /**
     * Evalúa los signos vitales y discriminadores clínicos para calcular la prioridad Manchester.
     *
     * @param  array<string, mixed>  $vitalSigns  Signos vitales: temperature, systolic_bp, diastolic_bp, heart_rate, respiratory_rate, spo2.
     * @param  array<int|string, string|bool>  $discriminators  Lista de discriminadores presentes o mapa [codigo => bool].
     * @return array{
     *     priority: TriagePriority,
     *     target_wait_minutes: int,
     *     discriminator: string
     * }
     */
    public function evaluate(array $vitalSigns = [], array $discriminators = []): array
    {
        $activeDiscriminators = $this->extractActiveDiscriminators($discriminators);
        $normalizedVitals = $this->normalizeVitalSigns($vitalSigns);

        // 1. Evaluación de Nivel 1: ROJO (Emergencia / Reanimación, 0 min)
        $redMatch = $this->evaluateRedCriteria($normalizedVitals, $activeDiscriminators);
        if ($redMatch !== null) {
            return [
                'priority' => TriagePriority::RED,
                'target_wait_minutes' => TriagePriority::RED->targetWaitMinutes(),
                'discriminator' => $redMatch,
            ];
        }

        // 2. Evaluación de Nivel 2: NARANJA (Muy Urgente, 10 min)
        $orangeMatch = $this->evaluateOrangeCriteria($normalizedVitals, $activeDiscriminators);
        if ($orangeMatch !== null) {
            return [
                'priority' => TriagePriority::ORANGE,
                'target_wait_minutes' => TriagePriority::ORANGE->targetWaitMinutes(),
                'discriminator' => $orangeMatch,
            ];
        }

        // 3. Evaluación de Nivel 3: AMARILLO (Urgente, 60 min)
        $yellowMatch = $this->evaluateYellowCriteria($normalizedVitals, $activeDiscriminators);
        if ($yellowMatch !== null) {
            return [
                'priority' => TriagePriority::YELLOW,
                'target_wait_minutes' => TriagePriority::YELLOW->targetWaitMinutes(),
                'discriminator' => $yellowMatch,
            ];
        }

        // 4. Evaluación de Nivel 4: VERDE (Poco Urgente, 120 min)
        $greenMatch = $this->evaluateGreenCriteria($normalizedVitals, $activeDiscriminators);
        if ($greenMatch !== null) {
            return [
                'priority' => TriagePriority::GREEN,
                'target_wait_minutes' => TriagePriority::GREEN->targetWaitMinutes(),
                'discriminator' => $greenMatch,
            ];
        }

        // 5. Nivel 5: AZUL (No Urgente, 240 min)
        $blueMatch = $this->evaluateBlueCriteria($activeDiscriminators);

        return [
            'priority' => TriagePriority::BLUE,
            'target_wait_minutes' => TriagePriority::BLUE->targetWaitMinutes(),
            'discriminator' => $blueMatch ?? 'Consulta no urgente / chequeo general',
        ];
    }

    /**
     * @param  array<string, float|int|null>  $vitals
     * @param  array<string, bool>  $discriminators
     */
    private function evaluateRedCriteria(array $vitals, array $discriminators): ?string
    {
        // Vía aérea comprometida u obstruida
        if ($this->hasAny($discriminators, ['airway_obstruction', 'airway_compromised'])) {
            return 'Vía aérea obstruida o compromiso inminente';
        }

        // Apnea o paro respiratorio
        if ($this->hasAny($discriminators, ['apnea', 'respiratory_arrest'])
            || ($vitals['respiratory_rate'] !== null && $vitals['respiratory_rate'] <= 5)) {
            return 'Apnea o paro respiratorio inminente';
        }

        // Pulso no detectable o paro cardíaco
        if ($this->hasAny($discriminators, ['undetectable_pulse', 'cardiac_arrest'])) {
            return 'Pulso no detectable o paro cardiorrespiratorio';
        }

        // Saturación de oxígeno crítica (SpO2 < 85%)
        if ($vitals['spo2'] !== null && $vitals['spo2'] < 85) {
            return 'Saturación de oxígeno crítica (SpO2 < 85%)';
        }

        // Shock descompensado o colapso hemodinámico grave (PAS < 70 mmHg)
        if ($this->hasAny($discriminators, ['severe_shock'])
            || ($vitals['systolic_bp'] !== null && $vitals['systolic_bp'] < 70)) {
            return 'Shock descompensado o colapso hemodinámico grave (PAS < 70 mmHg)';
        }

        // Convulsión activa
        if ($this->hasAny($discriminators, ['convulsion_active'])) {
            return 'Convulsión activa en curso';
        }

        return null;
    }

    /**
     * @param  array<string, float|int|null>  $vitals
     * @param  array<string, bool>  $discriminators
     */
    private function evaluateOrangeCriteria(array $vitals, array $discriminators): ?string
    {
        // Dolor severo (EVA 8-10)
        if ($this->hasAny($discriminators, ['severe_pain'])) {
            return 'Dolor severo (EVA 8-10)';
        }

        // Disnea aguda o dificultad respiratoria severa
        if ($this->hasAny($discriminators, ['acute_dyspnea'])
            || ($vitals['respiratory_rate'] !== null && ($vitals['respiratory_rate'] > 35 || $vitals['respiratory_rate'] < 10))) {
            return 'Disnea aguda o alteración respiratoria severa';
        }

        // Letargo o alteración aguda de conciencia
        if ($this->hasAny($discriminators, ['lethargy', 'altered_consciousness'])) {
            return 'Letargo o alteración aguda del estado de conciencia';
        }

        // Hipoxemia moderada (SpO2 85-92%)
        if ($vitals['spo2'] !== null && $vitals['spo2'] >= 85 && $vitals['spo2'] <= 92) {
            return 'Hipoxemia moderada (SpO2 85% - 92%)';
        }

        // Shock o inestabilidad hemodinámica (PAS < 90 mmHg con taquicardia > 120 lpm o bradicardia < 40 lpm)
        if ($this->hasAny($discriminators, ['shock'])
            || ($vitals['systolic_bp'] !== null && $vitals['systolic_bp'] < 90 && $vitals['heart_rate'] !== null && ($vitals['heart_rate'] > 120 || $vitals['heart_rate'] < 40))) {
            return 'Signos de shock o inestabilidad hemodinámica';
        }

        // Hiperpirexia extrema (≥ 40.5 °C)
        if ($vitals['temperature'] !== null && $vitals['temperature'] >= 40.5) {
            return 'Hiperpirexia extrema (≥ 40.5 °C)';
        }

        return null;
    }

    /**
     * @param  array<string, float|int|null>  $vitals
     * @param  array<string, bool>  $discriminators
     */
    private function evaluateYellowCriteria(array $vitals, array $discriminators): ?string
    {
        // Sospecha febril de dengue: Fiebre persistente > 39°C con cefalea, dolor retroocular o mialgias
        $hasDengueSymptoms = $this->hasAny($discriminators, ['dengue_suspicion', 'headache', 'myalgia', 'dengue_symptoms']);
        if ($hasDengueSymptoms && $vitals['temperature'] !== null && $vitals['temperature'] > 39.0) {
            return 'Fiebre persistente > 39°C con sospecha febril de dengue';
        }

        if ($this->hasAny($discriminators, ['dengue_suspicion'])) {
            return 'Sospecha febril de dengue';
        }

        // Desaturación leve (SpO2 93-94%)
        if ($vitals['spo2'] !== null && $vitals['spo2'] >= 93 && $vitals['spo2'] <= 94) {
            return 'Desaturación leve (SpO2 93% - 94%)';
        }

        // Dolor moderado (EVA 4-7)
        if ($this->hasAny($discriminators, ['moderate_pain'])) {
            return 'Dolor moderado (EVA 4-7)';
        }

        // Fiebre elevada aislada (≥ 38.5 °C)
        if ($vitals['temperature'] !== null && $vitals['temperature'] >= 38.5) {
            return 'Fiebre elevada (≥ 38.5 °C)';
        }

        // Hemorragia menor incontrolada
        if ($this->hasAny($discriminators, ['uncontrolled_minor_hemorrhage'])) {
            return 'Hemorragia menor incontrolada';
        }

        // Vómitos persistentes o deshidratación moderada
        if ($this->hasAny($discriminators, ['persistent_vomiting'])) {
            return 'Vómitos persistentes o deshidratación moderada';
        }

        return null;
    }

    /**
     * @param  array<string, float|int|null>  $vitals
     * @param  array<string, bool>  $discriminators
     */
    private function evaluateGreenCriteria(array $vitals, array $discriminators): ?string
    {
        // Fiebre < 38.5°C sin alteración hemodinámica (37.5°C a 38.4°C)
        if ($vitals['temperature'] !== null && $vitals['temperature'] >= 37.5 && $vitals['temperature'] < 38.5) {
            return 'Fiebre < 38.5°C sin alteración hemodinámica';
        }

        if ($this->hasAny($discriminators, ['mild_fever'])) {
            return 'Fiebre < 38.5°C sin alteración hemodinámica';
        }

        // Dolor leve reciente (EVA 1-3)
        if ($this->hasAny($discriminators, ['mild_pain'])) {
            return 'Dolor leve reciente (EVA 1-3)';
        }

        // Traumatismo menor reciente sin deformidad
        if ($this->hasAny($discriminators, ['mild_trauma'])) {
            return 'Traumatismo menor reciente sin deformidad';
        }

        // Vómitos leves sin deshidratación
        if ($this->hasAny($discriminators, ['mild_vomiting'])) {
            return 'Vómitos leves sin deshidratación';
        }

        // Infección local menor / erupción cutánea afebril
        if ($this->hasAny($discriminators, ['local_infection'])) {
            return 'Infección local menor / erupción cutánea afebril';
        }

        return null;
    }

    /**
     * @param  array<string, bool>  $discriminators
     */
    private function evaluateBlueCriteria(array $discriminators): ?string
    {
        if ($this->hasAny($discriminators, ['routine_check'])) {
            return 'Chequeo de rutina / control periódico';
        }

        if ($this->hasAny($discriminators, ['stitches_removal'])) {
            return 'Retiro de puntos de sutura';
        }

        if ($this->hasAny($discriminators, ['administrative'])) {
            return 'Trámite administrativo o recetas crónicas';
        }

        if ($this->hasAny($discriminators, ['chronic_stable'])) {
            return 'Problema crónico estable sin signos de alarma';
        }

        return null;
    }

    /**
     * @param  array<int|string, string|bool>  $discriminators
     * @return array<string, bool>
     */
    private function extractActiveDiscriminators(array $discriminators): array
    {
        $active = [];

        foreach ($discriminators as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $active[trim($value)] = true;
            } elseif (is_string($key) && (bool) $value) {
                $active[trim($key)] = true;
            }
        }

        return $active;
    }

    /**
     * @param  array<string, mixed>  $vitals
     * @return array<string, float|int|null>
     */
    public function normalizeVitalSigns(array $vitals): array
    {
        return [
            'temperature' => isset($vitals['temperature']) && is_numeric($vitals['temperature']) ? (float) $vitals['temperature'] : null,
            'systolic_bp' => isset($vitals['systolic_bp']) && is_numeric($vitals['systolic_bp']) ? (int) $vitals['systolic_bp'] : null,
            'diastolic_bp' => isset($vitals['diastolic_bp']) && is_numeric($vitals['diastolic_bp']) ? (int) $vitals['diastolic_bp'] : null,
            'heart_rate' => isset($vitals['heart_rate']) && is_numeric($vitals['heart_rate']) ? (int) $vitals['heart_rate'] : null,
            'respiratory_rate' => isset($vitals['respiratory_rate']) && is_numeric($vitals['respiratory_rate']) ? (int) $vitals['respiratory_rate'] : null,
            'spo2' => isset($vitals['spo2']) && is_numeric($vitals['spo2']) ? (int) $vitals['spo2'] : null,
        ];
    }

    /**
     * @param  array<string, bool>  $active
     * @param  array<int, string>  $keys
     */
    private function hasAny(array $active, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! empty($active[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Catálogo estandarizado de discriminadores clínicos agrupados por nivel MTS.
     * Utilizado para alimentar la interfaz reactiva en Livewire.
     *
     * @return array<string, array{label: string, priority: TriagePriority, items: array<string, string>}>
     */
    public function getDiscriminatorCatalogue(): array
    {
        return [
            'red' => [
                'label' => 'Rojo (Emergencia / Reanimación - 0 min)',
                'priority' => TriagePriority::RED,
                'items' => [
                    'airway_obstruction' => 'Vía aérea obstruida / estridor severo',
                    'apnea' => 'Apnea o paro respiratorio',
                    'undetectable_pulse' => 'Pulso no detectable / paro cardiorrespiratorio',
                    'severe_shock' => 'Shock descompensado / colapso hemodinámico',
                    'convulsion_active' => 'Convulsión activa en curso',
                ],
            ],
            'orange' => [
                'label' => 'Naranja (Muy Urgente - 10 min)',
                'priority' => TriagePriority::ORANGE,
                'items' => [
                    'severe_pain' => 'Dolor severo (EVA 8-10)',
                    'acute_dyspnea' => 'Disnea aguda o dificultad respiratoria severa',
                    'lethargy' => 'Letargo o alteración aguda del estado de conciencia',
                    'shock' => 'Signos de shock o inestabilidad hemodinámica',
                ],
            ],
            'yellow' => [
                'label' => 'Amarillo (Urgente - 60 min)',
                'priority' => TriagePriority::YELLOW,
                'items' => [
                    'dengue_suspicion' => 'Sospecha febril de dengue (fiebre + cefalea/mialgias)',
                    'moderate_pain' => 'Dolor moderado (EVA 4-7)',
                    'persistent_vomiting' => 'Vómitos persistentes o deshidratación moderada',
                    'uncontrolled_minor_hemorrhage' => 'Hemorragia menor incontrolada',
                ],
            ],
            'green' => [
                'label' => 'Verde (Poco Urgente - 120 min)',
                'priority' => TriagePriority::GREEN,
                'items' => [
                    'mild_pain' => 'Dolor leve reciente (EVA 1-3)',
                    'mild_trauma' => 'Traumatismo menor reciente sin deformidad',
                    'mild_vomiting' => 'Vómitos leves sin deshidratación',
                    'local_infection' => 'Infección local menor / erupción cutánea afebril',
                ],
            ],
            'blue' => [
                'label' => 'Azul (No Urgente - 240 min)',
                'priority' => TriagePriority::BLUE,
                'items' => [
                    'routine_check' => 'Chequeo de rutina / control periódico',
                    'stitches_removal' => 'Retiro de puntos de sutura',
                    'administrative' => 'Trámite administrativo / receta médica',
                    'chronic_stable' => 'Problema crónico estable sin signos de alarma',
                ],
            ],
        ];
    }
}
