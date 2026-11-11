<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">{{ $title }}</h1>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="max-w-2xl bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8">
            <p class="text-xs font-bold tracking-wider text-brand-turquesa uppercase">Módulo en construcción · {{ $sprint }}</p>
            <h2 class="mt-2 text-xl font-bold text-ink">Hola, {{ Auth::user()->name }}</h2>
            <p class="mt-2 text-sm text-ink-muted">
                Ingresaste como <span class="font-semibold text-ink">{{ Auth::user()->role?->label() }}</span>.
                Esta pantalla se completa en el {{ $sprint }}.
            </p>
        </div>
    </div>
</x-app-layout>
