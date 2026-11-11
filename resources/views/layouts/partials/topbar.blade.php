@php
    $user = Auth::user();
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp

<header class="sticky top-0 z-30 h-16 bg-white border-b border-line flex items-center gap-3 px-4 sm:px-6">
    <button type="button"
            data-testid="sidebar-open"
            class="lg:hidden -ms-2 p-2 rounded-lg text-ink-muted hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa"
            x-on:click="sidebarOpen = true"
            aria-label="Abrir menú"
            :aria-expanded="sidebarOpen.toString()">
        <x-icon name="bars-3" class="w-6 h-6" />
    </button>

    <a href="{{ route('dashboard') }}" class="shrink-0">
        <x-centinela-logo class="hidden sm:inline-flex" />
        <x-application-logo class="sm:hidden w-9 h-9" />
    </a>

    <div class="hidden md:flex items-center gap-2 ps-4 ms-1 border-s border-line text-sm font-semibold text-ink min-w-0">
        <x-icon name="map-pin" class="w-5 h-5 text-ink-muted" />
        <span class="truncate">{{ config('centinela.node_name') }}</span>
    </div>

    <div class="flex-1"></div>

    {{-- Perfil activo --}}
    <x-dropdown align="right" width="48">
        <x-slot name="trigger">
            <button type="button" class="flex items-center gap-2.5 rounded-lg p-1 pe-2 hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa">
                <span class="w-9 h-9 rounded-full bg-brand-50 text-brand-petroleo grid place-items-center text-sm font-bold">{{ $initials }}</span>
                <span class="hidden sm:block text-start leading-tight max-w-[14rem]">
                    <span class="block text-sm font-semibold text-ink truncate">{{ $user->name }}</span>
                    @if ($user->role)
                        <span class="block text-xs text-ink-muted truncate">{{ $user->role->label() }}</span>
                    @endif
                </span>
                <x-icon name="chevron-down" class="w-4 h-4 text-ink-muted" />
            </button>
        </x-slot>

        <x-slot name="content">
            <div class="px-4 py-2 border-b border-line md:hidden">
                <p class="text-xs text-ink-muted">Nodo</p>
                <p class="text-sm font-semibold truncate">{{ config('centinela.node_name') }}</p>
            </div>

            <x-dropdown-link :href="route('profile.edit')">
                Mi perfil
            </x-dropdown-link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-dropdown-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                    Cerrar sesión
                </x-dropdown-link>
            </form>
        </x-slot>
    </x-dropdown>
</header>
