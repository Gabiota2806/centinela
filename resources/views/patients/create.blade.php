<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">Nuevo paciente</h1>
        <p class="mt-1 text-sm text-ink-muted">Registrá los datos del paciente para derivarlo a triage.</p>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="max-w-3xl">
            <livewire:patients.create-patient />
        </div>
    </div>
</x-app-layout>
