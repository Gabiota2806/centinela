<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-ink">Padrón de Pacientes</h1>
                <p class="mt-1 text-sm text-ink-muted">Consultá el padrón local del CAPS por DNI o nombre e iniciá una nueva atención con precarga directa.</p>
            </div>
            <a href="{{ route('patients.create') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-brand-petroleo text-white text-sm font-semibold shadow-sm hover:bg-brand-noche focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa transition shrink-0">
                <x-icon name="user-plus" class="w-4 h-4" />
                <span>Nuevo paciente</span>
            </a>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
            <livewire:patients.patient-directory />
        </div>
    </div>
</x-app-layout>
