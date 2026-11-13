<div class="space-y-6">
    {{-- Banner de Notificación Flash --}}
    @if (session()->has('status'))
        <div class="p-4 rounded-xl border border-green-200 bg-green-50 text-green-800 flex items-start gap-3">
            <svg class="w-5 h-5 text-green-600 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div class="flex-1">
                <p class="text-sm font-semibold">{{ session('status') }}</p>
                @if ($savedSuccessfully)
                    <div class="mt-2">
                        <button
                            type="button"
                            wire:click="resetEvaluation"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700 hover:text-green-900 underline"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Evaluar nuevo paciente
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Paso 1: Selección / Ficha del Paciente --}}
    <div class="bg-white border border-line rounded-2xl shadow-card p-5 sm:p-6">
        <div class="flex items-center justify-between pb-4 border-b border-line mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-brand-azul/10 text-brand-azul flex items-center justify-center font-bold text-sm">
                    1
                </div>
                <h2 class="text-lg font-bold text-ink">Identificación del Paciente</h2>
            </div>
            @if ($patient)
                <button
                    type="button"
                    wire:click="deselectPatient"
                    class="text-xs font-semibold text-ink-muted hover:text-ink inline-flex items-center gap-1 px-2.5 py-1.5 border border-line rounded-lg hover:bg-slate-50 transition"
                >
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Cambiar Paciente
                </button>
            @endif
        </div>

        @if ($patient)
            {{-- Ficha del Paciente Seleccionado --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-muted block">Paciente</span>
                    <span class="text-sm font-bold text-ink">{{ $patient->full_name }}</span>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-muted block">DNI</span>
                    <span class="text-sm font-medium text-ink">{{ $patient->dni }}</span>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-muted block">Barrio (Formosa)</span>
                    <span class="text-sm font-medium text-ink">{{ $patient->neighborhood?->name ?? 'Sin barrio asignado' }}</span>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-muted block">Fecha de Nacimiento</span>
                    <span class="text-sm font-medium text-ink">
                        {{ $patient->birth_date ? $patient->birth_date->format('d/m/Y') : '-' }}
                    </span>
                </div>
            </div>
        @else
            {{-- Buscador y Selección Rápida de Pacientes --}}
            <div class="space-y-4">
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchPatient"
                        placeholder="Buscar paciente por DNI, nombre o apellido..."
                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-line focus:ring-2 focus:ring-brand-azul focus:border-brand-azul text-sm"
                    />
                    <svg class="w-5 h-5 text-ink-muted absolute left-3 top-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>

                @if (count($availablePatients) > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($availablePatients as $item)
                            <div
                                wire:key="patient-{{ $item->id }}"
                                wire:click="selectPatient({{ $item->id }})"
                                class="p-3 rounded-xl border border-line bg-white hover:border-brand-azul hover:bg-slate-50 cursor-pointer transition flex flex-col justify-between"
                            >
                                <div>
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-sm font-bold text-ink">{{ $item->full_name }}</h3>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-ink-muted font-mono">DNI {{ $item->dni }}</span>
                                    </div>
                                    <p class="text-xs text-ink-muted mt-1">
                                        {{ $item->neighborhood?->name ?? 'Barrio no especificado' }}
                                    </p>
                                </div>
                                <div class="mt-3 flex items-center justify-end text-xs font-semibold text-brand-azul gap-1">
                                    <span>Seleccionar para triage</span>
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center rounded-xl bg-slate-50 border border-dashed border-line text-sm text-ink-muted">
                        No se encontraron pacientes disponibles para triage con ese criterio.
                    </div>
                @endif

                @error('patientId')
                    <p class="text-xs font-semibold text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>

    {{-- Layout Principal: Evaluación Clínica y Panel Resultado --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Columna Izquierda: Signos Vitales y Discriminadores (2 cols) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Paso 2: Signos Vitales --}}
            <div class="bg-white border border-line rounded-2xl shadow-card p-5 sm:p-6">
                <div class="flex items-center gap-2 pb-4 border-b border-line mb-4">
                    <div class="w-8 h-8 rounded-lg bg-brand-azul/10 text-brand-azul flex items-center justify-center font-bold text-sm">
                        2
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-ink">Signos Vitales Cuantitativos</h2>
                        <p class="text-xs text-ink-muted">Valores fisiológicos medidos durante la admisión clínica.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    {{-- Temperatura --}}
                    <div>
                        <label for="vital-temp" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                            Temperatura (°C)
                        </label>
                        <div class="relative">
                            <input
                                id="vital-temp"
                                type="number"
                                step="0.1"
                                wire:model.live.debounce.250ms="temperature"
                                placeholder="Ej: 36.8"
                                class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:ring-2 focus:ring-brand-azul"
                            />
                            <span class="absolute right-3 top-2.5 text-xs text-ink-muted">°C</span>
                        </div>
                        @error('temperature') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Presión Sistólica --}}
                    <div>
                        <label for="vital-pas" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                            PAS Sistólica (mmHg)
                        </label>
                        <div class="relative">
                            <input
                                id="vital-pas"
                                type="number"
                                wire:model.live.debounce.250ms="systolic_bp"
                                placeholder="Ej: 120"
                                class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:ring-2 focus:ring-brand-azul"
                            />
                            <span class="absolute right-3 top-2.5 text-xs text-ink-muted">mmHg</span>
                        </div>
                        @error('systolic_bp') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Presión Diastólica --}}
                    <div>
                        <label for="vital-pad" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                            PAD Diastólica (mmHg)
                        </label>
                        <div class="relative">
                            <input
                                id="vital-pad"
                                type="number"
                                wire:model.live.debounce.250ms="diastolic_bp"
                                placeholder="Ej: 80"
                                class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:ring-2 focus:ring-brand-azul"
                            />
                            <span class="absolute right-3 top-2.5 text-xs text-ink-muted">mmHg</span>
                        </div>
                        @error('diastolic_bp') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Frecuencia Cardíaca --}}
                    <div>
                        <label for="vital-hr" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                            Frecuencia Cardíaca (lpm)
                        </label>
                        <div class="relative">
                            <input
                                id="vital-hr"
                                type="number"
                                wire:model.live.debounce.250ms="heart_rate"
                                placeholder="Ej: 75"
                                class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:ring-2 focus:ring-brand-azul"
                            />
                            <span class="absolute right-3 top-2.5 text-xs text-ink-muted">lpm</span>
                        </div>
                        @error('heart_rate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Frecuencia Respiratoria --}}
                    <div>
                        <label for="vital-rr" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                            Frecuencia Respiratoria (rpm)
                        </label>
                        <div class="relative">
                            <input
                                id="vital-rr"
                                type="number"
                                wire:model.live.debounce.250ms="respiratory_rate"
                                placeholder="Ej: 16"
                                class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:ring-2 focus:ring-brand-azul"
                            />
                            <span class="absolute right-3 top-2.5 text-xs text-ink-muted">rpm</span>
                        </div>
                        @error('respiratory_rate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Saturación de Oxígeno (SpO2) --}}
                    <div>
                        <label for="vital-spo2" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                            Saturación O2 (SpO2)
                        </label>
                        <div class="relative">
                            <input
                                id="vital-spo2"
                                type="number"
                                wire:model.live.debounce.250ms="spo2"
                                placeholder="Ej: 98"
                                class="w-full rounded-xl border border-line px-3 py-2 text-sm focus:ring-2 focus:ring-brand-azul font-bold @if($spo2 !== null && $spo2 < 85) text-red-600 @elseif($spo2 !== null && $spo2 <= 92) text-orange-600 @endif"
                            />
                            <span class="absolute right-3 top-2.5 text-xs text-ink-muted">%</span>
                        </div>
                        @error('spo2') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Paso 3: Discriminadores Clínicos Manchester --}}
            <div class="bg-white border border-line rounded-2xl shadow-card p-5 sm:p-6">
                <div class="flex items-center gap-2 pb-4 border-b border-line mb-4">
                    <div class="w-8 h-8 rounded-lg bg-brand-azul/10 text-brand-azul flex items-center justify-center font-bold text-sm">
                        3
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-ink">Discriminadores Clínicos Manchester (MTS)</h2>
                        <p class="text-xs text-ink-muted">Seleccione los hallazgos clínicos observados durante la anamnesis.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach ($catalogue as $groupKey => $group)
                        <div class="border border-line rounded-xl overflow-hidden">
                            <div class="px-4 py-2.5 bg-slate-50 border-b border-line flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-ink flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $group['priority']->badgeClasses() }}"></span>
                                    {{ $group['label'] }}
                                </span>
                            </div>
                            <div class="p-3 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                @foreach ($group['items'] as $itemKey => $itemLabel)
                                    <label
                                        wire:key="disc-{{ $itemKey }}"
                                        class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition border border-transparent hover:border-line"
                                    >
                                        <input
                                            type="checkbox"
                                            wire:model.live="discriminators.{{ $itemKey }}"
                                            class="mt-0.5 rounded border-line text-brand-azul focus:ring-brand-azul"
                                        />
                                        <span class="text-xs text-ink leading-tight">{{ $itemLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Paso 4: Motivo de Consulta y Síntomas --}}
            <div class="bg-white border border-line rounded-2xl shadow-card p-5 sm:p-6">
                <div class="flex items-center gap-2 pb-4 border-b border-line mb-4">
                    <div class="w-8 h-8 rounded-lg bg-brand-azul/10 text-brand-azul flex items-center justify-center font-bold text-sm">
                        4
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-ink">Motivo de Consulta y Anamnesis</h2>
                        <p class="text-xs text-ink-muted">Sintomatología referida por el paciente o acompañante.</p>
                    </div>
                </div>

                <div>
                    <label for="triage-symptoms" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                        Descripción de Síntomas *
                    </label>
                    <textarea
                        id="triage-symptoms"
                        rows="3"
                        wire:model.live.debounce.300ms="symptoms"
                        placeholder="Describa el motivo de consulta, tiempo de evolución y síntomas asociados..."
                        class="w-full rounded-xl border border-line p-3 text-sm focus:ring-2 focus:ring-brand-azul"
                    ></textarea>
                    @error('symptoms')
                        <p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Columna Derecha: Tarjeta Determinística de Resultado MTS (Sticky) --}}
        <div class="lg:col-span-1 space-y-4 lg:sticky lg:top-6">
            <div class="bg-white border border-line rounded-2xl shadow-card overflow-hidden">
                {{-- Encabezado con Color Manchester Dinámico --}}
                <div class="p-5 text-center transition-colors duration-300 {{ $badgeClasses }}">
                    <span class="text-xs uppercase font-extrabold tracking-widest opacity-90 block">
                        Clasificación Algorítmica MTS
                    </span>
                    <h3 class="text-2xl font-black mt-1 tracking-tight">
                        {{ $priorityLabel }}
                    </h3>
                </div>

                {{-- Detalles del Cálculo --}}
                <div class="p-5 space-y-4">
                    {{-- Tiempo Máximo de Espera --}}
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                        <span class="text-xs font-semibold uppercase text-ink-muted block">Tiempo Objetivo de Espera</span>
                        <div class="flex items-baseline gap-1 mt-1">
                            <span class="text-3xl font-extrabold text-ink font-mono">{{ $targetWaitMinutes }}</span>
                            <span class="text-xs font-bold text-ink-muted">minutos máximos</span>
                        </div>
                        <p class="text-[11px] text-ink-muted mt-1">
                            @if ($targetWaitMinutes === 0)
                                Atención médica inmediata (Emergencia de Reanimación).
                            @else
                                Tiempo máximo tolerado antes de atención en consultorio.
                            @endif
                        </p>
                    </div>

                    {{-- Discriminador Disparador --}}
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                        <span class="text-xs font-semibold uppercase text-ink-muted block">Criterio Determinante</span>
                        <p class="text-xs font-bold text-ink mt-1">
                            {{ $activeDiscriminator }}
                        </p>
                    </div>

                    {{-- Botón de Acción Principal --}}
                    <div class="pt-2">
                        <button
                            type="button"
                            wire:click="save"
                            wire:loading.attr="disabled"
                            class="w-full py-3 px-4 rounded-xl bg-brand-azul hover:bg-brand-azul-dark text-white font-bold text-sm shadow-sm transition flex items-center justify-center gap-2 disabled:opacity-50"
                        >
                            <svg wire:loading.remove class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <svg wire:loading class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span wire:loading.remove>Registrar Triage Clínico</span>
                            <span wire:loading>Procesando Triage...</span>
                        </button>
                    </div>

                    <p class="text-[11px] text-center text-ink-muted leading-tight">
                        La clasificación determinística es trazable y queda respaldada en el historial clínico del paciente.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
