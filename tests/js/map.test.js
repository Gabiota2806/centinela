import test from 'node:test';
import assert from 'node:assert/strict';

import {
    DEFAULT_MAP_CONFIG,
    GEO_CACHE_KEY,
    createCentinelaMap,
    isValidFeatureCollection,
    parseMapConfig,
} from '../../resources/js/map.js';

function createFakeElement(dataset = {}) {
    const listeners = new Map();
    const attrs = new Map();

    return {
        dataset: { ...dataset },
        textContent: '',
        hidden: false,
        classList: {
            classes: new Set(),
            add(...items) {
                items.forEach((item) => this.classes.add(item));
            },
            remove(...items) {
                items.forEach((item) => this.classes.delete(item));
            },
            toggle(item, force) {
                if (force === undefined) {
                    if (this.classes.has(item)) {
                        this.classes.delete(item);
                        return false;
                    }
                    this.classes.add(item);
                    return true;
                }
                if (force) {
                    this.classes.add(item);
                } else {
                    this.classes.delete(item);
                }
                return Boolean(force);
            },
            contains(item) {
                return this.classes.has(item);
            },
        },
        setAttribute(name, value) {
            attrs.set(name, String(value));
        },
        getAttribute(name) {
            return attrs.get(name) ?? null;
        },
        addEventListener(type, handler) {
            const list = listeners.get(type) ?? [];
            list.push(handler);
            listeners.set(type, list);
        },
        removeEventListener(type, handler) {
            const list = listeners.get(type) ?? [];
            listeners.set(
                type,
                list.filter((fn) => fn !== handler),
            );
        },
        trigger(type, event = {}) {
            const list = listeners.get(type) ?? [];
            list.forEach((fn) => fn(event));
        },
    };
}

function createFakeContainer(dataset = {}) {
    const canvas = createFakeElement();
    const statusBadge = createFakeElement();
    const statusMessage = createFakeElement();
    const statusBanner = createFakeElement();
    const recenterButton = createFakeElement();
    const refreshButton = createFakeElement();
    const retryButton = createFakeElement();
    const choroplethToggle = createFakeElement();
    const heatmapToggle = createFakeElement();
    const hoverPanel = createFakeElement();
    const riskCountLow = createFakeElement();
    const riskCountModerate = createFakeElement();
    const riskCountHigh = createFakeElement();
    const heatmapFebrileTotal = createFakeElement();
    const heatmapLegend = createFakeElement();

    const selectors = {
        '[data-map-canvas]': canvas,
        '[data-map-status-badge]': statusBadge,
        '[data-map-status-message]': statusMessage,
        '[data-map-banner]': statusBanner,
        '[data-map-recenter]': recenterButton,
        '[data-map-refresh]': refreshButton,
        '[data-map-retry]': retryButton,
        '[data-layer-toggle="choropleth"]': choroplethToggle,
        '[data-layer-toggle="heatmap"]': heatmapToggle,
        '[data-map-hover-panel]': hoverPanel,
        '[data-risk-count="low"]': riskCountLow,
        '[data-risk-count="moderate"]': riskCountModerate,
        '[data-risk-count="high"]': riskCountHigh,
        '[data-heatmap-febrile-total]': heatmapFebrileTotal,
        '[data-heatmap-legend]': heatmapLegend,
    };

    const root = createFakeElement(dataset);
    root.querySelector = (selector) => selectors[selector] ?? null;
    root.refs = selectors;

    return root;
}

function createFakeLeaflet() {
    const calls = {
        mapInit: null,
        tileLayer: null,
        setView: [],
        removed: false,
        invalidateSize: 0,
        tileLayerAdded: false,
        geoJsonAdded: 0,
        geoJsonRemoved: 0,
    };

    const tileHandlers = new Map();

    const tileLayerInstance = {
        on(event, handler) {
            tileHandlers.set(event, handler);
            return this;
        },
        addTo(map) {
            calls.tileLayerAdded = true;
            calls.tileLayerMap = map;
            return this;
        },
        trigger(event, payload = {}) {
            tileHandlers.get(event)?.(payload);
        },
    };

    const mapInstance = {
        setView(center, zoom, options) {
            calls.setView.push({ center, zoom, options });
            return this;
        },
        invalidateSize() {
            calls.invalidateSize += 1;
            return this;
        },
        getSize() {
            return { x: 800, y: 500 };
        },
        remove() {
            calls.removed = true;
        },
    };

    const leaflet = {
        map(element, options) {
            calls.mapInit = { element, options };
            return mapInstance;
        },
        tileLayer(url, options) {
            calls.tileLayer = { url, options };
            return tileLayerInstance;
        },
        geoJSON(collection, config) {
            return {
                collection,
                config,
                addTo() {
                    calls.geoJsonAdded += 1;
                    return this;
                },
                remove() {
                    calls.geoJsonRemoved += 1;
                },
                resetStyle() {},
            };
        },
    };

    return { leaflet, calls, mapInstance, tileLayerInstance };
}

function createMemoryStorage(initial = {}) {
    const data = new Map(Object.entries(initial));
    return {
        getItem: (key) => (data.has(key) ? data.get(key) : null),
        setItem: (key, value) => data.set(key, String(value)),
        removeItem: (key) => data.delete(key),
    };
}

const SAMPLE_COLLECTION = {
    type: 'FeatureCollection',
    features: [
        {
            type: 'Feature',
            id: 14,
            geometry: {
                type: 'Polygon',
                coordinates: [
                    [
                        [-58.18, -26.178],
                        [-58.17, -26.178],
                        [-58.17, -26.188],
                        [-58.18, -26.188],
                        [-58.18, -26.178],
                    ],
                ],
            },
            properties: {
                id: 14,
                name: 'San Miguel',
                risk_level: 'high',
                risk_score: 0.8421,
                febrile_cases_7d: 12,
                total_consultations_7d: 28,
                risk_updated_at: '2026-11-24T15:35:00-03:00',
                centroid_lat: -26.183,
                centroid_lng: -58.175,
            },
        },
    ],
};

test('DEFAULT_MAP_CONFIG centra el mapa en Formosa Capital con zoom 13 y tiles OSM', () => {
    assert.deepEqual(DEFAULT_MAP_CONFIG.center, [-26.18, -58.17]);
    assert.equal(DEFAULT_MAP_CONFIG.zoom, 13);
    assert.equal(DEFAULT_MAP_CONFIG.endpoint, '/api/v1/geo/neighborhoods');
    assert.match(DEFAULT_MAP_CONFIG.tileUrl, /tile\.openstreetmap\.org/);
});

test('parseMapConfig lee atributos dataset válidos y aplica fallback seguro ante valores inválidos', () => {
    const valid = parseMapConfig({
        dataset: {
            centerLat: '-26.20',
            centerLng: '-58.21',
            zoom: '15',
            endpoint: '/api/v1/geo/custom',
        },
    });

    assert.deepEqual(valid.center, [-26.2, -58.21]);
    assert.equal(valid.zoom, 15);
    assert.equal(valid.endpoint, '/api/v1/geo/custom');

    const invalid = parseMapConfig({
        dataset: {
            centerLat: 'no-es-numero',
            centerLng: '',
            zoom: '999',
        },
    });

    assert.deepEqual(invalid.center, [-26.18, -58.17]);
    assert.equal(invalid.zoom, 13);
});

test('isValidFeatureCollection valida la estructura GeoJSON del contrato', () => {
    assert.equal(isValidFeatureCollection(SAMPLE_COLLECTION), true);
    assert.equal(isValidFeatureCollection({ type: 'FeatureCollection', features: [] }), true);
    assert.equal(isValidFeatureCollection({ type: 'Feature', geometry: null }), false);
    assert.equal(isValidFeatureCollection(null), false);
});

test('createCentinelaMap inicializa Leaflet sobre OSM, consulta el endpoint y guarda caché local', async () => {
    const root = createFakeContainer({
        centerLat: '-26.18',
        centerLng: '-58.17',
        zoom: '13',
        endpoint: '/api/v1/geo/neighborhoods',
    });
    const { leaflet, calls } = createFakeLeaflet();
    const storage = createMemoryStorage();
    const fetchCalls = [];

    const fetchImpl = async (url, options) => {
        fetchCalls.push({ url, options });
        return {
            ok: true,
            status: 200,
            async json() {
                return SAMPLE_COLLECTION;
            },
        };
    };

    const controller = await createCentinelaMap(root, {
        leaflet,
        fetchImpl,
        storage,
    });

    assert.deepEqual(calls.mapInit.options.center, [-26.18, -58.17]);
    assert.equal(calls.mapInit.options.zoom, 13);
    assert.equal(calls.tileLayerAdded, true);
    assert.equal(calls.tileLayer.url, DEFAULT_MAP_CONFIG.tileUrl);
    assert.equal(fetchCalls.length, 1);
    assert.equal(fetchCalls[0].url, '/api/v1/geo/neighborhoods');
    assert.equal(fetchCalls[0].options.credentials, 'same-origin');
    assert.equal(fetchCalls[0].options.headers.Accept, 'application/json');
    assert.equal(controller.getState().status, 'online');
    assert.equal(controller.getState().featureCount, 1);
    assert.ok(storage.getItem(GEO_CACHE_KEY));

    root.refs['[data-map-recenter]'].trigger('click');
    assert.equal(calls.setView.length, 1);
    assert.deepEqual(calls.setView[0].center, [-26.18, -58.17]);
    assert.equal(calls.setView[0].zoom, 13);

    controller.destroy();
    assert.equal(calls.removed, true);
});

test('createCentinelaMap degrada sin fallos ante error HTTP o desconexión y usa caché local si existe', async () => {
    const storageWithCache = createMemoryStorage({
        [GEO_CACHE_KEY]: JSON.stringify({
            savedAt: '2026-11-24T15:35:00-03:00',
            data: SAMPLE_COLLECTION,
        }),
    });

    const rootCached = createFakeContainer();
    const fake1 = createFakeLeaflet();
    const controllerCached = await createCentinelaMap(rootCached, {
        leaflet: fake1.leaflet,
        fetchImpl: async () => {
            throw new Error('Network offline');
        },
        storage: storageWithCache,
    });

    assert.equal(controllerCached.getState().status, 'cached');
    assert.equal(controllerCached.getState().featureCount, 1);
    controllerCached.destroy();

    const rootUnavailable = createFakeContainer();
    const fake2 = createFakeLeaflet();
    const controllerUnavailable = await createCentinelaMap(rootUnavailable, {
        leaflet: fake2.leaflet,
        fetchImpl: async () => ({
            ok: false,
            status: 404,
            async json() {
                return { message: 'Not Found' };
            },
        }),
        storage: createMemoryStorage(),
    });

    assert.equal(controllerUnavailable.getState().status, 'unavailable');
    assert.equal(controllerUnavailable.getState().featureCount, 0);
    assert.equal(rootUnavailable.refs['[data-map-banner]'].hidden, false);
    controllerUnavailable.destroy();
});

test('createCentinelaMap renderiza coropletas, actualiza contadores de riesgo y conmuta visibilidad', async () => {
    const root = createFakeContainer();
    const { leaflet, calls } = createFakeLeaflet();

    const controller = await createCentinelaMap(root, {
        leaflet,
        fetchImpl: async () => ({
            ok: true,
            status: 200,
            async json() {
                return SAMPLE_COLLECTION;
            },
        }),
        storage: createMemoryStorage(),
    });

    assert.equal(calls.geoJsonAdded, 1);
    assert.equal(root.refs['[data-risk-count="high"]'].textContent, '1');
    assert.equal(root.refs['[data-risk-count="low"]'].textContent, '0');
    assert.equal(root.refs['[data-layer-toggle="choropleth"]'].getAttribute('aria-pressed'), 'true');

    // Conmutar coropletas OFF y luego ON
    root.refs['[data-layer-toggle="choropleth"]'].trigger('click');
    assert.equal(controller.getState().choroplethVisible, false);
    assert.equal(calls.geoJsonRemoved, 1);
    assert.equal(root.refs['[data-layer-toggle="choropleth"]'].getAttribute('aria-pressed'), 'false');

    root.refs['[data-layer-toggle="choropleth"]'].trigger('click');
    assert.equal(controller.getState().choroplethVisible, true);
    assert.equal(calls.geoJsonAdded, 2);

    controller.destroy();
    assert.equal(calls.geoJsonRemoved, 2);
});

test('createCentinelaMap conmuta capa de calor heatmap, actualiza resumen febril y limpia al destruir', async () => {
    const heatInstances = [];

    class FakeHeatLayer {
        constructor(points, options) {
            this.points = points;
            this.options = options;
            this.added = 0;
            this.removed = 0;
            heatInstances.push(this);
        }

        addTo() {
            this.added += 1;
            return this;
        }

        remove() {
            this.removed += 1;
            return this;
        }

        setLatLngs(points) {
            this.points = points;
            return this;
        }
    }

    const root = createFakeContainer();
    const { leaflet } = createFakeLeaflet();

    const controller = await createCentinelaMap(root, {
        leaflet,
        HeatLayerClass: FakeHeatLayer,
        fetchImpl: async () => ({
            ok: true,
            status: 200,
            async json() {
                return SAMPLE_COLLECTION;
            },
        }),
        storage: createMemoryStorage(),
    });

    assert.equal(controller.getState().heatmapVisible, false);
    assert.equal(root.refs['[data-heatmap-febrile-total]'].textContent, '12 febriles (1 foco)');
    assert.equal(root.refs['[data-heatmap-legend]'].hidden, true);
    assert.equal(heatInstances.length, 0);

    // Activar mapa de calor (crea instancia bajo demanda y agrega al mapa)
    await controller.setHeatmapVisible(true);
    assert.equal(controller.getState().heatmapVisible, true);
    assert.equal(root.refs['[data-layer-toggle="heatmap"]'].getAttribute('aria-pressed'), 'true');
    assert.equal(root.refs['[data-heatmap-legend]'].hidden, false);
    assert.equal(heatInstances.length, 1);
    assert.equal(heatInstances[0].added, 1);
    assert.equal(heatInstances[0].points.length, 1);

    // Desactivar mapa de calor
    await controller.setHeatmapVisible(false);
    assert.equal(controller.getState().heatmapVisible, false);
    assert.equal(heatInstances[0].removed, 1);
    assert.equal(root.refs['[data-heatmap-legend]'].hidden, true);

    controller.destroy();
});
