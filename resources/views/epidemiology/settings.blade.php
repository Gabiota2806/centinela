<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-ink">Configuración Epidemiológica</h1>
                <p class="mt-1 text-sm text-ink-muted">
                    Ajustá los umbrales ambientales y clínicos de alerta temprana para Formosa Capital.
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-petroleo">
                <x-icon name="shield-check" class="w-4 h-4 text-brand-turquesa" />
                Acceso exclusivo Dirección de CAPS
            </span>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
            <livewire:admin.epidemiology-settings />
        </div>
    </div>
</x-app-layout>
