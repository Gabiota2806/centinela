@php
    // Estilo común de campos (docs/specs/ui/spec.md). El borde cambia a `danger` si el campo tiene error.
    $field = fn (string $name, string $extra = '') => \Illuminate\Support\Arr::toCssClasses([
        'mt-1.5 block w-full h-11 rounded-lg shadow-sm text-ink placeholder:text-ink-subtle',
        $extra => $extra !== '',
        'border-danger focus:border-danger focus:ring-danger' => $errors->has($name),
        'border-line focus:border-brand-turquesa focus:ring-brand-turquesa' => ! $errors->has($name),
    ]);
@endphp

<div class="space-y-6 scroll-mt-24"
     x-data
     x-on:triage-saved.window="$el.scrollIntoView({ block: 'start' })">

    @if ($createdPatientId && $createdTriageId)
        {{-- Confirmación del ingreso unificado Admisión + Triage (CEN-74) --}}
        <div role="status" class="flex flex-col sm:flex-row sm:items-center gap-4 rounded-2xl border border-ok/30 bg-ok/10 px-5 py-4">
            <x-icon name="check-circle" class="w-6 h-6 text-ok shrink-0" />
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold text-ok">Paciente registrado</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-white border border-ok/30 text-ink">
                        #{{ $createdTicketNumber }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $createdPriorityBadgeClasses }}">
                        {{ $createdPriorityLabel }}
                    </span>
                    @if ($createdQueuePosition !== null)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-brand-50 text-brand-petroleo border border-brand-turquesa/40">
                            Posición estimada en espera: #{{ $createdQueuePosition }}
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-ink">
                    {{ $createdPatientName }} · DNI {{ $createdPatientDni }} · Espera máxima: {{ $createdTargetWaitMinutes }} min
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <x-secondary-button type="button" wire:click="showTicket">
                    <x-icon name="printer" class="w-4 h-4" />
                    Ver ticket
                </x-secondary-button>
                <x-secondary-button type="button" wire:click="newPatient">
                    Registrar otro
                </x-secondary-button>
            </div>
        </div>
    @endif

    <form wire:submit="save" novalidate class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Columna Izquierda: Datos Demográficos, Signos Vitales, Motivo y Discriminadores (2 cols) --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Paso 1: Datos Personales y Domicilio --}}
            <div class="bg-white rounded-2xl border border-line shadow-card p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-2.5 pb-4 border-b border-line">
                    <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-petroleo flex items-center justify-center font-bold text-sm shrink-0">
                        1
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-ink">Identificación y Datos Personales</h2>
                        <p class="text-xs text-ink-muted">Ingresá el DNI para verificar si el paciente ya se encuentra en el padrón del CAPS.</p>
                    </div>
                </div>

                @if ($activeTriageId && $activeTriageTicket)
                    <div role="alert" class="rounded-xl border border-danger bg-danger/10 px-4 py-3 text-danger flex items-start gap-3">
                        <x-icon name="lock-closed" class="w-5 h-5 shrink-0 mt-0.5" />
                        <div class="text-sm">
                            <p class="font-bold">Triage activo en curso (#{{ $activeTriageTicket }})</p>
                            <p class="mt-0.5 text-xs">
                                Este paciente ya posee una atención activa en cola o consultorio con el ticket <strong>#{{ $activeTriageTicket }}</strong>. No es posible registrar un ingreso duplicado hasta que finalice o se cancele la atención actual.
                            </p>
                        </div>
                    </div>
                @elseif ($isRecurringPatient)
                    <div role="status" class="rounded-xl border border-brand-turquesa/40 bg-brand-50 px-4 py-3 text-brand-petroleo flex items-start gap-3">
                        <x-icon name="check-circle" class="w-5 h-5 shrink-0 mt-0.5 text-brand-turquesa" />
                        <div class="text-xs">
                            <p class="font-bold text-sm">Paciente recurrente detectado en el padrón</p>
                            <p class="mt-0.5 text-ink-muted">
                                Se precargaron sus datos personales editables. Podés actualizar teléfono, domicilio o barrio y completar los signos vitales para abrir un nuevo triage.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-5">
                    {{-- DNI --}}
                    <div>
                        <x-input-label for="dni" value="DNI *" />
                        <input id="dni" type="text" inputmode="numeric" autocomplete="off" maxlength="20"
                               wire:model.live.debounce.400ms="dni" placeholder="Solo números, sin puntos"
                               aria-invalid="{{ $errors->has('dni') ? 'true' : 'false' }}" aria-describedby="dni-error"
                               class="{{ $field('dni') }}" autofocus>
                        @error('dni') <p id="dni-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Fecha de nacimiento --}}
                    <div>
                        <x-input-label for="birth_date" value="Fecha de nacimiento *" />
                        <input id="birth_date" type="date" max="{{ now()->toDateString() }}"
                               wire:model.blur="birth_date"
                               aria-invalid="{{ $errors->has('birth_date') ? 'true' : 'false' }}" aria-describedby="birth_date-error"
                               class="{{ $field('birth_date') }}">
                        @error('birth_date')
                            <p id="birth_date-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                        @else
                            @if ($birth_date && strtotime($birth_date) && $birth_date <= now()->toDateString())
                                <p class="mt-1.5 text-xs text-ink-muted">{{ \Illuminate\Support\Carbon::parse($birth_date)->age }} años</p>
                            @endif
                        @enderror
                    </div>

                    {{-- Nombre --}}
                    <div>
                        <x-input-label for="first_name" value="Nombre *" />
                        <input id="first_name" type="text" autocomplete="off" maxlength="100"
                               wire:model.blur="first_name"
                               aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}" aria-describedby="first_name-error"
                               class="{{ $field('first_name') }}">
                        @error('first_name') <p id="first_name-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Apellido --}}
                    <div>
                        <x-input-label for="last_name" value="Apellido *" />
                        <input id="last_name" type="text" autocomplete="off" maxlength="100"
                               wire:model.blur="last_name"
                               aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}" aria-describedby="last_name-error"
                               class="{{ $field('last_name') }}">
                        @error('last_name') <p id="last_name-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Género --}}
                    <div>
                        <x-input-label for="gender" value="Género *" />
                        <select id="gender" wire:model.live="gender"
                                aria-invalid="{{ $errors->has('gender') ? 'true' : 'false' }}" aria-describedby="gender-error"
                                class="{{ $field('gender') }}">
                            <option value="">Seleccioná una opción</option>
                            @foreach ($genders as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('gender') <p id="gender-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Teléfono --}}
                    <div>
                        <x-input-label for="phone">Teléfono <span class="font-normal text-ink-muted">(opcional)</span></x-input-label>
                        <input id="phone" type="tel" autocomplete="off" maxlength="50"
                               wire:model.blur="phone" placeholder="370 4 55-1234"
                               aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="phone-error"
                               class="{{ $field('phone') }}">
                        @error('phone') <p id="phone-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Domicilio --}}
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Domicilio *" />
                        <input id="address" type="text" autocomplete="off" maxlength="255"
                               wire:model.blur="address" placeholder="Calle y número"
                               aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" aria-describedby="address-error"
                               class="{{ $field('address') }}">
                        @error('address') <p id="address-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Barrio con autocompletado y búsqueda reactiva (CEN-36) --}}
                    <div class="sm:col-span-2 relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <x-input-label for="neighborhood_search" value="Barrio de residencia *" />

                        <div class="relative mt-1.5">
                            <div class="relative flex items-center">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-ink-subtle">
                                    <x-icon name="magnifying-glass" class="w-4 h-4" />
                                </span>
                                <input id="neighborhood_search"
                                       type="text"
                                       autocomplete="off"
                                       wire:model.live.debounce.250ms="neighborhoodSearch"
                                       @focus="open = true"
                                       @click="open = true"
                                       placeholder="Buscar barrio de Formosa Capital..."
                                       aria-invalid="{{ $errors->has('neighborhood_id') ? 'true' : 'false' }}"
                                       aria-describedby="neighborhood_id-error"
                                       class="pl-10 pr-20 block w-full h-11 rounded-lg shadow-sm text-ink placeholder:text-ink-subtle {{ $errors->has('neighborhood_id') ? 'border-danger focus:border-danger focus:ring-danger' : 'border-line focus:border-brand-turquesa focus:ring-brand-turquesa' }}"
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center pr-2 gap-1">
                                    @if ($neighborhood_id)
                                        <button type="button"
                                                wire:click="clearNeighborhood"
                                                @click="open = true"
                                                title="Limpiar selección"
                                                class="p-1 rounded-md text-ink-subtle hover:text-ink hover:bg-slate-100 transition-colors">
                                            <x-icon name="x-mark" class="w-4 h-4" />
                                            <span class="sr-only">Limpiar barrio</span>
                                        </button>
                                    @endif
                                    <button type="button"
                                            @click="open = !open"
                                            title="Desplegar lista"
                                            class="p-1 rounded-md text-ink-subtle hover:text-ink hover:bg-slate-100 transition-colors">
                                        <x-icon name="chevron-down" class="w-4 h-4" />
                                        <span class="sr-only">Desplegar lista</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Menú desplegable con autocompletado reactivo --}}
                            <div x-show="open"
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="absolute z-50 mt-1 w-full bg-white rounded-xl border border-line shadow-lg max-h-60 overflow-y-auto focus:outline-none divide-y divide-line/40">

                                @if ($neighborhood_id && $selectedNeighborhoodName)
                                    <div class="px-3.5 py-2 bg-brand-turquesa/10 flex items-center justify-between text-xs font-medium text-brand-petroleo">
                                        <span class="flex items-center gap-1.5">
                                            <x-icon name="map-pin" class="w-3.5 h-3.5 text-brand-turquesa" />
                                            Seleccionado: <strong class="text-ink">{{ $selectedNeighborhoodName }}</strong>
                                        </span>
                                        <span class="text-ink-muted">Formosa Capital</span>
                                    </div>
                                @endif

                                @forelse ($neighborhoods as $neighborhood)
                                    <button type="button"
                                            wire:key="nh-{{ $neighborhood->id }}"
                                            wire:click="selectNeighborhood({{ $neighborhood->id }}, '{{ addslashes($neighborhood->name) }}')"
                                            @click="open = false"
                                            class="w-full text-left px-3.5 py-2.5 text-sm flex items-center justify-between hover:bg-slate-50 focus:bg-slate-50 focus:outline-none transition-colors {{ (int)$neighborhood_id === (int)$neighborhood->id ? 'bg-brand-turquesa/10 font-medium text-brand-petroleo' : 'text-ink' }}">
                                        <span class="flex items-center gap-2">
                                            <x-icon name="map-pin" class="w-4 h-4 {{ (int)$neighborhood_id === (int)$neighborhood->id ? 'text-brand-turquesa' : 'text-ink-subtle' }}" />
                                            <span>{{ $neighborhood->name }}</span>
                                        </span>
                                        @if ((int)$neighborhood_id === (int)$neighborhood->id)
                                            <x-icon name="check" class="w-4 h-4 text-brand-turquesa" />
                                        @endif
                                    </button>
                                @empty
                                    <div class="px-4 py-3 text-sm text-ink-muted text-center">
                                        No se encontraron barrios con ese nombre en Formosa Capital.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        @error('neighborhood_id')
                            <p id="neighborhood_id-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Paso 2: Signos Vitales Cuantitativos (Obligatorios) --}}
            <div class="bg-white rounded-2xl border border-line shadow-card p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-2.5 pb-4 border-b border-line">
                    <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-petroleo flex items-center justify-center font-bold text-sm shrink-0">
                        2
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-ink">Signos Vitales Cuantitativos</h2>
                        <p class="text-xs text-ink-muted">Los 6 parámetros fisiológicos son obligatorios para determinar la prioridad clínica MTS.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    {{-- Temperatura --}}
                    <div>
                        <x-input-label for="temperature" value="Temperatura (°C) *" />
                        <div class="relative mt-1.5">
                            <input id="temperature"
                                   type="text"
                                   inputmode="decimal"
                                   autocomplete="off"
                                   x-data
                                   x-on:input.capture="$el.value = $el.value.replace(',', '.').replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')"
                                   wire:model.live.blur="temperature"
                                   placeholder="Ej: 36.8"
                                   aria-invalid="{{ $errors->has('temperature') ? 'true' : 'false' }}"
                                   aria-describedby="temperature-error"
                                   class="{{ $field('temperature', 'mt-0 pr-9') }}" />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">°C</span>
                        </div>
                        @error('temperature') <p id="temperature-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Presión Sistólica --}}
                    <div>
                        <x-input-label for="systolic_bp" value="PAS Sistólica (mmHg) *" />
                        <div class="relative mt-1.5">
                            <input id="systolic_bp"
                                   type="text"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   x-data
                                   x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                   wire:model.live.debounce.250ms="systolic_bp"
                                   placeholder="Ej: 120"
                                   aria-invalid="{{ $errors->has('systolic_bp') ? 'true' : 'false' }}"
                                   aria-describedby="systolic_bp-error"
                                   class="{{ $field('systolic_bp', 'mt-0 pr-14') }}" />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">mmHg</span>
                        </div>
                        @error('systolic_bp') <p id="systolic_bp-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Presión Diastólica --}}
                    <div>
                        <x-input-label for="diastolic_bp" value="PAD Diastólica (mmHg) *" />
                        <div class="relative mt-1.5">
                            <input id="diastolic_bp"
                                   type="text"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   x-data
                                   x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                   wire:model.live.debounce.250ms="diastolic_bp"
                                   placeholder="Ej: 80"
                                   aria-invalid="{{ $errors->has('diastolic_bp') ? 'true' : 'false' }}"
                                   aria-describedby="diastolic_bp-error"
                                   class="{{ $field('diastolic_bp', 'mt-0 pr-14') }}" />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">mmHg</span>
                        </div>
                        @error('diastolic_bp') <p id="diastolic_bp-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Frecuencia Cardíaca --}}
                    <div>
                        <x-input-label for="heart_rate" value="Frecuencia Cardíaca (lpm) *" />
                        <div class="relative mt-1.5">
                            <input id="heart_rate"
                                   type="text"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   x-data
                                   x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                   wire:model.live.debounce.250ms="heart_rate"
                                   placeholder="Ej: 75"
                                   aria-invalid="{{ $errors->has('heart_rate') ? 'true' : 'false' }}"
                                   aria-describedby="heart_rate-error"
                                   class="{{ $field('heart_rate', 'mt-0 pr-12') }}" />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">lpm</span>
                        </div>
                        @error('heart_rate') <p id="heart_rate-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Frecuencia Respiratoria --}}
                    <div>
                        <x-input-label for="respiratory_rate" value="Frecuencia Respiratoria (rpm) *" />
                        <div class="relative mt-1.5">
                            <input id="respiratory_rate"
                                   type="text"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   x-data
                                   x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                   wire:model.live.debounce.250ms="respiratory_rate"
                                   placeholder="Ej: 16"
                                   aria-invalid="{{ $errors->has('respiratory_rate') ? 'true' : 'false' }}"
                                   aria-describedby="respiratory_rate-error"
                                   class="{{ $field('respiratory_rate', 'mt-0 pr-12') }}" />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">rpm</span>
                        </div>
                        @error('respiratory_rate') <p id="respiratory_rate-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Saturación de Oxígeno (SpO2) --}}
                    <div>
                        <x-input-label for="spo2" value="Saturación O2 (SpO2) *" />
                        <div class="relative mt-1.5">
                            <input id="spo2"
                                   type="text"
                                   inputmode="numeric"
                                   autocomplete="off"
                                   x-data
                                   x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                   wire:model.live.debounce.250ms="spo2"
                                   placeholder="Ej: 98"
                                   aria-invalid="{{ $errors->has('spo2') ? 'true' : 'false' }}"
                                   aria-describedby="spo2-error"
                                   class="{{ $field('spo2', trim('mt-0 pr-9 font-bold '.(is_numeric($spo2) && (int) $spo2 < 85 ? 'text-danger' : (is_numeric($spo2) && (int) $spo2 <= 92 ? 'text-warn' : '')))) }}" />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">%</span>
                        </div>
                        @error('spo2') <p id="spo2-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Paso 3: Motivo de Consulta y Anamnesis (Obligatorio, ubicado ANTES de los discriminadores MTS) --}}
            <div class="bg-white rounded-2xl border border-line shadow-card p-5 sm:p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-4 border-b border-line">
                    <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-petroleo flex items-center justify-center font-bold text-sm shrink-0">
                        3
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-ink">Motivo de Consulta y Anamnesis</h2>
                        <p class="text-xs text-ink-muted">Sintomatología principal referida por el paciente o acompañante al ingreso.</p>
                    </div>
                </div>

                <div>
                    <x-input-label for="symptoms" value="Descripción de síntomas y motivo de consulta *" />
                    <textarea id="symptoms"
                              rows="3"
                              wire:model.live.debounce.300ms="symptoms"
                              placeholder="Describí el motivo de consulta, tiempo de evolución y síntomas asociados..."
                              aria-invalid="{{ $errors->has('symptoms') ? 'true' : 'false' }}"
                              aria-describedby="symptoms-error"
                              class="mt-1.5 block w-full rounded-lg shadow-sm text-ink placeholder:text-ink-subtle p-3 text-sm {{ $errors->has('symptoms') ? 'border-danger focus:border-danger focus:ring-danger' : 'border-line focus:border-brand-turquesa focus:ring-brand-turquesa' }}"></textarea>
                    @error('symptoms')
                        <p id="symptoms-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Paso 4: Discriminadores Clínicos Manchester (Opcionales) --}}
            <div class="bg-white rounded-2xl border border-line shadow-card p-5 sm:p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-4 border-b border-line">
                    <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-petroleo flex items-center justify-center font-bold text-sm shrink-0">
                        4
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-ink">Discriminadores Clínicos Manchester (MTS) <span class="text-xs font-normal text-ink-muted">(opcionales)</span></h2>
                        <p class="text-xs text-ink-muted">Seleccioná los hallazgos clínicos específicos observados durante la recepción.</p>
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
                                    <label wire:key="disc-{{ $itemKey }}"
                                           class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition border border-transparent hover:border-line">
                                        <input type="checkbox"
                                               wire:model.live="discriminators.{{ $itemKey }}"
                                               class="mt-0.5 rounded border-line text-brand-petroleo focus:ring-brand-turquesa" />
                                        <span class="text-xs text-ink leading-tight">{{ $itemLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Columna Derecha: Panel Sticky MTS en vivo --}}
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
                                Tiempo máximo recomendado antes de la consulta médica.
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

                    @if ($errors->any())
                        <div id="admission-save-errors" role="alert" class="p-3 rounded-xl border border-danger/30 bg-danger/10 text-danger">
                            <p class="text-xs font-bold">Revisá los campos pendientes:</p>
                            <ul class="mt-1 list-disc ps-4 text-xs space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="pt-2 space-y-2.5">
                        <x-primary-button wire:loading.attr="disabled"
                                          wire:target="save"
                                          :disabled="$activeTriageId !== null"
                                          class="w-full justify-center py-3">
                            <x-icon name="user-plus" class="w-5 h-5" wire:loading.remove wire:target="save" />
                            <svg wire:loading wire:target="save" class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" class="opacity-25" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                            <span wire:loading.remove wire:target="save">Registrar paciente</span>
                            <span wire:loading wire:target="save">Procesando ingreso...</span>
                        </x-primary-button>

                        <x-secondary-button type="button" wire:click="clear" class="w-full justify-center">
                            Limpiar formulario
                        </x-secondary-button>
                    </div>

                    <p class="text-[11px] text-center text-ink-muted leading-tight">
                        El paciente y su evaluación inicial de triage se guardan en una única operación atómica.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>
