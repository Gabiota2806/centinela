@php
    $groupClasses = fn (string $name) => \Illuminate\Support\Arr::toCssClasses([
        'mt-1.5 flex items-stretch overflow-hidden rounded-lg border bg-white shadow-sm transition focus-within:ring-2',
        'border-danger focus-within:border-danger focus-within:ring-danger/30' => $errors->has($name),
        'border-line focus-within:border-brand-turquesa focus-within:ring-brand-turquesa/30' => ! $errors->has($name),
    ]);
@endphp

<div class="space-y-6">
    @if ($statusMessage)
        @php
            $bannerClasses = match ($statusType) {
                'warning' => 'border-warn/40 bg-warn/10 text-warn',
                'info' => 'border-brand-turquesa/40 bg-brand-50 text-brand-petroleo',
                default => 'border-ok/30 bg-ok/10 text-ok',
            };
            $bannerIcon = match ($statusType) {
                'warning' => 'exclamation-triangle',
                'info' => 'adjustments-horizontal',
                default => 'check-circle',
            };
        @endphp
        <div role="status" class="flex items-start gap-3 rounded-2xl border px-5 py-4 {{ $bannerClasses }}">
            <x-icon :name="$bannerIcon" class="w-5 h-5 shrink-0 mt-0.5" />
            <p class="text-sm font-semibold">{{ $statusMessage }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Formulario de parámetros epidemiológicos --}}
        <form wire:submit="save" novalidate class="lg:col-span-2 bg-white rounded-2xl border border-line shadow-card p-5 sm:p-6 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-line">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-petroleo shrink-0">
                        <x-icon name="adjustments-horizontal" class="w-5 h-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-ink">Parámetros de Alerta Temprana</h2>
                        <p class="text-xs text-ink-muted">
                            Valores de referencia usados para correlacionar precipitaciones, retardo biológico y casos febriles.
                        </p>
                    </div>
                </div>
                @if ($lastUpdatedAt)
                    <div class="text-xs text-ink-muted text-left sm:text-right">
                        <p>Última actualización: <span class="font-semibold text-ink">{{ $lastUpdatedAt }}</span></p>
                        @if ($lastUpdatedBy)
                            <p>Responsable: <span class="font-semibold text-ink">{{ $lastUpdatedBy }}</span></p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- Umbral de lluvia --}}
                <div>
                    <x-input-label for="rain_threshold_mm" value="Umbral de lluvia acumulada *" />
                    <div class="{{ $groupClasses('rain_threshold_mm') }}">
                        <input
                            id="rain_threshold_mm"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            x-data
                            x-on:input="const clean = $el.value.replace(',', '.').replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if ($el.value !== clean) { $el.value = clean; }"
                            wire:model.blur="rain_threshold_mm"
                            aria-invalid="{{ $errors->has('rain_threshold_mm') ? 'true' : 'false' }}"
                            aria-describedby="rain_threshold_mm-help rain_threshold_mm-error"
                            class="block h-11 w-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm font-medium text-ink focus:ring-0"
                        >
                        <span class="inline-flex shrink-0 select-none items-center border-l border-line bg-canvas px-3 text-xs font-semibold text-ink-muted">
                            mm
                        </span>
                    </div>
                    @error('rain_threshold_mm')
                        <p id="rain_threshold_mm-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                    @enderror
                    <p id="rain_threshold_mm-help" class="mt-1.5 text-xs text-ink-muted">
                        Precipitación acumulada en 7 días que activa alerta ambiental (referencia Formosa: 40 mm).
                    </p>
                </div>

                {{-- Ventana de desfase (time-lag) --}}
                <div>
                    <x-input-label for="time_lag_days" value="Ventana de desfase lluvia-casos *" />
                    <div class="{{ $groupClasses('time_lag_days') }}">
                        <input
                            id="time_lag_days"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            x-data
                            x-on:input="const clean = $el.value.replace(/[^0-9]/g, ''); if ($el.value !== clean) { $el.value = clean; }"
                            wire:model.blur="time_lag_days"
                            aria-invalid="{{ $errors->has('time_lag_days') ? 'true' : 'false' }}"
                            aria-describedby="time_lag_days-help time_lag_days-error"
                            class="block h-11 w-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm font-medium text-ink focus:ring-0"
                        >
                        <span class="inline-flex shrink-0 select-none items-center border-l border-line bg-canvas px-3 text-xs font-semibold text-ink-muted">
                            días
                        </span>
                    </div>
                    @error('time_lag_days')
                        <p id="time_lag_days-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                    @enderror
                    <p id="time_lag_days-help" class="mt-1.5 text-xs text-ink-muted">
                        Rango permitido: 7 a 14 días según ciclo biológico del vector <em>Aedes aegypti</em>.
                    </p>
                </div>

                {{-- Umbral de casos febriles --}}
                <div>
                    <x-input-label for="febrile_alert_threshold" value="Umbral de casos febriles por barrio *" />
                    <div class="{{ $groupClasses('febrile_alert_threshold') }}">
                        <input
                            id="febrile_alert_threshold"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            x-data
                            x-on:input="const clean = $el.value.replace(/[^0-9]/g, ''); if ($el.value !== clean) { $el.value = clean; }"
                            wire:model.blur="febrile_alert_threshold"
                            aria-invalid="{{ $errors->has('febrile_alert_threshold') ? 'true' : 'false' }}"
                            aria-describedby="febrile_alert_threshold-help febrile_alert_threshold-error"
                            class="block h-11 w-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm font-medium text-ink focus:ring-0"
                        >
                        <span class="inline-flex shrink-0 select-none items-center border-l border-line bg-canvas px-3 text-xs font-semibold text-ink-muted">
                            casos
                        </span>
                    </div>
                    @error('febrile_alert_threshold')
                        <p id="febrile_alert_threshold-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                    @enderror
                    <p id="febrile_alert_threshold-help" class="mt-1.5 text-xs text-ink-muted">
                        Cantidad mínima de consultas febriles en 7 días para señalizar brote barrial.
                    </p>
                </div>

                {{-- Probabilidad de alerta alta --}}
                @php
                    $normalizedProbability = str_replace(',', '.', trim((string) $high_risk_probability));
                    $initialPct = is_numeric($normalizedProbability) && (float) $normalizedProbability >= 0 && (float) $normalizedProbability <= 1
                        ? round(((float) $normalizedProbability) * 100).'%'
                        : '—';
                @endphp
                <div x-data="{
                        pctFrom(raw) {
                            const num = parseFloat(String(raw ?? '').replace(',', '.').trim());
                            return (! Number.isNaN(num) && num >= 0 && num <= 1) ? Math.round(num * 100) + '%' : '—';
                        },
                        pct: @js($initialPct),
                    }"
                    x-effect="pct = pctFrom($wire.high_risk_probability)"
                >
                    <x-input-label for="high_risk_probability" value="Probabilidad de alerta alta (0.10 – 0.99) *" />
                    <div class="{{ $groupClasses('high_risk_probability') }}">
                        <input
                            id="high_risk_probability"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            x-on:input="
                                const clean = $el.value.replace(',', '.').replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
                                if ($el.value !== clean) { $el.value = clean; }
                                pct = pctFrom(clean);
                            "
                            wire:model.blur="high_risk_probability"
                            aria-invalid="{{ $errors->has('high_risk_probability') ? 'true' : 'false' }}"
                            aria-describedby="high_risk_probability-help high_risk_probability-error"
                            class="block h-11 w-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm font-medium text-ink focus:ring-0"
                        >
                        <span
                            x-text="pct"
                            class="inline-flex min-w-[3.5rem] shrink-0 select-none items-center justify-center border-l border-line bg-canvas px-3 text-xs font-semibold text-ink-muted"
                        >{{ $initialPct }}</span>
                    </div>
                    @error('high_risk_probability')
                        <p id="high_risk_probability-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                    @enderror
                    <p id="high_risk_probability-help" class="mt-1.5 text-xs text-ink-muted">
                        Umbral decimal del modelo predictivo (ej. 0.70 equivale a 70% de probabilidad).
                    </p>
                </div>
            </div>

            <div class="pt-4 border-t border-line flex flex-wrap items-center justify-between gap-3">
                <x-secondary-button type="button" wire:click="restoreDefaults">
                    <x-icon name="arrow-uturn-left" class="w-4 h-4" />
                    Restaurar recomendados
                </x-secondary-button>

                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="save">
                    <x-icon name="check" class="w-4 h-4" />
                    <span wire:loading.remove wire:target="save">Guardar parámetros</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </x-primary-button>
            </div>
        </form>

        {{-- Resumen de contingencia climática (7 días) --}}
        <aside class="bg-white rounded-2xl border border-line shadow-card p-5 sm:p-6 space-y-5">
            <div class="flex items-start justify-between gap-3 pb-4 border-b border-line">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-petroleo shrink-0">
                        <x-icon name="cloud-rain" class="w-5 h-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-ink">Clima Local (7 días)</h2>
                        <p class="text-xs text-ink-muted">Formosa Capital · Open-Meteo</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="syncWeatherNow"
                    wire:loading.attr="disabled"
                    wire:target="syncWeatherNow"
                    title="Sincronizar Open-Meteo ahora"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa"
                >
                    <x-icon name="arrow-path" class="w-4 h-4 text-brand-petroleo" />
                    <span wire:loading.remove wire:target="syncWeatherNow">Sincronizar</span>
                    <span wire:loading wire:target="syncWeatherNow">...</span>
                </button>
            </div>

            <div class="rounded-xl border {{ $this->rainThresholdExceeded ? 'border-warn/40 bg-warn/10' : 'border-line bg-canvas' }} p-4 space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Lluvia acumulada (7 días)</span>
                    @if ($this->rainThresholdExceeded)
                        <span class="inline-flex items-center gap-1 rounded-full bg-warn/15 px-2 py-0.5 text-xs font-bold text-warn">
                            <x-icon name="exclamation-triangle" class="w-3.5 h-3.5" />
                            Supera umbral
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-ok/15 px-2 py-0.5 text-xs font-bold text-ok">
                            <x-icon name="check-circle" class="w-3.5 h-3.5" />
                            Bajo umbral
                        </span>
                    @endif
                </div>
                <p class="text-2xl font-extrabold text-ink">
                    {{ number_format($this->sevenDayRainTotal, 1, ',', '.') }} mm
                    <span class="text-xs font-medium text-ink-muted">/ umbral {{ number_format($this->activeRainThreshold, 1, ',', '.') }} mm</span>
                </p>
            </div>

            @if ($this->recentWeather === [])
                <div class="rounded-xl border border-dashed border-line p-4 text-center space-y-2">
                    <x-icon name="wifi-off" class="w-6 h-6 text-ink-subtle mx-auto" />
                    <p class="text-xs text-ink-muted">
                        Aún no hay registros meteorológicos locales. Ejecutá la sincronización manual o aguardá la corrida diaria de las 06:00.
                    </p>
                </div>
            @else
                <div class="divide-y divide-line">
                    @foreach ($this->recentWeather as $day)
                        <div class="py-2.5 flex items-center justify-between gap-2 text-xs">
                            <div>
                                <p class="font-semibold text-ink">{{ \Illuminate\Support\Carbon::parse($day['recorded_date'])->format('d/m/Y') }}</p>
                                <p class="text-ink-muted flex items-center gap-2 mt-0.5">
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon name="thermometer" class="w-3.5 h-3.5 text-ink-subtle" />
                                        {{ $day['temperature_min'] !== null ? number_format((float) $day['temperature_min'], 1, ',', '.') : '—' }}° / {{ $day['temperature_max'] !== null ? number_format((float) $day['temperature_max'], 1, ',', '.') : '—' }}°C
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon name="droplets" class="w-3.5 h-3.5 text-ink-subtle" />
                                        {{ $day['relative_humidity_mean'] !== null ? number_format((float) $day['relative_humidity_mean'], 0).'%' : '—' }}
                                    </span>
                                </p>
                            </div>
                            <span class="font-mono font-bold {{ (float) $day['precipitation_sum'] > 0 ? 'text-brand-petroleo' : 'text-ink-muted' }}">
                                {{ number_format((float) $day['precipitation_sum'], 1, ',', '.') }} mm
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
</div>
