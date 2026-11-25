import test from 'node:test';
import assert from 'node:assert/strict';

import {
    RISK_PALETTE,
    buildNeighborhoodTooltipHtml,
    createChoroplethLayer,
    dispatchNeighborhoodDetail,
    escapeHtml,
    getChoroplethFeatureStyle,
    normalizeRiskLevel,
    summarizeRiskDistribution,
} from '../../resources/js/choropleth.js';

const SAMPLE_FEATURES = {
    type: 'FeatureCollection',
    features: [
        {
            type: 'Feature',
            id: 14,
            geometry: {
                type: 'MultiPolygon',
                coordinates: [
                    [
                        [
                            [-58.18, -26.178],
                            [-58.17, -26.178],
                            [-58.17, -26.188],
                            [-58.18, -26.188],
                            [-58.18, -26.178],
                        ],
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
            },
        },
        {
            type: 'Feature',
            id: 21,
            geometry: {
                type: 'Polygon',
                coordinates: [
                    [
                        [-58.19, -26.18],
                        [-58.18, -26.18],
                        [-58.18, -26.19],
                        [-58.19, -26.19],
                        [-58.19, -26.18],
                    ],
                ],
            },
            properties: {
                id: 21,
                name: 'Don Bosco',
                risk_level: 'moderate',
                risk_score: 0.52,
                febrile_cases_7d: 5,
                total_consultations_7d: 14,
            },
        },
        {
            type: 'Feature',
            id: 3,
            geometry: {
                type: 'Polygon',
                coordinates: [
                    [
                        [-58.175, -26.17],
                        [-58.165, -26.17],
                        [-58.165, -26.178],
                        [-58.175, -26.178],
                        [-58.175, -26.17],
                    ],
                ],
            },
            properties: {
                id: 3,
                name: 'Centro',
                risk_level: 'normal',
                risk_score: 0.12,
                febrile_cases_7d: 0,
                total_consultations_7d: 9,
            },
        },
        {
            type: 'Feature',
            id: 99,
            geometry: null,
            properties: {
                id: 99,
                name: 'Barrio sin geometría',
                risk_level: 'high',
            },
        },
    ],
};

function createFakeGeoJsonLeaflet() {
    const state = {
        sanitizedCollection: null,
        boundLayers: [],
        resetStyleCalls: [],
    };

    const leaflet = {
        geoJSON(collection, config) {
            state.sanitizedCollection = collection;

            const geoJsonLayer = {
                resetStyle(target) {
                    state.resetStyleCalls.push(target);
                },
                addTo(map) {
                    this._map = map;
                    return this;
                },
                remove() {
                    this._removed = true;
                },
            };

            collection.features.forEach((feature) => {
                const handlers = new Map();
                const layer = {
                    feature,
                    style: config.style(feature),
                    tooltip: null,
                    broughtToFront: false,
                    stylesApplied: [],
                    bindTooltip(html, options) {
                        this.tooltip = { html, options };
                        return this;
                    },
                    on(eventsMap) {
                        Object.entries(eventsMap).forEach(([evt, fn]) => handlers.set(evt, fn));
                        return this;
                    },
                    setStyle(nextStyle) {
                        this.stylesApplied.push(nextStyle);
                    },
                    bringToFront() {
                        this.broughtToFront = true;
                    },
                    trigger(evt) {
                        handlers.get(evt)?.({ target: this });
                    },
                };

                config.onEachFeature(feature, layer);
                state.boundLayers.push(layer);
            });

            return geoJsonLayer;
        },
    };

    return { leaflet, state };
}

test('normalizeRiskLevel mapea normal a low y respeta los niveles canónicos del contrato', () => {
    assert.equal(normalizeRiskLevel('low'), 'low');
    assert.equal(normalizeRiskLevel('normal'), 'low');
    assert.equal(normalizeRiskLevel('bajo'), 'low');
    assert.equal(normalizeRiskLevel(null), 'low');
    assert.equal(normalizeRiskLevel('moderate'), 'moderate');
    assert.equal(normalizeRiskLevel('moderado'), 'moderate');
    assert.equal(normalizeRiskLevel('high'), 'high');
    assert.equal(normalizeRiskLevel('alto'), 'high');
});

test('getChoroplethFeatureStyle aplica los tokens oficiales del Design System por nivel de riesgo', () => {
    const low = getChoroplethFeatureStyle({ properties: { risk_level: 'normal' } });
    const moderate = getChoroplethFeatureStyle({ properties: { risk_level: 'moderate' } });
    const high = getChoroplethFeatureStyle({ properties: { risk_level: 'high' } });

    assert.equal(low.fillColor, RISK_PALETTE.low.fillColor);
    assert.equal(moderate.fillColor, RISK_PALETTE.moderate.fillColor);
    assert.equal(high.fillColor, RISK_PALETTE.high.fillColor);
    assert.equal(high.fillColor, '#C2410C');
    assert.equal(moderate.fillColor, '#F4B860');
    assert.equal(low.fillColor, '#9FD8C6');
});

test('escapeHtml y buildNeighborhoodTooltipHtml previenen XSS y muestran métricas barriales', () => {
    assert.equal(escapeHtml('<script>alert("x")</script>'), '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;');

    const html = buildNeighborhoodTooltipHtml({
        name: '<img src=x onerror=alert(1)> San Miguel',
        risk_level: 'high',
        risk_score: 0.8421,
        febrile_cases_7d: 12,
        total_consultations_7d: 28,
    });

    assert.doesNotMatch(html, /<img/i);
    assert.match(html, /&lt;img/);
    assert.match(html, /Riesgo Alto/);
    assert.match(html, /12/);
    assert.match(html, /28/);
});

test('dispatchNeighborhoodDetail usa Livewire.dispatch cuando existe y CustomEvent como respaldo', () => {
    const livewireCalls = [];
    const fakeLivewireWindow = {
        Livewire: {
            dispatch(name, params) {
                livewireCalls.push({ name, params });
            },
        },
    };

    assert.equal(dispatchNeighborhoodDetail(14, fakeLivewireWindow), true);
    assert.deepEqual(livewireCalls, [
        { name: 'open-neighborhood-detail', params: { neighborhoodId: 14 } },
    ]);

    const customEvents = [];
    const fakePlainWindow = {
        dispatchEvent(evt) {
            customEvents.push(evt);
        },
    };

    assert.equal(dispatchNeighborhoodDetail('21', fakePlainWindow), true);
    assert.equal(customEvents.length, 1);
    assert.equal(customEvents[0].type, 'open-neighborhood-detail');
    assert.deepEqual(customEvents[0].detail, { neighborhoodId: 21 });

    assert.equal(dispatchNeighborhoodDetail(0, fakeLivewireWindow), false);
    assert.equal(dispatchNeighborhoodDetail('invalido', fakeLivewireWindow), false);
});

test('createChoroplethLayer filtra geometrías nulas y maneja mouseover, mouseout y click', () => {
    const { leaflet, state } = createFakeGeoJsonLeaflet();
    const hovered = [];
    const dispatched = [];
    const fakeWindow = {
        Livewire: {
            dispatch(name, params) {
                dispatched.push({ name, params });
            },
        },
    };

    createChoroplethLayer(leaflet, SAMPLE_FEATURES, {
        targetWindow: fakeWindow,
        onHover: (info) => hovered.push(info),
    });

    // Filtra el feature sin geometría (de 4 quedan 3 válidos)
    assert.equal(state.sanitizedCollection.features.length, 3);
    assert.equal(state.boundLayers.length, 3);

    const sanMiguelLayer = state.boundLayers[0];
    assert.equal(sanMiguelLayer.style.fillColor, '#C2410C');
    assert.ok(sanMiguelLayer.tooltip);
    assert.match(sanMiguelLayer.tooltip.html, /San Miguel/);

    sanMiguelLayer.trigger('mouseover');
    assert.equal(sanMiguelLayer.broughtToFront, true);
    assert.equal(sanMiguelLayer.stylesApplied.length, 1);
    assert.equal(sanMiguelLayer.stylesApplied[0].weight, 3);
    assert.equal(hovered.at(-1).name, 'San Miguel');
    assert.equal(hovered.at(-1).riskLevel, 'high');

    sanMiguelLayer.trigger('mouseout');
    assert.equal(state.resetStyleCalls.length, 1);
    assert.equal(hovered.at(-1), null);

    sanMiguelLayer.trigger('click');
    assert.deepEqual(dispatched, [
        { name: 'open-neighborhood-detail', params: { neighborhoodId: 14 } },
    ]);
});

test('summarizeRiskDistribution contabiliza barrios válidos por nivel normalizado', () => {
    const summary = summarizeRiskDistribution(SAMPLE_FEATURES);

    assert.deepEqual(summary, {
        total: 3,
        low: 1,
        moderate: 1,
        high: 1,
    });
});
