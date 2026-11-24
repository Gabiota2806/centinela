<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-ink">Cola de atención médica</h1>
                <p class="text-xs text-ink-muted mt-1">Pacientes ordenados por prioridad Manchester (Rojo a Azul) y por orden de llegada.</p>
            </div>
            <div class="flex items-center gap-1.5">
                @foreach (\App\Enums\TriagePriority::cases() as $priority)
                    <span title="{{ $priority->label() }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-xs font-extrabold {{ $priority->badgeClasses() }}">{{ $priority->level() }}</span>
                @endforeach
            </div>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8">
        {{-- CEN-42: panel médico --}}
        <livewire:doctor.medical-queue />
    </div>

    {{-- CEN-39: Clinical Override reutilizado desde la cola (RF-CONS-04) --}}
    <livewire:triage.clinical-override-modal />
</x-app-layout>
