<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">Admisión y Triage Clínico</h1>
        <p class="mt-1 text-sm text-ink-muted">Registrá o actualizá los datos del paciente junto con sus signos vitales y motivo de consulta para clasificar su prioridad Manchester (MTS).</p>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
            <livewire:patients.create-patient />
        </div>
    </div>

    <livewire:triage.print-ticket />

    @can('access-triage')
        <livewire:triage.clinical-override-modal />
    @endcan
</x-app-layout>
