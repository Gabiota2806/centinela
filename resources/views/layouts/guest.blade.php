<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Centinela') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="min-h-screen flex">
            {{-- Panel de marca: solo en escritorio (Figma · 01 Login) --}}
            <section data-testid="brand-panel" class="hidden lg:flex lg:w-[44%] xl:w-[40%] flex-col p-12 xl:p-16 text-white bg-gradient-to-br from-brand-noche to-brand-petroleo">
                <div class="w-20 h-20 rounded-2xl bg-brand-noche/60 grid place-items-center">
                    <x-application-logo negative class="w-16 h-16" />
                </div>

                <h1 class="mt-6 text-5xl font-extrabold tracking-tight">Centinela</h1>
                <p class="mt-3 text-lg text-white/80 max-w-md">
                    Triage digital y alerta temprana epidemiológica para los Centros de Atención Primaria de Formosa Capital.
                </p>

                <ul class="mt-auto space-y-3 text-sm">
                    <li class="flex items-center gap-3">
                        <x-icon name="wifi-off" class="w-5 h-5 text-brand-50" />
                        Funciona sin internet: todo queda en el servidor de este centro.
                    </li>
                    <li class="flex items-center gap-3">
                        <x-icon name="lock-closed" class="w-5 h-5 text-brand-50" />
                        Los datos personales nunca salen del CAPS (Ley 25.326).
                    </li>
                    <li class="flex items-center gap-3">
                        <x-icon name="activity" class="w-5 h-5 text-brand-50" />
                        Clasificación Manchester de 5 niveles con criterio clínico final.
                    </li>
                </ul>
            </section>

            {{-- Contenido --}}
            <main class="flex-1 bg-canvas grid place-items-center px-4 py-10 sm:px-6">
                <div class="w-full max-w-md">
                    <a href="/" class="lg:hidden flex justify-center mb-6">
                        <x-centinela-logo />
                    </a>

                    <div data-testid="guest-card" class="bg-white rounded-2xl border border-line shadow-card px-6 py-8 sm:px-8">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
