/**
 * CEN-60: Visor interactivo Leaflet.js sobre OpenStreetMap centrado en Formosa Capital.
 * Consume GET /api/v1/geo/neighborhoods según docs/specs/00_contracts.md §4.3.
 */

export const DEFAULT_MAP_CONFIG = Object.freeze({
    center: [-26.18, -58.17],
    zoom: 13,
    minZoom: 11,
    maxZoom: 18,
    endpoint: '/api/v1/geo/neighborhoods',
    tileUrl: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    tileAttribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors',
});

export const GEO_CACHE_KEY = 'centinela.geo.neighborhoods.v1';

const activeControllers = new Map();

function parseBoundedNumber(raw, fallback, min, max) {
    if (raw === undefined || raw === null || String(raw).trim() === '') {
        return fallback;
    }

    const parsed = Number(raw);
    if (!Number.isFinite(parsed) || parsed < min || parsed > max) {
        return fallback;
    }

    return parsed;
}

export function parseMapConfig(container) {
    const dataset = container?.dataset ?? {};

    const lat = parseBoundedNumber(dataset.centerLat, DEFAULT_MAP_CONFIG.center[0], -90, 90);
    const lng = parseBoundedNumber(dataset.centerLng, DEFAULT_MAP_CONFIG.center[1], -180, 180);
    const zoom = Math.round(
        parseBoundedNumber(dataset.zoom, DEFAULT_MAP_CONFIG.zoom, DEFAULT_MAP_CONFIG.minZoom, DEFAULT_MAP_CONFIG.maxZoom),
    );

    const endpoint = typeof dataset.endpoint === 'string' && dataset.endpoint.trim() !== ''
        ? dataset.endpoint.trim()
        : DEFAULT_MAP_CONFIG.endpoint;

    const tileUrl = typeof dataset.tileUrl === 'string' && dataset.tileUrl.trim() !== ''
        ? dataset.tileUrl.trim()
        : DEFAULT_MAP_CONFIG.tileUrl;

    return {
        ...DEFAULT_MAP_CONFIG,
        center: [lat, lng],
        zoom,
        endpoint,
        tileUrl,
    };
}

export function isValidFeatureCollection(payload) {
    return (
        Boolean(payload)
        && typeof payload === 'object'
        && payload.type === 'FeatureCollection'
        && Array.isArray(payload.features)
    );
}

function resolveDefaultStorage() {
    try {
        return typeof globalThis.localStorage !== 'undefined' ? globalThis.localStorage : null;
    } catch {
        return null;
    }
}

function readCachedGeoJson(storage) {
    if (!storage || typeof storage.getItem !== 'function') {
        return null;
    }

    try {
        const raw = storage.getItem(GEO_CACHE_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        if (parsed && isValidFeatureCollection(parsed.data)) {
            return parsed;
        }
    } catch {
        // Ignora caché corrupta o inaccesible.
    }

    return null;
}

function writeCachedGeoJson(storage, data) {
    if (!storage || typeof storage.setItem !== 'function') {
        return;
    }

    try {
        storage.setItem(
            GEO_CACHE_KEY,
            JSON.stringify({
                savedAt: new Date().toISOString(),
                data,
            }),
        );
    } catch {
        // Ignora cuota excedida en modo privado/offline.
    }
}

async function loadBrowserLeaflet() {
    const [{ default: L }] = await Promise.all([
        import('leaflet'),
        import('leaflet/dist/leaflet.css'),
        import('../css/leaflet-map.css'),
    ]);

    return L;
}

function setBadgeAppearance(badgeEl, status, label) {
    if (!badgeEl) {
        return;
    }

    badgeEl.textContent = label;
    badgeEl.setAttribute('data-status', status);
}

function updateBanner(bannerEl, messageEl, visible, message = '') {
    if (bannerEl) {
        bannerEl.hidden = !visible;
    }

    if (messageEl && message !== '') {
        messageEl.textContent = message;
    }
}

export async function createCentinelaMap(container, options = {}) {
    if (!container) {
        throw new Error('Contenedor de mapa Leaflet no encontrado.');
    }

    const canvas = container.querySelector('[data-map-canvas]') ?? container;
    const config = parseMapConfig(container);
    const L = options.leaflet ?? (await loadBrowserLeaflet());
    const fetchImpl = options.fetchImpl ?? (typeof globalThis.fetch === 'function' ? globalThis.fetch.bind(globalThis) : null);
    const storage = options.storage !== undefined ? options.storage : resolveDefaultStorage();

    const statusBadge = container.querySelector('[data-map-status-badge]');
    const statusMessage = container.querySelector('[data-map-status-message]');
    const statusBanner = container.querySelector('[data-map-banner]');
    const recenterButton = container.querySelector('[data-map-recenter]');
    const refreshButton = container.querySelector('[data-map-refresh]');
    const retryButton = container.querySelector('[data-map-retry]');

    const state = {
        status: 'loading',
        featureCount: 0,
        tileOffline: false,
        lastFeatureCollection: null,
        lastError: null,
        destroyed: false,
    };

    const map = L.map(canvas, {
        center: config.center,
        zoom: config.zoom,
        minZoom: config.minZoom,
        maxZoom: config.maxZoom,
        zoomControl: true,
        scrollWheelZoom: true,
        preferCanvas: true,
    });

    const tileLayer = L.tileLayer(config.tileUrl, {
        attribution: config.tileAttribution,
        maxZoom: config.maxZoom,
        crossOrigin: true,
    });

    tileLayer.on('tileerror', () => {
        if (state.destroyed || state.tileOffline) {
            return;
        }
        state.tileOffline = true;
        if (state.status === 'online' || state.status === 'cached') {
            updateBanner(
                statusBanner,
                statusMessage,
                true,
                'Sin conexión a cartografía OpenStreetMap. Se mantienen visibles las capas barriales locales.',
            );
        }
    });

    tileLayer.on('load', () => {
        if (state.destroyed) {
            return;
        }
        state.tileOffline = false;
        if (state.status === 'online') {
            updateBanner(statusBanner, statusMessage, false);
        }
    });

    tileLayer.addTo(map);

    let resizeObserver = null;
    if (typeof globalThis.ResizeObserver === 'function' && canvas) {
        resizeObserver = new globalThis.ResizeObserver(() => {
            if (!state.destroyed) {
                map.invalidateSize({ animate: false });
            }
        });
        resizeObserver.observe(canvas);
    }

    function applyFeatureCollection(featureCollection) {
        state.lastFeatureCollection = featureCollection;
        state.featureCount = featureCollection.features.length;
    }

    async function loadNeighborhoods() {
        if (state.destroyed) {
            return state.lastFeatureCollection;
        }

        state.status = 'loading';
        state.lastError = null;
        setBadgeAppearance(statusBadge, 'loading', 'Actualizando mapa...');

        if (!fetchImpl) {
            state.status = 'unavailable';
            setBadgeAppearance(statusBadge, 'unavailable', 'Sin datos geoespaciales');
            updateBanner(
                statusBanner,
                statusMessage,
                true,
                'Cliente HTTP no disponible en este entorno.',
            );
            return null;
        }

        try {
            const response = await fetchImpl(config.endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            if (!isValidFeatureCollection(payload)) {
                throw new Error('Respuesta GeoJSON inválida');
            }

            if (state.destroyed) {
                return null;
            }

            applyFeatureCollection(payload);
            writeCachedGeoJson(storage, payload);

            state.status = 'online';
            setBadgeAppearance(
                statusBadge,
                'online',
                `${state.featureCount} ${state.featureCount === 1 ? 'barrio cargado' : 'barrios cargados'}`,
            );
            updateBanner(statusBanner, statusMessage, false);

            return payload;
        } catch (error) {
            if (state.destroyed) {
                return null;
            }

            state.lastError = error instanceof Error ? error.message : String(error);

            const cached = readCachedGeoJson(storage);
            if (cached) {
                applyFeatureCollection(cached.data);
                state.status = 'cached';
                setBadgeAppearance(statusBadge, 'cached', 'Caché local (offline)');
                updateBanner(
                    statusBanner,
                    statusMessage,
                    true,
                    'No se pudo sincronizar con la API espacial. Mostrando última captura barrial guardada localmente.',
                );

                return cached.data;
            }

            state.status = 'unavailable';
            state.featureCount = 0;
            setBadgeAppearance(statusBadge, 'unavailable', 'API espacial pendiente');
            updateBanner(
                statusBanner,
                statusMessage,
                true,
                'Aún no hay capas barriales disponibles desde /api/v1/geo/neighborhoods. El mapa base de Formosa sigue operativo.',
            );

            return null;
        }
    }

    const onRecenter = () => {
        if (!state.destroyed) {
            map.setView(config.center, config.zoom, { animate: true });
        }
    };

    const onRefresh = () => {
        void loadNeighborhoods();
    };

    recenterButton?.addEventListener('click', onRecenter);
    refreshButton?.addEventListener('click', onRefresh);
    retryButton?.addEventListener('click', onRefresh);

    const controller = {
        map,
        config,
        getState: () => ({ ...state }),
        reload: loadNeighborhoods,
        renderFeatureCollection: (featureCollection) => {
            if (!isValidFeatureCollection(featureCollection) || state.destroyed) {
                return false;
            }
            applyFeatureCollection(featureCollection);
            state.status = 'online';
            setBadgeAppearance(
                statusBadge,
                'online',
                `${state.featureCount} ${state.featureCount === 1 ? 'barrio cargado' : 'barrios cargados'}`,
            );
            updateBanner(statusBanner, statusMessage, false);
            return true;
        },
        recenter: onRecenter,
        destroy() {
            if (state.destroyed) {
                return;
            }
            state.destroyed = true;
            recenterButton?.removeEventListener('click', onRecenter);
            refreshButton?.removeEventListener('click', onRefresh);
            retryButton?.removeEventListener('click', onRefresh);
            resizeObserver?.disconnect();
            map.remove();
            activeControllers.delete(container);
        },
    };

    activeControllers.set(container, controller);

    await loadNeighborhoods();

    return controller;
}

export async function initAllLeafletMaps(root = typeof document !== 'undefined' ? document : null) {
    if (!root || typeof root.querySelectorAll !== 'function') {
        return [];
    }

    const containers = Array.from(root.querySelectorAll('[data-centinela-map]'));
    const initialized = [];

    for (const container of containers) {
        if (activeControllers.has(container)) {
            initialized.push(activeControllers.get(container));
            continue;
        }

        const controller = await createCentinelaMap(container);
        initialized.push(controller);
    }

    if (typeof globalThis.window !== 'undefined') {
        globalThis.window.CentinelaMaps = initialized;
    }

    return initialized;
}

export function destroyAllLeafletMaps() {
    for (const controller of Array.from(activeControllers.values())) {
        controller.destroy();
    }
    activeControllers.clear();
}
