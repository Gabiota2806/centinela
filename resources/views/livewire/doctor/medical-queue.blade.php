@php
    $tz = config('centinela.timezone');

    /** Texto del tiempo restante frente al objetivo MTS (se recalcula en el navegador). */
    $waitText = function (\App\Models\Triage $t): array {
        $remaining = (int) ceil(now()->diffInSeconds($t->created_at->copy()->addMinutes($t->target_wait_minutes), false) / 60);

        if ($t->target_wait_minutes === 0) {
            return ['Atención inmediata', true];
        }

        return $remaining > 0
            ? ['Quedan '.\App\Livewire\Doctor\MedicalQueue::formatMinutes($remaining), false]
            : ['Excedido por '.\App\Livewire\Doctor\MedicalQueue::formatMinutes(abs($remaining)), true];
    };

    $vitals = [
        'temperature' => ['Temp.', '°C'],
        'heart_rate' => ['FC', 'lpm'],
        'spo2' => ['SpO2', '%'],
        'respiratory_rate' => ['FR', 'rpm'],
    ];
@endphp

<div class="space-y-6"
     wire:poll.5s
     x-data="{
         now: Date.now(),
         init() { setInterval(() => this.now = Date.now(), 15000) },
         wait(deadline, target) {
             if (target === 0) return 'Atención inmediata';
             const min = Math.ceil((deadline - this.now) / 60000);
             return min > 0 ? `Quedan ${this.fmt(min)}` : `Excedido por ${this.fmt(Math.abs(min))}`;
         },
         fmt(m) {
             if (m < 60) return `${m} min`;
             if (m < 1440) return `${Math.floor(m / 60)} h ${m % 60} min`;
             return `${Math.floor(m / 1440)} d ${Math.floor((m % 1440) / 60)} h`;
         },
         overdue(deadline, target) { return target === 0 || deadline <= this.now },
     }">

    {{-- Resultado de la última acción --}}
    @if ($notice)
        <div role="status"
             class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $noticeType === 'error' ? 'border-danger/30 bg-red-50 text-danger' : 'border-green-200 bg-green-50 text-green-800' }}">
            <x-icon :name="$noticeType === 'error' ? 'exclamation-triangle' : 'check-circle'" class="w-5 h-5 shrink-0" />
            <p class="font-medium">{{ $notice }}</p>
        </div>
    @endif

    {{-- Resumen --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        @foreach ([['En espera', $this->waiting->count(), 'clock'], ['Llamados', $this->called->count(), 'megaphone'], ['En consulta', $this->inConsultation->count(), 'stethoscope']] as [$label, $count, $icon])
            <div class="flex items-center gap-3 rounded-2xl border border-line bg-white px-4 py-3 shadow-card">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-petroleo">
                    <x-icon :name="$icon" class="w-5 h-5" />
                </span>
                <div>
                    <p class="text-2xl font-extrabold text-ink leading-none">{{ $count }}</p>
                    <p class="text-xs text-ink-muted mt-1">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Llamados a consultorio: acciones selectivas (RF-CONS-06) --}}
    @if ($this->called->isNotEmpty())
        <section aria-labelledby="called-title" class="rounded-2xl border border-brand-turquesa/40 bg-brand-50/60 p-4 sm:p-5">
            <h2 id="called-title" class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-brand-petroleo">
                <x-icon name="megaphone" class="w-5 h-5" /> Llamados a consultorio
            </h2>
            <ul class="mt-3 space-y-3">
                @foreach ($this->called as $triage)
                    <li wire:key="called-{{ $triage->id }}" class="flex flex-col gap-3 rounded-xl border border-line bg-white p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 min-w-10 items-center justify-center rounded-lg px-2 text-lg font-extrabold {{ $triage->final_priority->badgeClasses() }}">{{ $triage->final_priority->level() }}</span>
                            <div>
                                <p class="font-bold text-ink">{{ $triage->patient->full_name }}</p>
                                <p class="text-xs text-ink-muted"><span class="font-mono">{{ \App\Livewire\Doctor\MedicalQueue::ticketNumber($triage) }}</span> · {{ $triage->final_priority->label() }} · llamado {{ \App\Livewire\Doctor\MedicalQueue::localTime($triage->updated_at) }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="startConsultation({{ $triage->id }})" wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-petroleo px-3 py-2 text-sm font-semibold text-white hover:bg-brand-noche disabled:opacity-50">
                                <x-icon name="arrow-right" class="w-4 h-4" /> Ingresar a consulta
                            </button>
                            <button type="button" wire:click="returnToQueue({{ $triage->id }})" wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-white px-3 py-2 text-sm font-semibold text-ink hover:bg-slate-50 disabled:opacity-50">
                                <x-icon name="arrow-uturn-left" class="w-4 h-4" /> Volver a espera
                            </button>
                            <button type="button" wire:click="markAbsent({{ $triage->id }})" wire:loading.attr="disabled"
                                    wire:confirm="¿Marcar a {{ $triage->patient->full_name }} como ausente? Sale de la cola y podrá volver a ser admitido."
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-danger/40 bg-white px-3 py-2 text-sm font-semibold text-danger hover:bg-red-50 disabled:opacity-50">
                                <x-icon name="user-minus" class="w-4 h-4" /> Cancelar / Ausente
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- En consulta --}}
    @if ($this->inConsultation->isNotEmpty())
        <section aria-labelledby="consulting-title" class="rounded-2xl border border-line bg-white p-4 sm:p-5 shadow-card">
            <h2 id="consulting-title" class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-ink-muted">
                <x-icon name="stethoscope" class="w-5 h-5" /> En consulta
            </h2>
            <ul class="mt-3 divide-y divide-line">
                @foreach ($this->inConsultation as $triage)
                    <li wire:key="consulting-{{ $triage->id }}" class="flex items-center justify-between gap-3 py-3">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 font-extrabold {{ $triage->final_priority->badgeClasses() }}">{{ $triage->final_priority->level() }}</span>
                            <div>
                                <p class="font-semibold text-ink">{{ $triage->patient->full_name }}</p>
                                <p class="text-xs text-ink-muted"><span class="font-mono">{{ \App\Livewire\Doctor\MedicalQueue::ticketNumber($triage) }}</span>
                                    @if ($triage->consultation?->started_at) · desde {{ \App\Livewire\Doctor\MedicalQueue::localTime($triage->consultation->started_at) }} @endif
                                </p>
                            </div>
                        </div>
                        @if (Route::has('consultations.show'))
                            <a href="{{ route('consultations.show', $triage) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-petroleo hover:underline">
                                Abrir consultorio <x-icon name="arrow-right" class="w-4 h-4" />
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Cola de espera ordenada por MTS + FIFO (RF-CONS-01/02) --}}
    <section aria-labelledby="waiting-title">
        <h2 id="waiting-title" class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-ink-muted">
            <x-icon name="clock" class="w-5 h-5" /> En espera
        </h2>

        @if ($this->waiting->isEmpty())
            <div class="mt-3 flex flex-col items-center gap-2 rounded-2xl border border-dashed border-line bg-white px-6 py-10 text-center">
                <x-icon name="check-circle" class="w-8 h-8 text-ok" />
                <p class="font-semibold text-ink">No hay pacientes en espera</p>
                <p class="text-sm text-ink-muted">Los pacientes clasificados en triage aparecen acá automáticamente.</p>
            </div>
        @else
            <ol class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-2">
                @foreach ($this->waiting as $index => $triage)
                    @php
                        [$text, $overdue] = $waitText($triage);
                        $deadline = $triage->created_at->copy()->addMinutes($triage->target_wait_minutes)->getTimestampMs();
                        $vs = $triage->vital_signs ?? [];
                    @endphp
                    <li wire:key="waiting-{{ $triage->id }}"
                        class="flex overflow-hidden rounded-2xl border border-line bg-white shadow-card">
                        <div class="flex w-16 shrink-0 flex-col items-center justify-center gap-1 {{ $triage->final_priority->badgeClasses() }}">
                            <span class="text-xs font-semibold opacity-90">#{{ $index + 1 }}</span>
                            <span class="text-3xl font-extrabold leading-none">{{ $triage->final_priority->level() }}</span>
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-3 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-ink">{{ $triage->patient->full_name }}</p>
                                    <p class="text-xs text-ink-muted">
                                        <span class="font-mono">{{ \App\Livewire\Doctor\MedicalQueue::ticketNumber($triage) }}</span>
                                        · {{ $triage->final_priority->label() }}
                                        @if ($triage->is_override) · <span class="font-semibold text-warn">Override</span> @endif
                                    </p>
                                    <p class="mt-0.5 flex items-center gap-1 text-xs text-ink-muted">
                                        <x-icon name="map-pin" class="w-3.5 h-3.5" /> {{ $triage->patient->neighborhood?->name ?? 'Barrio no especificado' }}
                                    </p>
                                </div>

                                <div data-overdue="{{ $overdue ? 'true' : 'false' }}"
                                     x-bind:data-overdue="overdue({{ $deadline }}, {{ $triage->target_wait_minutes }}) ? 'true' : 'false'"
                                     class="rounded-lg border px-2.5 py-1.5 text-right data-[overdue=true]:border-danger/40 data-[overdue=true]:bg-red-50 data-[overdue=true]:text-danger data-[overdue=false]:border-line data-[overdue=false]:text-ink">
                                    <p class="flex items-center justify-end gap-1 text-sm font-bold">
                                        <x-icon name="clock" class="w-4 h-4" />
                                        <span x-text="wait({{ $deadline }}, {{ $triage->target_wait_minutes }})">{{ $text }}</span>
                                    </p>
                                    <p class="text-[11px] opacity-80">Espera máx. {{ $triage->target_wait_minutes }} min · llegó {{ \App\Livewire\Doctor\MedicalQueue::localTime($triage->created_at) }}</p>
                                </div>
                            </div>

                            <dl class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                @foreach ($vitals as $key => [$label, $unit])
                                    @if (isset($vs[$key]))
                                        <div class="flex gap-1"><dt class="text-ink-muted">{{ $label }}</dt><dd class="font-semibold text-ink">{{ $vs[$key] }} {{ $unit }}</dd></div>
                                    @endif
                                @endforeach
                                @if (isset($vs['systolic_bp'], $vs['diastolic_bp']))
                                    <div class="flex gap-1"><dt class="text-ink-muted">TA</dt><dd class="font-semibold text-ink">{{ $vs['systolic_bp'] }}/{{ $vs['diastolic_bp'] }} mmHg</dd></div>
                                @endif
                            </dl>

                            <div class="flex flex-wrap justify-end gap-2">
                                <button type="button" wire:click="reclassify({{ $triage->id }})"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-line px-3 py-2 text-sm font-semibold text-ink hover:bg-slate-50">
                                    <x-icon name="arrows-up-down" class="w-4 h-4" /> Reclasificar
                                </button>
                                <button type="button" wire:click="callPatient({{ $triage->id }})" wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-petroleo px-3 py-2 text-sm font-semibold text-white hover:bg-brand-noche disabled:opacity-50">
                                    <x-icon name="megaphone" class="w-4 h-4" /> Llamar a consultorio
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
