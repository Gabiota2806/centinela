/**
 * CEN-62: Capa de calor (Heatmap) basada en densidad espacial de casos febriles recientes.
 * Utiliza `leaflet.heat` (empaquetado localmente con Vite) sobre los centroides de los barrios
 * entregados por GET /api/v1/geo/neighborhoods (docs/specs/00_contracts.md §4.3).
 */

export const DEFAULT_HEATMAP_OPTIONS = Object.freeze({
    radius: 34,
    blur: 24,
    maxZoom: 13,
    max: 1.0,
    minOpacity: 0.32,
    referenceThreshold: 5,
    minPointIntensity: 0.18,
    gradient: Object.freeze({
        0.2: '#9FD8C6',
        0.45: '#F4B860',
        0.75: '#EA580C',
        1.0: '#C2410C',
    }),
});

function isValidLatLng(lat, lng) {
    return (
        Number.isFinite(lat)
        && Number.isFinite(lng)
        && lat >= -90
        && lat <= 90
        && lng >= -180
        && lng <= 180
    );
}

function collectRingCoordinates(geometry) {
    if (!geometry || typeof geometry.type !== 'string' || !geometry.coordinates) {
        return [];
    }

    if (geometry.type === 'Point' && Array.isArray(geometry.coordinates)) {
        return [geometry.coordinates];
    }

    if (geometry.type === 'Polygon' && Array.isArray(geometry.coordinates[0])) {
        return geometry.coordinates[0];
    }

    if (geometry.type === 'MultiPolygon' && Array.isArray(geometry.coordinates)) {
        const points = [];
        for (const polygon of geometry.coordinates) {
            if (Array.isArray(polygon?.[0])) {
                points.push(...polygon[0]);
            }
        }
        return points;
    }

    return [];
}

export function extractFeatureCentroid(feature) {
    const props = feature?.properties ?? {};
    if (
        props.centroid_lat !== undefined
        && props.centroid_lat !== null
        && props.centroid_lat !== ''
        && props.centroid_lng !== undefined
        && props.centroid_lng !== null
        && props.centroid_lng !== ''
    ) {
        const lat = Number(props.centroid_lat);
        const lng = Number(props.centroid_lng);
        if (isValidLatLng(lat, lng)) {
            return [lat, lng];
        }
    }

    const coords = collectRingCoordinates(feature?.geometry);
    if (coords.length === 0) {
        return null;
    }

    let minLat = Infinity;
    let maxLat = -Infinity;
    let minLng = Infinity;
    let maxLng = -Infinity;

    for (const pair of coords) {
        if (!Array.isArray(pair) || pair.length < 2) {
            continue;
        }
        const lng = Number(pair[0]);
        const lat = Number(pair[1]);
        if (!isValidLatLng(lat, lng)) {
            continue;
        }
        if (lat < minLat) minLat = lat;
        if (lat > maxLat) maxLat = lat;
        if (lng < minLng) minLng = lng;
        if (lng > maxLng) maxLng = lng;
    }

    if (!Number.isFinite(minLat) || !Number.isFinite(minLng)) {
        return null;
    }

    return [
        Number(((minLat + maxLat) / 2).toFixed(6)),
        Number(((minLng + maxLng) / 2).toFixed(6)),
    ];
}

function toNonNegativeCount(raw) {
    const num = Number(raw);
    if (!Number.isFinite(num) || num <= 0) {
        return 0;
    }

    return num;
}

export function buildHeatmapPoints(featureCollection, options = {}) {
    const referenceThreshold = Math.max(
        1,
        Number(options.referenceThreshold ?? DEFAULT_HEATMAP_OPTIONS.referenceThreshold),
    );
    const minPointIntensity = Math.min(
        1,
        Math.max(0.05, Number(options.minPointIntensity ?? DEFAULT_HEATMAP_OPTIONS.minPointIntensity)),
    );

    const features = Array.isArray(featureCollection?.features) ? featureCollection.features : [];
    const weightedCandidates = [];
    let totalFebrileCases7d = 0;
    let maxFebrileCases7d = 0;

    for (const feature of features) {
        const febrileCases = toNonNegativeCount(feature?.properties?.febrile_cases_7d);
        if (febrileCases <= 0) {
            continue;
        }

        const centroid = extractFeatureCentroid(feature);
        if (!centroid) {
            continue;
        }

        totalFebrileCases7d += febrileCases;
        if (febrileCases > maxFebrileCases7d) {
            maxFebrileCases7d = febrileCases;
        }

        weightedCandidates.push({
            lat: centroid[0],
            lng: centroid[1],
            febrileCases,
        });
    }

    const scaleDenominator = Math.max(maxFebrileCases7d, referenceThreshold);
    const points = weightedCandidates.map(({ lat, lng, febrileCases }) => {
        const rawIntensity = febrileCases / scaleDenominator;
        const clamped = Math.min(1, Math.max(minPointIntensity, rawIntensity));
        return [lat, lng, Number(clamped.toFixed(4))];
    });

    return {
        points,
        summary: {
            hotspotCount: points.length,
            totalFebrileCases7d: Math.round(totalFebrileCases7d),
            maxFebrileCases7d: Math.round(maxFebrileCases7d),
        },
    };
}

async function loadHeatLayerClass() {
    const module = await import('leaflet.heat');
    if (typeof module.HeatLayer === 'function') {
        return module.HeatLayer;
    }

    if (typeof module.default === 'function') {
        return module.default;
    }

    throw new Error('No se pudo cargar HeatLayer desde leaflet.heat.');
}

function hasPositiveMapCanvasSize(map) {
    if (!map || typeof map.getSize !== 'function') {
        return true;
    }

    const size = map.getSize();
    return Boolean(size) && Number(size.x) > 0 && Number(size.y) > 0;
}

export async function createHeatmapController(map, featureCollection, options = {}) {
    const HeatLayerClass = options.HeatLayerClass ?? (await loadHeatLayerClass());
    const layerOptions = {
        radius: options.radius ?? DEFAULT_HEATMAP_OPTIONS.radius,
        blur: options.blur ?? DEFAULT_HEATMAP_OPTIONS.blur,
        maxZoom: options.maxZoom ?? DEFAULT_HEATMAP_OPTIONS.maxZoom,
        max: options.max ?? DEFAULT_HEATMAP_OPTIONS.max,
        minOpacity: options.minOpacity ?? DEFAULT_HEATMAP_OPTIONS.minOpacity,
        gradient: options.gradient ?? { ...DEFAULT_HEATMAP_OPTIONS.gradient },
    };

    let current = buildHeatmapPoints(featureCollection, options);
    let visible = false;
    let destroyed = false;

    const layer = new HeatLayerClass(current.points, layerOptions);

    function setVisible(nextVisible) {
        if (destroyed) {
            return;
        }

        const shouldShow = Boolean(nextVisible);
        if (shouldShow === visible) {
            return;
        }

        if (shouldShow) {
            if (!hasPositiveMapCanvasSize(map)) {
                return;
            }
            if (typeof layer.addTo === 'function') {
                layer.addTo(map);
                visible = true;
            }
        } else if (typeof layer.remove === 'function') {
            layer.remove();
            visible = false;
        }
    }

    function update(nextFeatureCollection) {
        if (destroyed) {
            return current.summary;
        }

        current = buildHeatmapPoints(nextFeatureCollection, options);
        if (typeof layer.setLatLngs === 'function') {
            layer.setLatLngs(current.points);
        }

        return current.summary;
    }

    function destroy() {
        if (destroyed) {
            return;
        }
        destroyed = true;
        if (visible && typeof layer.remove === 'function') {
            layer.remove();
        }
        visible = false;
    }

    if (options.visible) {
        setVisible(true);
    }

    return {
        layer,
        isVisible: () => visible,
        getPoints: () => current.points.map((point) => [...point]),
        getSummary: () => ({ ...current.summary }),
        setVisible,
        update,
        destroy,
    };
}
