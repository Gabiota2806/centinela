<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-ink">Clasificación de Triage (Sistema Manchester)</h1>
                <p class="text-xs text-ink-muted mt-1">Evaluación clínica y categorización determinística de pacientes en CAPS.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Módulo MTS Activo
                </span>
            </div>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <livewire:triage.evaluate-patient />
    </div>

    {{-- CEN-37: ticket digital del triage registrado --}}
    <livewire:triage.print-ticket />

    {{-- CEN-39: Clinical Override con justificación diferida --}}
    <livewire:triage.clinical-override-modal />

</x-app-layout>
