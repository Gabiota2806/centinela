import test from 'node:test';
import assert from 'node:assert/strict';

import {
    DEFAULT_HEATMAP_OPTIONS,
    buildHeatmapPoints,
    createHeatmapController,
    extractFeatureCentroid,
} from '../../resources/js/heatmap.js';

const SAMPLE_GEOJSON = {
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
                febrile_cases_7d: 10,
                centroid_lat: -26.183,
                centroid_lng: -58.175,
            },
        },
        {
            type: 'Feature',
            id: 21,
            geometry: {
                type: 'Polygon',
                coordinates: [
                    [
                        [-58.2, -26.19],
                        [-58.19, -26.19],
                        [-58.19, -26.2],
                        [-58.2, -26.2],
                        [-58.2, -26.19],
                    ],
                ],
            },
            properties: {
                id: 21,
                name: 'Lote 4',
                risk_level: 'moderate',
                febrile_cases_7d: 5,
                // Sin centroid_lat/lng explícitos: debe calcular respaldo desde el polígono
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
                risk_level: 'low',
                febrile_cases_7d: 0,
                centroid_lat: -26.174,
                centroid_lng: -58.17,
            },
        },
    ],
};

class FakeHeatLayer {
    constructor(points, options) {
        this.points = points;
        this.options = options;
        this.addedTo = null;
        this.removedCount = 0;
        this.setLatLngsCalls = 0;
    }

    addTo(map) {
        this.addedTo = map;
        return this;
    }

    remove() {
        this.addedTo = null;
        this.removedCount += 1;
        return this;
    }

    setLatLngs(points) {
        this.points = points;
        this.setLatLngsCalls += 1;
        return this;
    }
}

test('extractFeatureCentroid prioriza centroid_lat/lng y calcula centroide geométrico como respaldo', () => {
    const fromProps = extractFeatureCentroid(SAMPLE_GEOJSON.features[0]);
    assert.deepEqual(fromProps, [-26.183, -58.175]);

    const fromPolygon = extractFeatureCentroid(SAMPLE_GEOJSON.features[1]);
    assert.ok(Array.isArray(fromPolygon));
    assert.ok(Math.abs(fromPolygon[0] - -26.195) < 1e-6);
    assert.ok(Math.abs(fromPolygon[1] - -58.195) < 1e-6);

    const fromMultiPolygon = extractFeatureCentroid({
        type: 'Feature',
        geometry: {
            type: 'MultiPolygon',
            coordinates: [
                [
                    [
                        [-58.18, -26.18],
                        [-58.16, -26.18],
                        [-58.16, -26.2],
                        [-58.18, -26.2],
                        [-58.18, -26.18],
                    ],
                ],
            ],
        },
        properties: {},
    });
    assert.ok(Math.abs(fromMultiPolygon[0] - -26.19) < 1e-6);
    assert.ok(Math.abs(fromMultiPolygon[1] - -58.17) < 1e-6);

    assert.equal(extractFeatureCentroid({ type: 'Feature', geometry: null, properties: {} }), null);
});

test('buildHeatmapPoints pondera por febrile_cases_7d y excluye barrios sin casos febriles', () => {
    const { points, summary } = buildHeatmapPoints(SAMPLE_GEOJSON);

    // Centro tiene 0 casos febriles -> excluido
    assert.equal(points.length, 2);
    assert.deepEqual(summary, {
        hotspotCount: 2,
        totalFebrileCases7d: 15,
        maxFebrileCases7d: 10,
    });

    // San Miguel (10 / 10 = 1.0) y Lote 4 (5 / 10 = 0.5)
    assert.deepEqual(points[0], [-26.183, -58.175, 1]);
    assert.equal(points[1][2], 0.5);
});

test('buildHeatmapPoints aplica el umbral base (referenceThreshold=5) para no sobre-escalar casos aislados', () => {
    const lowCountCollection = {
        type: 'FeatureCollection',
        features: [
            {
                type: 'Feature',
                id: 1,
                geometry: null,
                properties: {
                    febrile_cases_7d: 1,
                    centroid_lat: -26.18,
                    centroid_lng: -58.17,
                },
            },
        ],
    };

    const { points } = buildHeatmapPoints(lowCountCollection);
    assert.equal(points.length, 1);
    assert.equal(points[0][2], 0.2);
});

test('createHeatmapController conmuta visibilidad, actualiza in-place con setLatLngs y limpia recursos', async () => {
    const fakeMap = {
        getSize: () => ({ x: 900, y: 500 }),
    };

    const controller = await createHeatmapController(fakeMap, SAMPLE_GEOJSON, {
        HeatLayerClass: FakeHeatLayer,
        visible: false,
    });

    assert.equal(controller.isVisible(), false);
    assert.equal(controller.layer.addedTo, null);
    assert.equal(controller.layer.options.maxZoom, DEFAULT_HEATMAP_OPTIONS.maxZoom);
    assert.equal(controller.getSummary().totalFebrileCases7d, 15);

    controller.setVisible(true);
    assert.equal(controller.isVisible(), true);
    assert.equal(controller.layer.addedTo, fakeMap);

    // Actualización in-place sin recrear la instancia
    const updated = {
        type: 'FeatureCollection',
        features: [SAMPLE_GEOJSON.features[0]],
    };
    controller.update(updated);
    assert.equal(controller.layer.setLatLngsCalls, 1);
    assert.equal(controller.getPoints().length, 1);
    assert.equal(controller.getSummary().totalFebrileCases7d, 10);

    controller.setVisible(false);
    assert.equal(controller.isVisible(), false);
    assert.equal(controller.layer.removedCount, 1);

    controller.destroy();
    assert.equal(controller.layer.addedTo, null);
});
