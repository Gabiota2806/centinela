<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Centinela') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans text-ink antialiased bg-canvas">
        {{-- Estado de la barra lateral: `collapsed` (escritorio, persistido) y `sidebarOpen` (menú deslizable en tablet/celular). --}}
        <div
            x-data="{
                sidebarOpen: false,
                collapsed: localStorage.getItem('centinela.sidebar.collapsed') === '1',
                toggleCollapsed() {
                    this.collapsed = ! this.collapsed;
                    localStorage.setItem('centinela.sidebar.collapsed', this.collapsed ? '1' : '0');
                },
            }"
            x-on:keydown.escape.window="sidebarOpen = false"
            class="min-h-screen flex flex-col"
        >
            @include('layouts.partials.topbar')

            <div class="flex flex-1">
                @include('layouts.partials.sidebar')

                <div class="flex-1 min-w-0">
                    @isset($header)
                        <header class="bg-white border-b border-line">
                            <div class="px-4 py-5 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    <main>
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
