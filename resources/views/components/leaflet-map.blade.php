@props([
    'endpoint' => '/api/v1/geo/neighborhoods',
    'centerLat' => -26.18,
    'centerLng' => -58.17,
    'zoom' => 13,
    'tileUrl' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    'heatmapDefault' => false,
    'heightClass' => 'h-[480px] sm:h-[540px]',
    'title' => 'Mapa Epidemiológico de Formosa Capital',
])

<section
    data-centinela-map
    data-endpoint="{{ $endpoint }}"
    data-center-lat="{{ $centerLat }}"
    data-center-lng="{{ $centerLng }}"
    data-zoom="{{ $zoom }}"
    data-tile-url="{{ $tileUrl }}"
    data-heatmap-default="{{ $heatmapDefault ? 'true' : 'false' }}"
    role="region"
    aria-label="{{ $title }}"
    {{ $attributes->merge(['class' => 'bg-white border border-line rounded-2xl shadow-card overflow-hidden']) }}
>
    {{-- Encabezado y controles del visor --}}
    <div class="px-5 py-4 border-b border-line flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-petroleo shrink-0">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                    </svg>
                </span>
                <h2 class="text-base font-bold text-ink truncate">{{ $title }}</h2>
                <span
                    data-map-status-badge
                    data-status="loading"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-brand-50 text-brand-petroleo border border-line"
                >
                    Inicializando mapa...
                </span>
            </div>
            <p class="text-xs text-ink-muted mt-1">
                Vigilancia territorial georreferenciada sobre OpenStreetMap (Formosa Capital · SRID 4326).
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Conmutador de capa coroplética --}}
            <button
                type="button"
                data-layer-toggle="choropleth"
                aria-pressed="true"
                data-active="true"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-brand-petroleo bg-brand-petroleo text-xs font-semibold text-white hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa transition"
                title="Mostrar u ocultar polígonos de riesgo barrial"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3" />
                </svg>
                <span>Coropletas</span>
            </button>

            {{-- Conmutador de capa de calor (leaflet.heat) --}}
            <button
                type="button"
                data-layer-toggle="heatmap"
                aria-pressed="{{ $heatmapDefault ? 'true' : 'false' }}"
                data-active="{{ $heatmapDefault ? 'true' : 'false' }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border {{ $heatmapDefault ? 'border-brand-petroleo bg-brand-petroleo text-white' : 'border-line bg-white text-ink' }} text-xs font-semibold hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa transition"
                title="Mostrar u ocultar mapa de calor de densidad febril"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z" />
                </svg>
                <span>Mapa de calor</span>
            </button>

            <button
                type="button"
                data-map-recenter
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-line bg-white text-xs font-semibold text-ink hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa transition"
                title="Recentrar mapa en Formosa Capital"
            >
                <svg class="w-4 h-4 text-brand-petroleo" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.364 0a6.364 6.364 0 1 1-12.728 0 6.364 6.364 0 0 1 12.728 0Z" />
                </svg>
                <span>Centrar Formosa</span>
            </button>

            <button
                type="button"
                data-map-refresh
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-line bg-white text-xs font-semibold text-ink hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa transition"
                title="Actualizar datos geoespaciales"
            >
                <svg class="w-4 h-4 text-brand-petroleo" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span>Actualizar</span>
            </button>
        </div>
    </div>

    {{-- Aviso no bloqueante ante modo offline o API espacial no disponible --}}
    <div
        data-map-banner
        hidden
        class="px-5 py-2.5 bg-canvas border-b border-line flex flex-wrap items-center justify-between gap-2 text-xs text-ink-muted"
        role="status"
        aria-live="polite"
    >
        <div class="flex items-center gap-2 min-w-0">
            <svg class="w-4 h-4 text-warn shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <span data-map-status-message class="truncate">Conectando con la capa geoespacial...</span>
        </div>

        <button
            type="button"
            data-map-retry
            class="inline-flex items-center gap-1 font-semibold text-brand-petroleo hover:text-brand-turquesa focus:outline-none focus-visible:underline"
        >
            Reintentar
        </button>
    </div>

    {{-- Barra de inspección interactiva al pasar el cursor sobre cada barrio --}}
    <div class="px-5 py-2 bg-canvas/70 border-b border-line flex flex-wrap items-center justify-between gap-2 text-xs text-ink-muted">
        <div class="flex items-center gap-2 min-w-0">
            <svg class="w-4 h-4 text-brand-turquesa shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <span data-map-hover-panel class="truncate">
                Pasá el cursor sobre un barrio para inspeccionar su riesgo o hacé clic para abrir el detalle.
            </span>
        </div>

        <span
            data-heatmap-febrile-total
            class="inline-flex items-center px-2 py-0.5 rounded-md bg-white border border-line text-[11px] font-semibold text-ink shrink-0"
        >
            0 febriles (0 focos)
        </span>
    </div>

    {{-- Lienzo Leaflet aislado en stacking context propio (z-0) para no superponerse a topbar/sidebar/modales --}}
    <div class="relative z-0 isolate">
        <div
            data-map-canvas
            class="centinela-leaflet-canvas w-full {{ $heightClass }}"
            aria-label="Mapa interactivo de barrios de Formosa Capital"
        ></div>
    </div>

    {{-- Leyenda de riesgo barrial, escala térmica febril y pie normativo --}}
    <div class="px-5 py-3 border-t border-line bg-canvas/60 flex flex-wrap items-center justify-between gap-3 text-xs text-ink-muted">
        <div class="flex flex-wrap items-center gap-4" aria-label="Leyenda de riesgo epidemiológico">
            <span class="inline-flex items-center gap-1.5 font-medium text-ink">
                <span class="w-3 h-3 rounded-sm bg-risk-low border border-ok inline-block"></span>
                <span>Riesgo Bajo</span>
                <span data-risk-count="low" class="px-1.5 py-0.5 rounded bg-white border border-line text-[11px] font-semibold text-ink-muted">0</span>
            </span>
            <span class="inline-flex items-center gap-1.5 font-medium text-ink">
                <span class="w-3 h-3 rounded-sm bg-risk-mid border border-warn inline-block"></span>
                <span>Riesgo Moderado</span>
                <span data-risk-count="moderate" class="px-1.5 py-0.5 rounded bg-white border border-line text-[11px] font-semibold text-ink-muted">0</span>
            </span>
            <span class="inline-flex items-center gap-1.5 font-medium text-ink">
                <span class="w-3 h-3 rounded-sm bg-risk-high border border-danger inline-block"></span>
                <span>Riesgo Alto</span>
                <span data-risk-count="high" class="px-1.5 py-0.5 rounded bg-white border border-line text-[11px] font-semibold text-ink-muted">0</span>
            </span>

            <span
                data-heatmap-legend
                @unless ($heatmapDefault) hidden @endunless
                class="inline-flex items-center gap-2 font-medium text-ink"
            >
                <span>Densidad febril (7d):</span>
                <span class="w-20 h-2.5 rounded-full border border-line inline-block" style="background: linear-gradient(90deg, #9FD8C6 0%, #F4B860 45%, #EA580C 75%, #C2410C 100%);"></span>
                <span class="text-[11px] text-ink-muted">Baja → Alta</span>
            </span>
        </div>

        <span>Datos agregados anonimizados (Ley N.º 25.326)</span>
    </div>
</section>
