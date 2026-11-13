@php
    // Estilo común de campos (docs/specs/ui/spec.md). El borde cambia a `danger` si el campo tiene error.
    $field = fn (string $name) => \Illuminate\Support\Arr::toCssClasses([
        'mt-1.5 block w-full h-11 rounded-lg shadow-sm text-ink placeholder:text-ink-subtle',
        'border-danger focus:border-danger focus:ring-danger' => $errors->has($name),
        'border-line focus:border-brand-turquesa focus:ring-brand-turquesa' => ! $errors->has($name),
    ]);
@endphp

<div class="space-y-5">
    @if ($createdPatientId)
        {{-- Alta exitosa: aviso y acceso directo al triage (Gherkin CEN-34) --}}
        <div role="status" class="flex flex-col sm:flex-row sm:items-center gap-4 rounded-2xl border border-ok/30 bg-ok/10 px-5 py-4">
            <x-icon name="check-circle" class="w-6 h-6 text-ok" />
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-ok">Paciente registrado</p>
                <p class="text-sm text-ink">{{ $createdPatientName }} · DNI {{ $createdPatientDni }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button wire:click="newPatient">Registrar otro</x-secondary-button>
                <a href="{{ route('triage.index', ['patient' => $createdPatientId]) }}"
                   class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-brand-petroleo text-sm font-semibold text-white hover:bg-brand-noche focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa focus-visible:ring-offset-2">
                    Iniciar triage
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
            </div>
        </div>
    @endif

    <form wire:submit="save" novalidate class="bg-white rounded-2xl border border-line shadow-card p-5 sm:p-6 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-5">
            {{-- DNI --}}
            <div>
                <x-input-label for="dni" value="DNI" />
                <input id="dni" type="text" inputmode="numeric" autocomplete="off" maxlength="20"
                       wire:model.live.debounce.500ms="dni" placeholder="Solo números, sin puntos"
                       aria-invalid="{{ $errors->has('dni') ? 'true' : 'false' }}" aria-describedby="dni-error"
                       class="{{ $field('dni') }}" autofocus>
                @error('dni') <p id="dni-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Fecha de nacimiento --}}
            <div>
                <x-input-label for="birth_date" value="Fecha de nacimiento" />
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
                <x-input-label for="first_name" value="Nombre" />
                <input id="first_name" type="text" autocomplete="off" maxlength="100"
                       wire:model.blur="first_name"
                       aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}" aria-describedby="first_name-error"
                       class="{{ $field('first_name') }}">
                @error('first_name') <p id="first_name-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Apellido --}}
            <div>
                <x-input-label for="last_name" value="Apellido" />
                <input id="last_name" type="text" autocomplete="off" maxlength="100"
                       wire:model.blur="last_name"
                       aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}" aria-describedby="last_name-error"
                       class="{{ $field('last_name') }}">
                @error('last_name') <p id="last_name-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Género --}}
            <div>
                <x-input-label for="gender" value="Género" />
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
                <x-input-label for="address" value="Domicilio" />
                <input id="address" type="text" autocomplete="off" maxlength="255"
                       wire:model.blur="address" placeholder="Calle y número"
                       aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" aria-describedby="address-error"
                       class="{{ $field('address') }}">
                @error('address') <p id="address-error" class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            {{-- Barrio con autocompletado y búsqueda reactiva (CEN-36) --}}
            <div class="sm:col-span-2 relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <x-input-label for="neighborhood_search" value="Barrio de residencia" />

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

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2 border-t border-line">
            <x-secondary-button wire:click="clear" class="sm:w-auto">Limpiar</x-secondary-button>
            <x-primary-button wire:loading.attr="disabled" wire:target="save" class="sm:w-auto">
                <x-icon name="user-plus" class="w-5 h-5" wire:loading.remove wire:target="save" />
                <svg wire:loading wire:target="save" class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" class="opacity-25" />
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                Registrar paciente
            </x-primary-button>
        </div>
    </form>
</div>
