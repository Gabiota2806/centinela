<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-ink">Consultorio</h1>
                <p class="text-xs text-ink-muted mt-1">Ficha clínica del paciente y cierre de la atención con diagnóstico presuntivo.</p>
            </div>
            <a href="{{ route('consultations.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-petroleo hover:underline">
                <x-icon name="arrow-uturn-left" class="w-4 h-4" /> Cola de atención
            </a>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        {{-- CEN-47: consultorio y cierre diagnóstico --}}
        <livewire:doctor.consultation-room :triage="$triage" />
    </div>
</x-app-layout>
