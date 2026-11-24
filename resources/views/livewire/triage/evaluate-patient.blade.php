@php
    $vitalField = fn (string $name, string $extra = '') => \Illuminate\Support\Arr::toCssClasses([
        'w-full rounded-xl border pl-3 py-2 text-sm text-ink placeholder:text-ink-subtle focus:ring-2',
        $extra => $extra !== '',
        'border-danger focus:border-danger focus:ring-danger' => $errors->has($name),
        'border-line focus:border-brand-turquesa focus:ring-brand-turquesa' => ! $errors->has($name),
    ]);
@endphp

<div class="space-y-6 scroll-mt-24"
     x-data
     x-on:triage-saved.window="$el.scrollIntoView({ block: 'start' })"
     x-on:triage-edit-started.window="document.getElementById('triage-evaluation-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
    {{-- Confirmación del último triage registrado o actualizado --}}
    @if ($savedSuccessfully)
        <div role="status" class="p-4 rounded-xl border border-ok/30 bg-ok/10 text-ink flex items-start gap-3">
            <x-icon name="check-circle" class="w-5 h-5 text-ok shrink-0 mt-0.5" />
            <div class="flex-1">
                <p class="text-sm font-semibold text-ok">{{ $savedMessage }}</p>
                <p class="text-xs text-ink-muted mt-1">La pantalla quedó lista para gestionar o evaluar al siguiente paciente.</p>
            </div>
            <div class="shrink-0 flex flex-col sm:flex-row items-end sm:items-center gap-2 sm:gap-4">
                <button
                    type="button"
                    wire:click="showTicket"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-petroleo hover:text-brand-noche underline"
                >
                    <x-icon name="printer" class="w-4 h-4" />
                    Ver ticket
                </button>
                <button
                    type="button"
                    wire:click="openOverride"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-petroleo hover:text-brand-noche underline"
                >
                    <x-icon name="arrows-up-down" class="w-4 h-4" />
                    Reclasificar prioridad
                </button>
            </div>
        </div>
    @endif

    {{-- Panel de Gestión de Triages: Buscador por DNI/nombre + Pestañas "En espera" e "Historial" --}}
    <section class="bg-white border border-line rounded-2xl shadow-card overflow-hidden" aria-label="Gestión de triages activos e historial">
        <div class="p-5 sm:p-6 border-b border-line space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-ink">Seguimiento y Gestión de Triage</h2>
                    <p class="text-xs text-ink-muted mt-0.5">
                        Consultá los pacientes en espera ordenados por prioridad MTS, editá signos vitales con auditoría o revisá el historial de atenciones.
                    </p>
                </div>

                {{-- Buscador reactivo por DNI o nombre/apellido --}}
                <div class="relative w-full lg:w-96">
                    <label for="triage-search" class="sr-only">Buscar triage por DNI o nombre del paciente</label>
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-subtle">
                        <x-icon name="magnifying-glass" class="w-4 h-4" />
                    </span>
                    <input
                        id="triage-search"
                        type="text"
                        autocomplete="off"
                        wire:model.live.debounce.300ms="searchTriage"
                        placeholder="Buscar por DNI, nombre o apellido..."
                        class="w-full h-10 pl-10 pr-9 rounded-xl border border-line text-sm text-ink placeholder:text-ink-subtle focus:ring-2 focus:ring-brand-turquesa focus:border-brand-turquesa"
                    />
                    @if ($searchTriage !== '')
                        <button
                            type="button"
                            wire:click="$set('searchTriage', '')"
                            title="Limpiar búsqueda"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-ink-subtle hover:text-ink"
                        >
                            <x-icon name="x-mark" class="w-4 h-4" />
                            <span class="sr-only">Limpiar búsqueda</span>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Pestañas "En espera" e "Historial" --}}
            <div class="flex items-center gap-2 border-b border-line -mb-5 sm:-mb-6 pt-1" role="tablist" aria-label="Vistas de triage">
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === 'waiting' ? 'true' : 'false' }}"
                    wire:click="setTab('waiting')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-bold border-b-2 transition {{ $activeTab === 'waiting' ? 'border-brand-petroleo text-brand-petroleo' : 'border-transparent text-ink-muted hover:text-ink' }}"
                >
                    <x-icon name="activity" class="w-4 h-4" />
                    <span>En espera</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-mono {{ $activeTab === 'waiting' ? 'bg-brand-50 text-brand-petroleo' : 'bg-slate-100 text-ink-muted' }}">
                        {{ $waitingCount }}
                    </span>
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === 'history' ? 'true' : 'false' }}"
                    wire:click="setTab('history')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-bold border-b-2 transition {{ $activeTab === 'history' ? 'border-brand-petroleo text-brand-petroleo' : 'border-transparent text-ink-muted hover:text-ink' }}"
                >
                    <x-icon name="clipboard-list" class="w-4 h-4" />
                    <span>Historial</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-mono {{ $activeTab === 'history' ? 'bg-brand-50 text-brand-petroleo' : 'bg-slate-100 text-ink-muted' }}">
                        {{ $historyCount }}
                    </span>
                </button>
            </div>
        </div>

        @error('editing')
            <div role="alert" class="mx-5 sm:mx-6 mt-4 p-3 rounded-xl border border-danger/40 bg-danger/10 text-danger text-xs font-semibold flex items-center gap-2">
                <x-icon name="lock-closed" class="w-4 h-4 shrink-0" />
                <span>{{ $message }}</span>
            </div>
        @enderror

        <div class="p-5 sm:p-6">
            @if ($activeTab === 'waiting')
                @if ($waitingTriages->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($waitingTriages as $item)
                            @php
                                $isPending = $item->status === \App\Enums\TriageStatus::PENDING_CONSULTATION;
                                $isCalled = $item->status === \App\Enums\TriageStatus::CALLED;
                                $position = $queuePositions[$item->id] ?? null;
                                $vitals = is_array($item->vital_signs) ? $item->vital_signs : [];
                                $isCurrentlyEditing = $editingTriageId === $item->id;
                            @endphp
                            <article
                                wire:key="waiting-triage-{{ $item->id }}"
                                class="rounded-xl border p-4 transition flex flex-col xl:flex-row xl:items-center gap-4 {{ $isCurrentlyEditing ? 'border-brand-turquesa bg-brand-50/40 ring-2 ring-brand-turquesa/30' : 'border-line bg-white hover:bg-slate-50/60' }}"
                            >
                                {{-- Posición / Estado y Nivel MTS --}}
                                <div class="flex items-center gap-3 shrink-0">
                                    @if ($isPending && $position !== null)
                                        <span
                                            data-waiting-position="{{ $position }}"
                                            class="inline-flex items-center justify-center min-w-[3rem] h-10 px-2.5 rounded-xl bg-brand-50 text-brand-petroleo border border-brand-turquesa/30 font-mono text-sm font-extrabold"
                                        >
                                            #{{ $position }}
                                        </span>
                                    @elseif ($isCalled)
                                        <span class="inline-flex items-center gap-1.5 h-10 px-3 rounded-xl bg-amber-100 text-warn border border-amber-300 text-xs font-extrabold uppercase tracking-wide">
                                            <span class="w-2 h-2 rounded-full bg-warn"></span>
                                            Llamado
                                        </span>
                                    @endif

                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-xs font-bold text-ink-muted">
                                                {{ \App\Livewire\Triage\PrintTicket::ticketNumber($item->id) }}
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $item->final_priority->badgeClasses() }}">
                                                {{ $item->final_priority->level() }} · {{ $item->final_priority->label() }}
                                            </span>
                                            @if ($item->is_override || $item->audit_logs_count > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-ink-muted border border-line">
                                                    <x-icon name="lock-closed" class="w-3 h-3" />
                                                    Auditado ({{ $item->audit_logs_count }})
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-ink-muted font-mono">
                                            Ingreso: {{ $item->created_at->timezone(config('centinela.timezone'))->format('d/m/Y H:i') }} · Espera máx: {{ $item->target_wait_minutes }} min
                                        </span>
                                    </div>
                                </div>

                                {{-- Datos del Paciente, Motivo y Signos Vitales --}}
                                <div class="flex-1 min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                        <h3 class="text-sm font-bold text-ink truncate">{{ $item->patient->full_name }}</h3>
                                        <span class="text-xs font-mono text-ink-muted">DNI {{ $item->patient->dni }}</span>
                                        <span class="text-xs text-ink-muted">{{ $item->patient->neighborhood?->name ?? 'Sin barrio' }}</span>
                                    </div>
                                    <p class="text-xs text-ink-muted line-clamp-2">{{ $item->symptoms }}</p>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-mono text-ink-muted pt-0.5">
                                        @if (isset($vitals['temperature']) && $vitals['temperature'] !== null)
                                            <span>T°: <strong class="text-ink">{{ $vitals['temperature'] }}°C</strong></span>
                                        @endif
                                        @if (isset($vitals['systolic_bp'], $vitals['diastolic_bp']) && $vitals['systolic_bp'] !== null && $vitals['diastolic_bp'] !== null)
                                            <span>PA: <strong class="text-ink">{{ $vitals['systolic_bp'] }}/{{ $vitals['diastolic_bp'] }} mmHg</strong></span>
                                        @endif
                                        @if (isset($vitals['heart_rate']) && $vitals['heart_rate'] !== null)
                                            <span>FC: <strong class="text-ink">{{ $vitals['heart_rate'] }} lpm</strong></span>
                                        @endif
                                        @if (isset($vitals['respiratory_rate']) && $vitals['respiratory_rate'] !== null)
                                            <span>FR: <strong class="text-ink">{{ $vitals['respiratory_rate'] }} rpm</strong></span>
                                        @endif
                                        @if (isset($vitals['spo2']) && $vitals['spo2'] !== null)
                                            <span>SpO2: <strong class="text-ink">{{ $vitals['spo2'] }}%</strong></span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Acciones de Fila --}}
                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                    @if ($isPending)
                                        <button
                                            type="button"
                                            wire:click="startEditing({{ $item->id }})"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-brand-turquesa/40 bg-brand-50 text-brand-petroleo hover:bg-brand-petroleo hover:text-white text-xs font-semibold transition"
                                        >
                                            <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                            Editar
                                        </button>
                                    @endif

                                    <button
                                        type="button"
                                        wire:click="openOverrideFor({{ $item->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-line bg-white hover:bg-slate-50 text-ink text-xs font-semibold transition"
                                    >
                                        <x-icon name="arrows-up-down" class="w-4 h-4 text-ink-muted" />
                                        Override
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="showTicketFor({{ $item->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-line bg-white hover:bg-slate-50 text-ink text-xs font-semibold transition"
                                    >
                                        <x-icon name="printer" class="w-4 h-4 text-ink-muted" />
                                        Ver ticket
                                    </button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center rounded-xl bg-slate-50 border border-dashed border-line text-sm text-ink-muted">
                        @if ($searchTriage !== '')
                            No se encontraron pacientes en espera para el criterio "{{ $searchTriage }}".
                        @else
                            No hay pacientes en espera de consulta en este momento.
                        @endif
                    </div>
                @endif
            @else
                @if ($historyTriages && $historyTriages->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($historyTriages as $item)
                            @php
                                $statusBadge = match ($item->status) {
                                    \App\Enums\TriageStatus::IN_CONSULTATION => 'bg-brand-50 text-brand-petroleo border-brand-turquesa/40',
                                    \App\Enums\TriageStatus::COMPLETED => 'bg-ok/10 text-ok border-ok/30',
                                    \App\Enums\TriageStatus::CANCELLED => 'bg-danger/10 text-danger border-danger/30',
                                    default => 'bg-slate-100 text-ink-muted border-line',
                                };
                                $vitals = is_array($item->vital_signs) ? $item->vital_signs : [];
                            @endphp
                            <article
                                wire:key="history-triage-{{ $item->id }}"
                                class="rounded-xl border border-line bg-white p-4 flex flex-col xl:flex-row xl:items-center gap-4"
                            >
                                <div class="flex flex-col gap-1.5 shrink-0 min-w-[14rem]">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono text-xs font-bold text-ink-muted">
                                            {{ \App\Livewire\Triage\PrintTicket::ticketNumber($item->id) }}
                                        </span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $statusBadge }}">
                                            {{ $item->status->label() }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $item->final_priority->badgeClasses() }}">
                                            {{ $item->final_priority->level() }} · {{ $item->final_priority->label() }}
                                        </span>
                                        @if ($item->audit_logs_count > 0)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-ink-muted border border-line">
                                                <x-icon name="lock-closed" class="w-3 h-3" />
                                                Auditado ({{ $item->audit_logs_count }})
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-[11px] text-ink-muted font-mono">
                                        {{ $item->updated_at->timezone(config('centinela.timezone'))->format('d/m/Y H:i') }} · {{ $item->user?->name ?? 'Sistema' }}
                                    </span>
                                </div>

                                <div class="flex-1 min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                        <h3 class="text-sm font-bold text-ink truncate">{{ $item->patient->full_name }}</h3>
                                        <span class="text-xs font-mono text-ink-muted">DNI {{ $item->patient->dni }}</span>
                                        <span class="text-xs text-ink-muted">{{ $item->patient->neighborhood?->name ?? 'Sin barrio' }}</span>
                                    </div>
                                    <p class="text-xs text-ink-muted line-clamp-2">{{ $item->symptoms }}</p>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-mono text-ink-muted pt-0.5">
                                        @if (isset($vitals['temperature']) && $vitals['temperature'] !== null)
                                            <span>T°: <strong class="text-ink">{{ $vitals['temperature'] }}°C</strong></span>
                                        @endif
                                        @if (isset($vitals['spo2']) && $vitals['spo2'] !== null)
                                            <span>SpO2: <strong class="text-ink">{{ $vitals['spo2'] }}%</strong></span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        wire:click="showTicketFor({{ $item->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-line bg-white hover:bg-slate-50 text-ink text-xs font-semibold transition"
                                    >
                                        <x-icon name="printer" class="w-4 h-4 text-ink-muted" />
                                        Ver ticket
                                    </button>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($historyTriages->hasPages())
                        <div class="mt-4">
                            {{ $historyTriages->links() }}
                        </div>
                    @endif
                @else
                    <div class="p-8 text-center rounded-xl bg-slate-50 border border-dashed border-line text-sm text-ink-muted">
                        @if ($searchTriage !== '')
                            No se encontraron registros históricos para el criterio "{{ $searchTriage }}".
                        @else
                            Aún no existen atenciones en consulta, completadas o canceladas.
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </section>

    {{-- Formulario de Edición Auditada / Evaluación Clínica MTS --}}
    <div id="triage-evaluation-form" class="space-y-6 scroll-mt-24">
        @if ($editingTriageId !== null)
            <div role="status" class="p-4 rounded-2xl border border-brand-turquesa/50 bg-brand-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-extrabold text-brand-petroleo">
                            Editando triage #{{ $editingTicketNumber }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $editingCurrentPriorityBadgeClasses }}">
                            Actual: {{ $editingCurrentPriorityLabel }}
                        </span>
                    </div>
                    @if ($editingCurrentPriorityValue !== null && $editingCurrentPriorityValue !== $priorityValue)
                        <p class="text-xs font-semibold text-warn">
                            El recálculo MTS cambiará la prioridad final a {{ $priorityLabel }} y registrará una fila inmutable en auditoría clínica.
                        </p>
                    @else
                        <p class="text-xs text-ink-muted">
                            Modificá los signos vitales, motivo de consulta o discriminadores. Si la prioridad final cambia, quedará auditada automáticamente.
                        </p>
                    @endif
                </div>
                <button
                    type="button"
                    wire:click="cancelEditing"
                    class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-line bg-white hover:bg-slate-50 text-xs font-bold text-ink shrink-0 transition"
                >
                    <x-icon name="x-mark" class="w-4 h-4" />
                    Cancelar edición
                </button>
            </div>
        @endif

        {{-- Paso 1: Selección / Ficha del Paciente --}}
        <div class="bg-white border border-line rounded-2xl shadow-card p-5 sm:p-6">
            <div class="flex items-center justify-between pb-4 border-b border-line mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-petroleo flex items-center justify-center font-bold text-sm">
                        1
                    </div>
                    <h2 class="text-lg font-bold text-ink">
                        {{ $editingTriageId !== null ? 'Paciente en Re-evaluación' : 'Identificación del Paciente' }}
                    </h2>
                </div>
                @if ($patient && $editingTriageId === null)
                    <button
                        type="button"
                        wire:click="deselectPatient"
                        class="text-xs font-semibold text-ink-muted hover:text-ink inline-flex items-center gap-1 px-2.5 py-1.5 border border-line rounded-lg hover:bg-slate-50 transition"
                    >
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
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
                        <span class="text-sm font-medium text-ink font-mono">{{ $patient->dni }}</span>
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
                {{-- Buscador y Selección Rápida para Evaluación Directa --}}
                <div class="space-y-4">
                    <div class="relative">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="searchPatient"
                            placeholder="Buscar paciente por DNI, nombre o apellido para nueva evaluación directa..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-line focus:ring-2 focus:ring-brand-turquesa focus:border-brand-turquesa text-sm"
                        />
                        <svg class="w-5 h-5 text-ink-muted absolute left-3 top-3 pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>

                    @if (trim($searchPatient) !== '')
                        @if (count($availablePatients) > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($availablePatients as $item)
                                    <div
                                        wire:key="patient-{{ $item->id }}"
                                        wire:click="selectPatient({{ $item->id }})"
                                        class="p-3 rounded-xl border border-line bg-white hover:border-brand-turquesa hover:bg-slate-50 cursor-pointer transition flex flex-col justify-between"
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
                                        <div class="mt-3 flex items-center justify-end text-xs font-semibold text-brand-petroleo gap-1">
                                            <span>Seleccionar para triage</span>
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
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
                    @else
                        <div class="p-4 rounded-xl bg-slate-50 border border-dashed border-line text-xs text-ink-muted">
                            Para modificar un triage pendiente usá el botón <strong>Editar</strong> en la pestaña <strong>En espera</strong>, o buscá aquí un paciente por DNI/nombre para registrar una evaluación directa.
                        </div>
                    @endif

                    @error('patientId')
                        <p id="patient-id-error" class="text-xs font-semibold text-danger mt-1">{{ $message }}</p>
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
                        <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-petroleo flex items-center justify-center font-bold text-sm">
                            2
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-ink">Signos Vitales Cuantitativos</h2>
                            <p class="text-xs text-ink-muted">Valores fisiológicos medidos durante la admisión clínica o re-evaluación.</p>
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
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    x-data
                                    x-on:input.capture="$el.value = $el.value.replace(',', '.').replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')"
                                    wire:model.live.blur="temperature"
                                    placeholder="Ej: 36.8"
                                    aria-invalid="{{ $errors->has('temperature') ? 'true' : 'false' }}"
                                    aria-describedby="vital-temp-error"
                                    class="{{ $vitalField('temperature', 'pr-9') }}"
                                />
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">°C</span>
                            </div>
                            @error('temperature') <p id="vital-temp-error" class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Presión Sistólica --}}
                        <div>
                            <label for="vital-pas" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                                PAS Sistólica (mmHg)
                            </label>
                            <div class="relative">
                                <input
                                    id="vital-pas"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    x-data
                                    x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                    wire:model.live.debounce.250ms="systolic_bp"
                                    placeholder="Ej: 120"
                                    aria-invalid="{{ $errors->has('systolic_bp') ? 'true' : 'false' }}"
                                    aria-describedby="vital-pas-error"
                                    class="{{ $vitalField('systolic_bp', 'pr-14') }}"
                                />
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">mmHg</span>
                            </div>
                            @error('systolic_bp') <p id="vital-pas-error" class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Presión Diastólica --}}
                        <div>
                            <label for="vital-pad" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                                PAD Diastólica (mmHg)
                            </label>
                            <div class="relative">
                                <input
                                    id="vital-pad"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    x-data
                                    x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                    wire:model.live.debounce.250ms="diastolic_bp"
                                    placeholder="Ej: 80"
                                    aria-invalid="{{ $errors->has('diastolic_bp') ? 'true' : 'false' }}"
                                    aria-describedby="vital-pad-error"
                                    class="{{ $vitalField('diastolic_bp', 'pr-14') }}"
                                />
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">mmHg</span>
                            </div>
                            @error('diastolic_bp') <p id="vital-pad-error" class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Frecuencia Cardíaca --}}
                        <div>
                            <label for="vital-hr" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                                Frecuencia Cardíaca (lpm)
                            </label>
                            <div class="relative">
                                <input
                                    id="vital-hr"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    x-data
                                    x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                    wire:model.live.debounce.250ms="heart_rate"
                                    placeholder="Ej: 75"
                                    aria-invalid="{{ $errors->has('heart_rate') ? 'true' : 'false' }}"
                                    aria-describedby="vital-hr-error"
                                    class="{{ $vitalField('heart_rate', 'pr-12') }}"
                                />
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">lpm</span>
                            </div>
                            @error('heart_rate') <p id="vital-hr-error" class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Frecuencia Respiratoria --}}
                        <div>
                            <label for="vital-rr" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                                Frecuencia Respiratoria (rpm)
                            </label>
                            <div class="relative">
                                <input
                                    id="vital-rr"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    x-data
                                    x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                    wire:model.live.debounce.250ms="respiratory_rate"
                                    placeholder="Ej: 16"
                                    aria-invalid="{{ $errors->has('respiratory_rate') ? 'true' : 'false' }}"
                                    aria-describedby="vital-rr-error"
                                    class="{{ $vitalField('respiratory_rate', 'pr-12') }}"
                                />
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">rpm</span>
                            </div>
                            @error('respiratory_rate') <p id="vital-rr-error" class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Saturación de Oxígeno (SpO2) --}}
                        <div>
                            <label for="vital-spo2" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1">
                                Saturación O2 (SpO2)
                            </label>
                            <div class="relative">
                                <input
                                    id="vital-spo2"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    x-data
                                    x-on:input.capture="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                    wire:model.live.debounce.250ms="spo2"
                                    placeholder="Ej: 98"
                                    aria-invalid="{{ $errors->has('spo2') ? 'true' : 'false' }}"
                                    aria-describedby="vital-spo2-error"
                                    class="{{ $vitalField('spo2', trim('pr-9 font-bold '.($spo2 !== null && $spo2 < 85 ? 'text-danger' : ($spo2 !== null && $spo2 <= 92 ? 'text-warn' : '')))) }}"
                                />
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">%</span>
                            </div>
                            @error('spo2') <p id="vital-spo2-error" class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Paso 3: Motivo de Consulta y Síntomas --}}
                <div class="bg-white border border-line rounded-2xl shadow-card p-5 sm:p-6">
                    <div class="flex items-center gap-2 pb-4 border-b border-line mb-4">
                        <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-petroleo flex items-center justify-center font-bold text-sm">
                            3
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
                            aria-invalid="{{ $errors->has('symptoms') ? 'true' : 'false' }}"
                            aria-describedby="triage-symptoms-error"
                            class="w-full rounded-xl border p-3 text-sm text-ink placeholder:text-ink-subtle focus:ring-2 {{ $errors->has('symptoms') ? 'border-danger focus:border-danger focus:ring-danger' : 'border-line focus:border-brand-turquesa focus:ring-brand-turquesa' }}"
                        ></textarea>
                        @error('symptoms')
                            <p id="triage-symptoms-error" class="text-xs text-danger mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Paso 4: Discriminadores Clínicos Manchester --}}
                <div class="bg-white border border-line rounded-2xl shadow-card p-5 sm:p-6">
                    <div class="flex items-center gap-2 pb-4 border-b border-line mb-4">
                        <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-petroleo flex items-center justify-center font-bold text-sm">
                            4
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
                                                class="mt-0.5 rounded border-line text-brand-petroleo focus:ring-brand-turquesa"
                                            />
                                            <span class="text-xs text-ink leading-tight">{{ $itemLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
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

                        {{-- Errores de validación visibles junto al botón --}}
                        @if ($errors->any())
                            <div id="triage-save-errors" role="alert" class="p-3 rounded-xl border border-danger/30 bg-danger/10 text-danger">
                                <p class="text-xs font-bold">
                                    {{ $editingTriageId !== null ? 'No se pudieron guardar los cambios del triage:' : 'No se pudo registrar el triage:' }}
                                </p>
                                <ul class="mt-1 list-disc ps-4 text-xs space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Botón de Acción Principal --}}
                        <div class="pt-2 space-y-2">
                            <button
                                type="button"
                                wire:click="save"
                                wire:loading.attr="disabled"
                                wire:target="save"
                                class="w-full py-3 px-4 rounded-xl bg-brand-petroleo hover:bg-brand-noche text-white font-bold text-sm shadow-sm transition flex items-center justify-center gap-2 disabled:opacity-50"
                            >
                                <svg wire:loading.remove wire:target="save" class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <svg wire:loading wire:target="save" class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="save">{{ $editingTriageId !== null ? 'Guardar cambios del triage' : 'Registrar Triage Clínico' }}</span>
                                <span wire:loading wire:target="save">Procesando Triage...</span>
                            </button>

                            @if ($editingTriageId !== null)
                                <button
                                    type="button"
                                    wire:click="cancelEditing"
                                    class="w-full py-2.5 px-4 rounded-xl border border-line bg-white hover:bg-slate-50 text-ink font-semibold text-xs transition"
                                >
                                    Cancelar edición
                                </button>
                            @endif
                        </div>

                        <p class="text-[11px] text-center text-ink-muted leading-tight">
                            La clasificación determinística es trazable y queda respaldada en el historial clínico del paciente.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
