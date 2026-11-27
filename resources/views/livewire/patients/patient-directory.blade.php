<div class="space-y-6">
    <section class="bg-white border border-line rounded-2xl shadow-card overflow-hidden" aria-label="Padrón local de pacientes">
        <div class="p-5 sm:p-6 border-b border-line flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-lg font-bold text-ink">Pacientes registrados</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-brand-50 text-brand-petroleo border border-brand-turquesa/30">
                        {{ $patients->total() }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-ink-muted">
                    Buscá por DNI, nombre o apellido para revisar sus atenciones e iniciar un nuevo ingreso de admisión y triage.
                </p>
            </div>

            {{-- Buscador reactivo por DNI o nombre --}}
            <div class="relative w-full lg:w-96">
                <label for="patient-directory-search" class="sr-only">Buscar paciente por DNI, nombre o apellido</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-subtle">
                    <x-icon name="magnifying-glass" class="w-4 h-4" />
                </span>
                <input
                    id="patient-directory-search"
                    type="text"
                    autocomplete="off"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por DNI, nombre o apellido..."
                    class="w-full h-10 pl-10 pr-9 rounded-xl border border-line text-sm text-ink placeholder:text-ink-subtle focus:ring-2 focus:ring-brand-turquesa focus:border-brand-turquesa"
                />
                @if ($hasSearch)
                    <button
                        type="button"
                        wire:click="clearSearch"
                        title="Limpiar búsqueda"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-ink-subtle hover:text-ink"
                    >
                        <x-icon name="x-mark" class="w-4 h-4" />
                        <span class="sr-only">Limpiar búsqueda</span>
                    </button>
                @endif
            </div>
        </div>

        @if ($patients->isEmpty())
            <div class="p-10 text-center">
                <div class="mx-auto w-12 h-12 rounded-2xl bg-brand-50 text-brand-petroleo flex items-center justify-center">
                    <x-icon name="{{ $hasSearch ? 'magnifying-glass' : 'clipboard-list' }}" class="w-6 h-6" />
                </div>

                @if ($hasSearch)
                    <h3 class="mt-4 text-base font-bold text-ink">No se encontraron pacientes</h3>
                    <p class="mt-1 text-sm text-ink-muted max-w-md mx-auto">
                        No hay registros que coincidan con «{{ $search }}». Podés limpiar el filtro o registrar al paciente directamente.
                    </p>
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                        <x-secondary-button type="button" wire:click="clearSearch">
                            Limpiar búsqueda
                        </x-secondary-button>
                        <a href="{{ route('patients.create') }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-petroleo text-white text-sm font-semibold hover:bg-brand-noche transition">
                            <x-icon name="user-plus" class="w-4 h-4" />
                            <span>Registrar nuevo paciente</span>
                        </a>
                    </div>
                @else
                    <h3 class="mt-4 text-base font-bold text-ink">Todavía no hay pacientes registrados</h3>
                    <p class="mt-1 text-sm text-ink-muted max-w-md mx-auto">
                        Iniciá la primera admisión para cargar los datos demográficos, signos vitales y prioridad Manchester (MTS).
                    </p>
                    <div class="mt-5">
                        <a href="{{ route('patients.create') }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-petroleo text-white text-sm font-semibold hover:bg-brand-noche transition">
                            <x-icon name="user-plus" class="w-4 h-4" />
                            <span>Registrar primer paciente</span>
                        </a>
                    </div>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-canvas/70 text-left text-xs font-bold uppercase tracking-wider text-ink-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3.5">Paciente</th>
                            <th scope="col" class="px-5 py-3.5">DNI</th>
                            <th scope="col" class="px-5 py-3.5">Barrio</th>
                            <th scope="col" class="px-5 py-3.5">Atenciones</th>
                            <th scope="col" class="px-5 py-3.5 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-white">
                        @foreach ($patients as $patient)
                            <tr wire:key="patient-{{ $patient->id }}" class="hover:bg-canvas/40 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-bold text-ink">{{ $patient->full_name }}</span>
                                        @if ($patient->has_active_triage)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                                                <x-icon name="clock" class="w-3.5 h-3.5" />
                                                En atención
                                            </span>
                                        @endif
                                    </div>
                                    @if ($patient->phone)
                                        <p class="mt-0.5 text-xs text-ink-muted font-mono">{{ $patient->phone }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4 font-mono font-semibold text-ink whitespace-nowrap">
                                    {{ $patient->dni }}
                                </td>
                                <td class="px-5 py-4 text-ink whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="map-pin" class="w-4 h-4 text-ink-subtle" />
                                        <span>{{ $patient->neighborhood?->name ?? 'Sin barrio' }}</span>
                                    </span>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-ink">
                                        {{ $patient->triages_count }} {{ $patient->triages_count === 1 ? 'atención' : 'atenciones' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <a href="{{ route('patients.create', ['dni' => $patient->dni]) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-brand-turquesa/40 bg-brand-50 text-brand-petroleo hover:bg-brand-petroleo hover:text-white transition">
                                        <span>{{ $patient->has_active_triage ? 'Ver ingreso' : 'Nueva atención' }}</span>
                                        <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($patients->hasPages())
                <div class="px-5 py-4 border-t border-line bg-canvas/30">
                    {{ $patients->links() }}
                </div>
            @endif
        @endif
    </section>
</div>
