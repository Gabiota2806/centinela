<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Exceptions\WeatherFetchException;
use App\Models\EpidemiologySetting;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

/**
 * Panel reactivo de configuración de parámetros epidemiológicos y monitoreo de serie climática (RF-GEO-03).
 */
class EpidemiologySettings extends Component
{
    public float|string $rain_threshold_mm = EpidemiologySetting::DEFAULT_RAIN_THRESHOLD_MM;

    public int|string $time_lag_days = EpidemiologySetting::DEFAULT_TIME_LAG_DAYS;

    public int|string $febrile_alert_threshold = EpidemiologySetting::DEFAULT_FEBRILE_ALERT_THRESHOLD;

    public float|string $high_risk_probability = EpidemiologySetting::DEFAULT_HIGH_RISK_PROBABILITY;

    public ?string $statusMessage = null;

    public string $statusType = 'success';

    public ?string $lastUpdatedAt = null;

    public ?string $lastUpdatedBy = null;

    public function mount(): void
    {
        $this->authorize('access-epidemiology');

        $this->loadSetting(EpidemiologySetting::current());
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'rain_threshold_mm' => ['required', 'numeric', 'min:1', 'max:500'],
            'time_lag_days' => ['required', 'integer', 'min:7', 'max:14'],
            'febrile_alert_threshold' => ['required', 'integer', 'min:1', 'max:500'],
            'high_risk_probability' => ['required', 'numeric', 'min:0.10', 'max:0.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'rain_threshold_mm.required' => 'Ingresá el umbral de precipitación acumulada.',
            'rain_threshold_mm.numeric' => 'El umbral de lluvia debe ser un valor numérico en milímetros.',
            'rain_threshold_mm.min' => 'El umbral de lluvia debe ser al menos 1 mm.',
            'rain_threshold_mm.max' => 'El umbral de lluvia no puede superar los 500 mm.',
            'time_lag_days.required' => 'Ingresá la ventana de desfase lluvia-casos.',
            'time_lag_days.integer' => 'La ventana de desfase debe ser un número entero de días.',
            'time_lag_days.min' => 'La ventana de desfase debe ser de al menos 7 días.',
            'time_lag_days.max' => 'La ventana de desfase no puede superar los 14 días.',
            'febrile_alert_threshold.required' => 'Ingresá el umbral de casos febriles.',
            'febrile_alert_threshold.integer' => 'El umbral de casos febriles debe ser un número entero.',
            'febrile_alert_threshold.min' => 'El umbral de casos febriles debe ser al menos 1.',
            'febrile_alert_threshold.max' => 'El umbral de casos febriles no puede superar los 500.',
            'high_risk_probability.required' => 'Ingresá la probabilidad mínima de alerta alta.',
            'high_risk_probability.numeric' => 'La probabilidad de alerta alta debe ser un valor decimal.',
            'high_risk_probability.min' => 'La probabilidad mínima de alerta alta debe ser al menos 0.10.',
            'high_risk_probability.max' => 'La probabilidad de alerta alta no puede superar 0.99.',
        ];
    }

    public function save(): void
    {
        $this->authorize('access-epidemiology');

        $validated = $this->validate();

        $setting = EpidemiologySetting::current();
        $setting->update([
            'rain_threshold_mm' => round((float) $validated['rain_threshold_mm'], 2),
            'time_lag_days' => (int) $validated['time_lag_days'],
            'febrile_alert_threshold' => (int) $validated['febrile_alert_threshold'],
            'high_risk_probability' => round((float) $validated['high_risk_probability'], 2),
            'updated_by' => Auth::id(),
        ]);

        $setting->load('updatedBy');
        $this->loadSetting($setting);

        $this->statusType = 'success';
        $this->statusMessage = 'Parámetros epidemiológicos actualizados correctamente.';
    }

    public function restoreDefaults(): void
    {
        $this->authorize('access-epidemiology');

        $this->rain_threshold_mm = EpidemiologySetting::DEFAULT_RAIN_THRESHOLD_MM;
        $this->time_lag_days = EpidemiologySetting::DEFAULT_TIME_LAG_DAYS;
        $this->febrile_alert_threshold = EpidemiologySetting::DEFAULT_FEBRILE_ALERT_THRESHOLD;
        $this->high_risk_probability = EpidemiologySetting::DEFAULT_HIGH_RISK_PROBABILITY;

        $this->resetValidation();
        $this->statusType = 'info';
        $this->statusMessage = 'Valores recomendados precargados en el formulario. Guardá los cambios para aplicarlos.';
    }

    public function syncWeatherNow(OpenMeteoService $weatherService): void
    {
        $this->authorize('access-epidemiology');

        try {
            $records = $weatherService->fetchAndStore(OpenMeteoService::CONTINGENCY_TTL_DAYS);

            unset($this->recentWeather, $this->sevenDayRainTotal, $this->rainThresholdExceeded);

            $this->statusType = 'success';
            $this->statusMessage = sprintf(
                'Serie meteorológica actualizada desde Open-Meteo (%d días sincronizados).',
                $records->count(),
            );
        } catch (WeatherFetchException|Throwable $exception) {
            Log::warning('Fallo en sincronización manual de Open-Meteo desde panel epidemiológico.', [
                'error' => $exception->getMessage(),
                'user_id' => Auth::id(),
            ]);

            unset($this->recentWeather, $this->sevenDayRainTotal, $this->rainThresholdExceeded);

            $this->statusType = 'warning';
            $this->statusMessage = 'Open-Meteo no responde en este momento. Se mantienen los registros climáticos locales de contingencia.';
        }
    }

    /**
     * @return array<int, array{recorded_date: string, temperature_max: float|null, temperature_min: float|null, precipitation_sum: float, relative_humidity_mean: float|null, source: string}>
     */
    #[Computed]
    public function recentWeather(): array
    {
        return app(OpenMeteoService::class)->getContingencyWeather(OpenMeteoService::CONTINGENCY_TTL_DAYS);
    }

    #[Computed]
    public function sevenDayRainTotal(): float
    {
        return round(
            array_reduce(
                $this->recentWeather,
                static fn (float $sum, array $day): float => $sum + (float) ($day['precipitation_sum'] ?? 0.0),
                0.0,
            ),
            2,
        );
    }

    #[Computed]
    public function rainThresholdExceeded(): bool
    {
        $threshold = is_numeric($this->rain_threshold_mm) ? (float) $this->rain_threshold_mm : EpidemiologySetting::DEFAULT_RAIN_THRESHOLD_MM;

        return $this->sevenDayRainTotal >= $threshold && $this->recentWeather !== [];
    }

    public function render(): View
    {
        return view('livewire.admin.epidemiology-settings');
    }

    private function loadSetting(EpidemiologySetting $setting): void
    {
        $this->rain_threshold_mm = $setting->rain_threshold_mm;
        $this->time_lag_days = $setting->time_lag_days;
        $this->febrile_alert_threshold = $setting->febrile_alert_threshold;
        $this->high_risk_probability = $setting->high_risk_probability;

        $timezone = (string) config('centinela.timezone', 'America/Argentina/Cordoba');
        $this->lastUpdatedAt = $setting->updated_at?->copy()->setTimezone($timezone)->format('d/m/Y H:i');
        $this->lastUpdatedBy = $setting->updatedBy?->name;
    }
}
