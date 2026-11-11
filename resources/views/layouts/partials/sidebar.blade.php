@php
    $items = collect(config('centinela.navigation', []))
        ->filter(fn (array $item) => Route::has($item['route'])
            && (! isset($item['can']) || Gate::allows($item['can'])));
@endphp

{{-- Fondo del menú deslizable (tablet/celular) --}}
<div x-show="sidebarOpen"
     x-cloak
     x-transition.opacity
     x-on:click="sidebarOpen = false"
     class="lg:hidden fixed inset-0 z-40 bg-brand-noche/50"></div>

<aside data-testid="sidebar"
       class="fixed inset-y-0 start-0 z-50 w-64 -translate-x-full bg-white border-e border-line flex flex-col p-3 transition-[transform,width] duration-200
              lg:sticky lg:top-16 lg:z-20 lg:h-[calc(100vh-4rem)] lg:translate-x-0"
       :class="{ 'translate-x-0': sidebarOpen, 'lg:w-[4.5rem]': collapsed }"
       aria-label="Navegación principal">

    <div class="lg:hidden flex items-center justify-between px-2 pb-3 mb-2 border-b border-line">
        <x-centinela-logo :tagline="false" />
        <button type="button"
                class="p-2 rounded-lg text-ink-muted hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa"
                x-on:click="sidebarOpen = false"
                aria-label="Cerrar menú">
            <x-icon name="x-mark" />
        </button>
    </div>

    <nav class="flex flex-col gap-1">
        @foreach ($items as $item)
            <x-sidebar-link :href="route($item['route'])"
                            :active="request()->routeIs($item['route'])"
                            :icon="$item['icon']"
                            :label="$item['label']" />
        @endforeach
    </nav>

    <div class="mt-auto pt-3 border-t border-line flex flex-col gap-1">
        <button type="button"
                data-testid="sidebar-collapse"
                class="hidden lg:flex items-center gap-3 h-10 px-3 rounded-lg text-sm font-medium text-ink-muted hover:bg-canvas hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa"
                :class="collapsed && 'justify-center px-0'"
                x-on:click="toggleCollapsed()"
                :aria-label="collapsed ? 'Expandir menú' : 'Colapsar menú'"
                :aria-expanded="(! collapsed).toString()">
            <x-icon name="chevron-double-left" class="w-5 h-5 transition-transform" ::class="collapsed && 'rotate-180'" />
            <span x-show="! collapsed">Colapsar menú</span>
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    title="Cerrar sesión"
                    class="w-full flex items-center gap-3 h-10 px-3 rounded-lg text-sm font-medium text-ink-muted hover:bg-canvas hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa"
                    :class="collapsed && 'lg:justify-center lg:px-0'">
                <x-icon name="logout" />
                <span :class="collapsed && 'lg:sr-only'">Cerrar sesión</span>
            </button>
        </form>
    </div>
</aside>
