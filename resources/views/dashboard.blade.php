<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-ink">Panel Epidemiológico y Operativo</h1>
                <p class="text-xs text-ink-muted mt-1">
                    Monitoreo territorial del nodo {{ config('centinela.node_name') }} en Formosa Capital.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-brand-50 text-brand-petroleo border border-line">
                    <span class="w-2 h-2 rounded-full bg-brand-turquesa"></span>
                    Nodo Edge Activo
                </span>
            </div>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        @can('view-epidemiology')
            {{-- Sección reservada para el módulo de Alertas Epidemiológicas --}}

            {{-- Visor geoespacial Leaflet.js (Formosa Capital) --}}
            <x-leaflet-map />

            {{-- Sección reservada para el modal de detalle por barrio (evento open-neighborhood-detail) --}}
        @else
            <div class="bg-white border border-line rounded-2xl shadow-card p-6">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-petroleo shrink-0">
                        <x-icon name="home" class="w-5 h-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-ink">Panel de inicio</h2>
                        <p class="text-sm text-ink-muted mt-1">
                            Accedé a los módulos operativos asignados a tu rol desde la barra lateral de navegación.
                        </p>
                    </div>
                </div>
            </div>
        @endcan
    </div>
</x-app-layout>
