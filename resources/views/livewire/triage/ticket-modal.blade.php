<div>
    @if ($show && $this->triage)
        @php
            $triage = $this->triage;
            $priority = $triage->final_priority;
            $wait = $triage->target_wait_minutes;
            $queuePosition = $this->queuePosition;
        @endphp

        <div class="fixed inset-0 z-50 flex items-start sm:items-center justify-center overflow-y-auto bg-brand-noche/60 p-4"
             role="dialog" aria-modal="true" aria-labelledby="ticket-title"
             x-data x-on:keydown.escape.window="$wire.close()">
            <div class="w-full max-w-sm my-8 sm:my-0">
                {{-- Ticket: lo único que se imprime (resources/css/app.css · @media print) --}}
                <article class="ticket-80mm bg-white rounded-2xl shadow-modal border border-line p-5 text-ink">
                    <header class="flex items-center justify-between gap-3 pb-4 border-b border-dashed border-line">
                        <x-centinela-logo :tagline="false" />
                        <span class="text-xs text-ink-muted text-end leading-tight">{{ config('centinela.node_name') }}</span>
                    </header>

                    <div class="py-4 text-center">
                        <p id="ticket-title" class="text-xs font-bold tracking-wider uppercase text-ink-muted">Ticket de triage</p>
                        <p class="mt-1 text-4xl font-extrabold tracking-tight font-mono">{{ \App\Livewire\Triage\PrintTicket::ticketNumber($triage->id) }}</p>
                    </div>

                    {{-- Nivel MTS: número + nombre (legible en impresión monocromática) y color oficial en pantalla --}}
                    <div class="ticket-badge {{ $priority->badgeClasses() }} rounded-2xl px-5 py-4 flex items-center gap-4"
                         data-mts-level="{{ $priority->level() }}">
                        <span class="text-5xl font-extrabold leading-none">{{ $priority->level() }}</span>
                        <div class="min-w-0">
                            <p class="text-lg font-extrabold uppercase leading-tight">{{ $priority->label() }}</p>
                            <p class="text-sm font-semibold">
                                @if ($wait === 0)
                                    Atención inmediata
                                @else
                                    Espera máxima: {{ $wait }} min
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($queuePosition !== null)
                        <div class="mt-3 rounded-xl border border-line bg-surface-soft px-3.5 py-2.5 text-center"
                             data-queue-position="{{ $queuePosition }}">
                            <p class="text-sm font-extrabold text-ink font-mono">
                                Posición estimada en espera: #{{ $queuePosition }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-ink-muted leading-tight">
                                Posición dinámica al ingreso: puede variar según urgencias clínicas entrantes.
                            </p>
                        </div>
                    @endif

                    <dl class="mt-4 divide-y divide-line text-sm">
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-muted">Paciente</dt>
                            <dd class="font-semibold text-end">{{ $triage->patient->full_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-muted">DNI</dt>
                            <dd class="font-semibold font-mono">{{ $triage->patient->dni }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-muted">Barrio</dt>
                            <dd class="font-semibold text-end">{{ $triage->patient->neighborhood?->name ?? 'Sin barrio' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-muted">Ingreso</dt>
                            <dd class="font-semibold font-mono">{{ $triage->created_at->timezone(config('centinela.timezone'))->format('d/m/Y H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-muted">Evaluó</dt>
                            <dd class="font-semibold text-end">{{ $triage->user?->name }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 pt-4 border-t border-dashed border-line text-xs text-ink-muted text-center">
                        Conservá este ticket. Te llamamos según la prioridad clínica, no por orden de llegada.
                    </p>
                </article>

                <div class="mt-4 flex gap-3 print:hidden">
                    <x-secondary-button wire:click="close" class="flex-1">Cerrar</x-secondary-button>
                    <x-primary-button type="button" x-on:click="window.print()" class="flex-1">
                        <x-icon name="printer" class="w-5 h-5" />
                        Imprimir
                    </x-primary-button>
                </div>

                {{-- CEN-39: Clinical Override desde el ticket --}}
                <button type="button" wire:click="reclassify"
                        class="mt-3 w-full inline-flex items-center justify-center gap-1.5 text-sm font-semibold text-white/90 hover:text-white underline print:hidden">
                    <x-icon name="arrows-up-down" class="w-4 h-4" />
                    Reclasificar prioridad (Override)
                </button>
            </div>
        </div>
    @endif
</div>
