/**
 * CEN-61: Capa poligonal coroplética GeoJSON de barrios de Formosa Capital.
 * Respeta tokens de riesgo de docs/specs/ui/spec.md §4 (sin colisionar con colores MTS)
 * y despacha el evento `open-neighborhood-detail` con `{ neighborhoodId }` (docs/specs/00_contracts.md §6).
 */

export const RISK_PALETTE = Object.freeze({
    low: Object.freeze({
        key: 'low',
        label: 'Riesgo Bajo',
        shortLabel: 'Bajo',
        fillColor: '#9FD8C6',
        borderColor: '#198754',
        badgeBg: '#E3F1F2',
        badgeText: '#0B4F55',
    }),
    moderate: Object.freeze({
        key: 'moderate',
        label: 'Riesgo Moderado',
        shortLabel: 'Moderado',
        fillColor: '#F4B860',
        borderColor: '#B35C00',
        badgeBg: '#FEF3C7',
        badgeText: '#92400E',
    }),
    high: Object.freeze({
        key: 'high',
        label: 'Riesgo Alto',
        shortLabel: 'Alto',
        fillColor: '#C2410C',
        borderColor: '#9A3412',
        badgeBg: '#FEE2E2',
        badgeText: '#991B1B',
    }),
});

const HIGHLIGHT_STYLE = Object.freeze({
    weight: 3,
    color: '#0A2E33',
    opacity: 1,
    fillOpacity: 0.8,
});

export function normalizeRiskLevel(rawRiskLevel) {
    const normalized = String(rawRiskLevel ?? '').trim().toLowerCase();

    if (['high', 'alto', 'alta', 'critical', 'critico', 'crítico'].includes(normalized)) {
        return 'high';
    }

    if (['moderate', 'mid', 'medium', 'moderado', 'moderada', 'medio', 'media'].includes(normalized)) {
        return 'moderate';
    }

    // Según docs/specs/00_contracts.md §2, 'normal' se mapea visualmente como 'low'.
    return 'low';
}

export function getRiskMeta(rawRiskLevel) {
    return RISK_PALETTE[normalizeRiskLevel(rawRiskLevel)];
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function formatRiskScorePercentage(riskScore) {
    if (riskScore === null || riskScore === undefined || riskScore === '') {
        return null;
    }

    const numeric = Number(riskScore);
    if (!Number.isFinite(numeric)) {
        return null;
    }

    const pct = numeric <= 1 ? numeric * 100 : numeric;
    return `${pct.toFixed(1).replace('.', ',')} %`;
}

function toNonNegativeInt(value) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return 0;
    }

    return Math.round(parsed);
}

export function extractNeighborhoodInfo(feature) {
    const props = feature?.properties ?? {};
    const riskMeta = getRiskMeta(props.risk_level);
    const rawId = props.id ?? feature?.id ?? null;
    const id = Number(rawId);

    return {
        id: Number.isInteger(id) && id > 0 ? id : null,
        name: typeof props.name === 'string' && props.name.trim() !== '' ? props.name.trim() : 'Barrio sin nombre',
        riskLevel: riskMeta.key,
        riskLabel: riskMeta.label,
        riskScore: props.risk_score !== undefined && props.risk_score !== null ? Number(props.risk_score) : null,
        febrileCases7d: toNonNegativeInt(props.febrile_cases_7d),
        totalConsultations7d: toNonNegativeInt(props.total_consultations_7d),
        riskUpdatedAt: props.risk_updated_at ?? null,
    };
}

export function getChoroplethFeatureStyle(feature) {
    const riskMeta = getRiskMeta(feature?.properties?.risk_level);

    return {
        fillColor: riskMeta.fillColor,
        color: riskMeta.borderColor,
        weight: 1.5,
        opacity: 0.92,
        fillOpacity: 0.58,
    };
}

export function buildNeighborhoodTooltipHtml(properties = {}) {
    const riskMeta = getRiskMeta(properties.risk_level);
    const safeName = escapeHtml(properties.name || 'Barrio sin nombre');
    const febrile = toNonNegativeInt(properties.febrile_cases_7d);
    const total = toNonNegativeInt(properties.total_consultations_7d);
    const scoreFormatted = formatRiskScorePercentage(properties.risk_score);

    const scoreRow = scoreFormatted !== null
        ? `<div>Probabilidad predicha: <strong>${escapeHtml(scoreFormatted)}</strong></div>`
        : '';

    return [
        `<div class="centinela-map-tooltip__title">${safeName}</div>`,
        `<div class="centinela-map-tooltip__badge" style="background:${riskMeta.badgeBg};color:${riskMeta.badgeText};">`,
        `<span style="display:inline-block;width:8px;height:8px;border-radius:9999px;background:${riskMeta.fillColor};border:1px solid ${riskMeta.borderColor};"></span>`,
        `${escapeHtml(riskMeta.label)}`,
        '</div>',
        '<div class="centinela-map-tooltip__meta">',
        `<div>Casos febriles (7 días): <strong>${febrile}</strong></div>`,
        `<div>Consultas totales (7 días): <strong>${total}</strong></div>`,
        scoreRow,
        '</div>',
        '<div class="centinela-map-tooltip__hint">Clic para ver detalle del barrio</div>',
    ].join('');
}

export function dispatchNeighborhoodDetail(neighborhoodId, targetWindow = globalThis.window) {
    const id = Number(neighborhoodId);
    if (!Number.isInteger(id) || id <= 0) {
        return false;
    }

    if (targetWindow?.Livewire && typeof targetWindow.Livewire.dispatch === 'function') {
        targetWindow.Livewire.dispatch('open-neighborhood-detail', { neighborhoodId: id });
        return true;
    }

    if (targetWindow && typeof targetWindow.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
        targetWindow.dispatchEvent(
            new CustomEvent('open-neighborhood-detail', {
                detail: { neighborhoodId: id },
                bubbles: true,
            }),
        );
        return true;
    }

    return false;
}

export function sanitizeFeatureCollection(featureCollection) {
    const rawFeatures = Array.isArray(featureCollection?.features) ? featureCollection.features : [];

    return {
        type: 'FeatureCollection',
        features: rawFeatures.filter(
            (feature) => Boolean(feature) && Boolean(feature.geometry) && typeof feature.geometry.type === 'string',
        ),
    };
}

export function summarizeRiskDistribution(featureCollection) {
    const sanitized = sanitizeFeatureCollection(featureCollection);
    const summary = {
        total: sanitized.features.length,
        low: 0,
        moderate: 0,
        high: 0,
    };

    for (const feature of sanitized.features) {
        const level = normalizeRiskLevel(feature?.properties?.risk_level);
        summary[level] += 1;
    }

    return summary;
}

export function createChoroplethLayer(L, featureCollection, options = {}) {
    const sanitized = sanitizeFeatureCollection(featureCollection);
    const targetWindow = options.targetWindow ?? (typeof globalThis.window !== 'undefined' ? globalThis.window : null);

    let geoJsonLayer = null;

    geoJsonLayer = L.geoJSON(sanitized, {
        style: (feature) => getChoroplethFeatureStyle(feature),
        onEachFeature: (feature, layer) => {
            const info = extractNeighborhoodInfo(feature);

            if (typeof layer.bindTooltip === 'function') {
                layer.bindTooltip(buildNeighborhoodTooltipHtml(feature.properties), {
                    sticky: true,
                    direction: 'top',
                    className: 'centinela-map-tooltip',
                    opacity: 0.98,
                });
            }

            if (typeof layer.on === 'function') {
                layer.on({
                    mouseover: (event) => {
                        const target = event?.target ?? layer;
                        if (typeof target.setStyle === 'function') {
                            target.setStyle(HIGHLIGHT_STYLE);
                        }
                        if (typeof target.bringToFront === 'function') {
                            target.bringToFront();
                        }
                        options.onHover?.(info);
                    },
                    mouseout: (event) => {
                        const target = event?.target ?? layer;
                        if (geoJsonLayer && typeof geoJsonLayer.resetStyle === 'function') {
                            geoJsonLayer.resetStyle(target);
                        }
                        options.onHover?.(null);
                    },
                    click: () => {
                        if (info.id !== null) {
                            dispatchNeighborhoodDetail(info.id, targetWindow);
                        }
                        options.onSelect?.(info);
                    },
                });
            }
        },
    });

    return geoJsonLayer;
}
