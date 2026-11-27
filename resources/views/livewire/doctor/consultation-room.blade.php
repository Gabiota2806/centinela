@php
    $tz = config('centinela.timezone');
    $patient = $triage->patient;
    $vs = $triage->vital_signs ?? [];
    $vitals = [
        ['Temperatura', isset($vs['temperature']) ? $vs['temperature'].' °C' : null, 'thermometer'],
        ['Tensión arterial', isset($vs['systolic_bp'], $vs['diastolic_bp']) ? $vs['systolic_bp'].'/'.$vs['diastolic_bp'].' mmHg' : null, 'activity'],
        ['Frecuencia cardíaca', isset($vs['heart_rate']) ? $vs['heart_rate'].' lpm' : null, 'activity'],
        ['Frecuencia respiratoria', isset($vs['respiratory_rate']) ? $vs['respiratory_rate'].' rpm' : null, 'activity'],
        ['Saturación O2', isset($vs['spo2']) ? $vs['spo2'].' %' : null, 'activity'],
    ];
    $discriminators = $this->discriminatorLabels();
@endphp

<div class="grid grid-cols-1 gap-6 xl:grid-cols-5">

    {{-- Ficha clínica (RF-CONS-08) --}}
    <section aria-labelledby="ficha-title" class="space-y-4 xl:col-span-2">
        <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
            <div class="flex items-center gap-4 px-5 py-4 {{ $triage->final_priority->badgeClasses() }}">
                <span class="text-4xl font-extrabold leading-none">{{ $triage->final_priority->level() }}</span>
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide">{{ $triage->final_priority->label() }}</p>
                    <p class="text-xs opacity-90">
                        <span class="font-mono">{{ \App\Livewire\Triage\PrintTicket::ticketNumber($triage->id) }}</span>
                        · Espera máx. {{ $triage->target_wait_minutes }} min
                        @if ($triage->is_override) · Override clínico @endif
                    </p>
                </div>
            </div>

            <div class="space-y-4 p-5">
                <div>
                    <h2 id="ficha-title" class="text-xl font-extrabold text-ink">{{ $patient->full_name }}</h2>
                    <p class="text-sm text-ink-muted">
                        DNI <span class="font-mono">{{ $patient->dni }}</span>
                        @if ($patient->birth_date) · {{ $patient->birth_date->age }} años @endif
                        @if ($patient->gender) · {{ ucfirst($patient->gender) }} @endif
                    </p>
                    <p class="mt-1 flex items-center gap-1 text-sm text-ink-muted">
                        <x-icon name="map-pin" class="w-4 h-4" /> {{ $patient->neighborhood?->name ?? 'Barrio no especificado' }}
                    </p>
                </div>

                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-canvas px-3 py-2">
                        <dt class="text-xs text-ink-muted">Llegada</dt>
                        <dd class="font-semibold text-ink">{{ $triage->created_at->timezone($tz)->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div class="rounded-xl bg-canvas px-3 py-2">
                        <dt class="text-xs text-ink-muted">En consulta desde</dt>
                        <dd class="font-semibold text-ink">{{ $triage->consultation?->started_at?->timezone($tz)->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="col-span-2 rounded-xl bg-canvas px-3 py-2">
                        <dt class="text-xs text-ink-muted">Clasificó</dt>
                        <dd class="font-semibold text-ink">{{ $triage->user?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-5 shadow-card">
            <h3 class="text-xs font-bold uppercase tracking-wide text-ink-muted">Signos vitales</h3>
            <dl class="mt-3 grid grid-cols-2 gap-3">
                @foreach ($vitals as [$label, $value, $icon])
                    <div class="rounded-xl border border-line px-3 py-2">
                        <dt class="flex items-center gap-1 text-xs text-ink-muted"><x-icon :name="$icon" class="w-3.5 h-3.5" /> {{ $label }}</dt>
                        <dd class="text-lg font-bold text-ink">{{ $value ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="rounded-2xl border border-line bg-white p-5 shadow-card space-y-4">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-ink-muted">Motivo de consulta</h3>
                <p class="mt-1 text-sm text-ink whitespace-pre-line">{{ $triage->symptoms ?: '—' }}</p>
            </div>
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-ink-muted">Discriminadores MTS</h3>
                @if ($discriminators)
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($discriminators as $label)
                            <li class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-petroleo">{{ $label }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-1 text-sm text-ink-muted">Sin discriminadores marcados.</p>
                @endif
                @if (! empty($vs['calculated_discriminator']))
                    <p class="mt-2 text-xs text-ink-muted">Criterio determinante: <span class="font-semibold text-ink">{{ $vs['calculated_discriminator'] }}</span></p>
                @endif
            </div>
        </div>
    </section>

    {{-- Cierre de la consulta (RF-CONS-09/10) --}}
    <section aria-labelledby="cierre-title" class="xl:col-span-3">
        <form wire:submit="save" class="space-y-6 rounded-2xl border border-line bg-white p-5 shadow-card sm:p-6">
            <div>
                <h2 id="cierre-title" class="text-lg font-extrabold text-ink">Cierre de la consulta</h2>
                <p class="text-sm text-ink-muted">El diagnóstico presuntivo es obligatorio y alimenta la vigilancia epidemiológica del CAPS.</p>
            </div>

            @if ($error)
                <div role="alert" class="flex items-start gap-3 rounded-xl border border-danger/30 bg-red-50 px-4 py-3 text-sm text-danger">
                    <x-icon name="exclamation-triangle" class="w-5 h-5 shrink-0" />
                    <p class="font-medium">{{ $error }}</p>
                </div>
            @endif

            <fieldset>
                <legend class="text-sm font-bold text-ink">Diagnóstico presuntivo <span class="text-danger">*</span></legend>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($diagnoses as $option)
                        <label data-diagnosis="{{ $option->value }}" wire:key="dx-{{ $option->value }}"
                               class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition {{ $diagnosis === $option->value ? 'border-brand-turquesa bg-brand-50 ring-2 ring-brand-turquesa' : 'border-line hover:border-brand-turquesa' }}">
                            <input type="radio" wire:model.live="diagnosis" value="{{ $option->value }}" class="mt-0.5 text-brand-petroleo focus:ring-brand-turquesa">
                            <span>
                                <span class="block text-sm font-semibold text-ink">{{ $option->label() }}</span>
                                @if ($option->isFebrile())
                                    <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-warn">
                                        <x-icon name="thermometer" class="w-3.5 h-3.5" /> Vigilancia epidemiológica
                                    </span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('diagnosis') <p id="diagnosis-error" class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset>
                <legend class="text-sm font-bold text-ink">Resultado de la atención</legend>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ([['completed', 'Alta / tratamiento ambulatorio', 'check-circle'], ['referred_sipec', 'Derivación a hospital (SIPEC)', 'arrow-right']] as [$value, $label, $icon])
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-3 {{ $outcome === $value ? 'border-brand-turquesa bg-brand-50 ring-2 ring-brand-turquesa' : 'border-line hover:border-brand-turquesa' }}">
                            <input type="radio" wire:model.live="outcome" value="{{ $value }}" class="text-brand-petroleo focus:ring-brand-turquesa">
                            <x-icon :name="$icon" class="w-5 h-5 text-brand-petroleo" />
                            <span class="text-sm font-semibold text-ink">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('outcome') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
            </fieldset>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div>
                    <label for="clinicalNotes" class="text-sm font-bold text-ink">Notas clínicas <span class="font-normal text-ink-muted">(opcional)</span></label>
                    <textarea id="clinicalNotes" wire:model.blur="clinicalNotes" rows="5" maxlength="2000"
                              placeholder="Evolución, examen físico, hallazgos..."
                              class="mt-1 block w-full rounded-lg border-line text-sm focus:border-brand-turquesa focus:ring-brand-turquesa"></textarea>
                    @error('clinicalNotes') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="prescription" class="text-sm font-bold text-ink">Prescripción e indicaciones <span class="font-normal text-ink-muted">(opcional)</span></label>
                    <textarea id="prescription" wire:model.blur="prescription" rows="5" maxlength="2000"
                              placeholder="Medicación, dosis, pautas de alarma..."
                              class="mt-1 block w-full rounded-lg border-line text-sm focus:border-brand-turquesa focus:ring-brand-turquesa"></textarea>
                    @error('prescription') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-5 sm:flex-row sm:justify-between">
                <a href="{{ route('consultations.index') }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-line px-4 py-2.5 text-sm font-semibold text-ink hover:bg-slate-50">
                    <x-icon name="arrow-uturn-left" class="w-4 h-4" /> Volver a la cola
                </a>
                <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-petroleo px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-noche disabled:opacity-50">
                    <x-icon name="check-circle" class="w-5 h-5" />
                    <span wire:loading.remove wire:target="save">Cerrar consulta</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </button>
            </div>
        </form>
    </section>
</div>
