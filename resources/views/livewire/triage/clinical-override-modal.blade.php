<div>
    @if ($show && $this->triage)
        @php
            $triage = $this->triage;
            $current = $triage->final_priority;
        @endphp

        <div class="fixed inset-0 z-50 flex items-start sm:items-center justify-center overflow-y-auto bg-brand-noche/60 p-4"
             role="dialog" aria-modal="true" aria-labelledby="override-title"
             x-data x-on:keydown.escape.window="$wire.close()">
            <form wire:submit="save" class="w-full max-w-lg my-8 sm:my-0 bg-white rounded-2xl shadow-modal border border-line">
                <header class="flex items-start justify-between gap-3 p-5 border-b border-line">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-10 h-10 rounded-xl bg-brand-50 text-brand-petroleo flex items-center justify-center">
                            <x-icon name="arrows-up-down" class="w-5 h-5" />
                        </span>
                        <div>
                            <h2 id="override-title" class="text-lg font-extrabold text-ink leading-tight">Reclasificar prioridad (Clinical Override)</h2>
                            <p class="text-xs text-ink-muted mt-0.5">
                                {{ $triage->patient->full_name }} · DNI {{ $triage->patient->dni }}
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="close" class="p-1 rounded-lg text-ink-muted hover:text-ink hover:bg-canvas" aria-label="Cerrar">
                        <x-icon name="x-mark" class="w-5 h-5" />
                    </button>
                </header>

                <div class="p-5 space-y-5">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-ink-muted">Prioridad actual:</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold {{ $current->badgeClasses() }}">
                            {{ $current->level() }} · {{ $current->label() }}
                        </span>
                    </div>

                    {{-- Selector de nuevo nivel MTS (5 colores estandarizados) --}}
                    <fieldset>
                        <legend class="text-xs font-bold uppercase tracking-wider text-ink-muted mb-2">Nuevo nivel MTS</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-5 gap-2">
                            @foreach ($priorities as $priority)
                                @php $isCurrent = $priority === $current; @endphp
                                <label data-mts-option="{{ $priority->value }}"
                                       @class([
                                           'relative flex sm:flex-col items-center gap-3 sm:gap-1 rounded-xl px-3 py-2.5 sm:py-3 text-center transition',
                                           $priority->badgeClasses(),
                                           'cursor-pointer hover:brightness-95' => ! $isCurrent,
                                           'opacity-40 cursor-not-allowed' => $isCurrent,
                                           'ring-4 ring-offset-2 ring-brand-noche' => $newPriority === $priority->value,
                                       ])>
                                    <input type="radio" class="sr-only" name="override-priority"
                                           wire:model.live="newPriority" value="{{ $priority->value }}"
                                           @disabled($isCurrent)>
                                    <span class="text-2xl font-extrabold leading-none">{{ $priority->level() }}</span>
                                    <span class="text-xs font-bold leading-tight text-start sm:text-center">{{ $priority->label() }}</span>
                                    @if ($newPriority === $priority->value)
                                        <x-icon name="check-circle" class="w-5 h-5 ms-auto sm:absolute sm:top-1 sm:right-1" />
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        @error('newPriority') <p class="text-xs font-semibold text-red-600 mt-2">{{ $message }}</p> @enderror
                    </fieldset>

                    {{-- Emergencia crítica: guardar en un clic sin redactar --}}
                    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer {{ $deferred ? 'border-red-300 bg-red-50' : 'border-line bg-canvas' }}">
                        <input type="checkbox" wire:model.live="deferred" class="mt-0.5 rounded border-line text-red-600 focus:ring-red-500">
                        <span>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-ink">
                                <x-icon name="bolt" class="w-4 h-4 text-red-600" />
                                Justificación diferida (emergencia crítica)
                            </span>
                            <span class="block text-xs text-ink-muted mt-0.5">
                                Guarda el nuevo nivel al instante (paro, shock). La auditoría queda con el motivo pendiente de redacción.
                            </span>
                        </span>
                    </label>

                    @unless ($deferred)
                        <div>
                            <label for="override-reason" class="text-xs font-bold uppercase tracking-wider text-ink-muted">Justificación clínica *</label>
                            <textarea id="override-reason" rows="3" wire:model="reason"
                                      placeholder="Motivo clínico de la reclasificación (mínimo 10 caracteres)"
                                      class="mt-1 w-full rounded-xl border border-line p-3 text-sm focus:ring-2 focus:ring-brand-turquesa"></textarea>
                            @error('reason') <p class="text-xs font-semibold text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endunless

                    <p class="flex items-start gap-1.5 text-[11px] text-ink-muted">
                        <x-icon name="lock-closed" class="w-3.5 h-3.5 shrink-0 mt-px" />
                        La reclasificación queda registrada en la bitácora inmutable de auditoría clínica con tu usuario, la prioridad original y la nueva.
                    </p>
                </div>

                <footer class="flex gap-3 p-5 border-t border-line">
                    <x-secondary-button type="button" wire:click="close" class="flex-1">Cancelar</x-secondary-button>
                    <x-primary-button type="submit" class="flex-1" wire:loading.attr="disabled" wire:target="save">
                        <x-icon name="arrows-up-down" class="w-5 h-5" />
                        Reclasificar
                    </x-primary-button>
                </footer>
            </form>
        </div>
    @endif
</div>
