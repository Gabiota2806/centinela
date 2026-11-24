# 📦 Especificación Modular: Clima, Telemetría Anonimizada, Motor Predictivo y Visor Geoespacial

- **Módulo:** `geo` (Clima + Telemetría Edge-to-Hub + ML + Visor PostGIS/Leaflet)
- **Estado:** `En Implementación`
- **Última Actualización:** 2026-11-24
- **Contratos base:** `docs/specs/00_contracts.md`, `docs/specs/global_spec.md`
- **Historias y Subtareas asociadas:**
  - `HU05` (`CEN-28`): Ingestión Meteorológica Automatizada (`CEN-48`, `CEN-49`, `CEN-50`)
  - `HU06` (`CEN-29`): Telemetría Epidemiológica Asíncrona y Anonimización Ley 25.326 (`CEN-51`, `CEN-52`, `CEN-53`)
  - `HU07` (`CEN-30`): Predicción de Riesgo de Brote Epidemiológico con Scikit-Learn (`CEN-54`, `CEN-55`, `CEN-56`, `CEN-57`, `CEN-58`, `CEN-59`)
  - `HU08` (`CEN-31`): Mapa Interactivo de Calor por Barrios en Leaflet.js (`CEN-60`, `CEN-61`, `CEN-62`, `CEN-63`, `CEN-64`, `CEN-65`)

---

## 1. Alcance y Propósito del Módulo

Conecta los eventos clínicos anonimizados del nodo local CAPS con el Hub Analítico Central (FastAPI + Scikit-Learn) y variables meteorológicas de Formosa Capital (Open-Meteo) para clasificar el riesgo epidemiológico por barrio (`low`, `moderate`, `high`) y representarlo geoespacialmente sobre PostGIS + Leaflet.js, cumpliendo estrictamente con el funcionamiento *Offline-First* y la Ley Nacional N° 25.326.

---

## 2. Requerimientos Funcionales (EARS)

### Ingestión Meteorológica y Parámetros (`HU05`)
- **RF-GEO-01 (`CEN-48`):** `App\Services\Weather\OpenMeteoService` debe consultar Open-Meteo para Formosa Capital (`latitude=-26.18`, `longitude=-58.17`), obteniendo `precipitation_sum`, `temperature_2m_max`, `temperature_2m_min` y `relative_humidity_2m` (media diaria) y persistiendo de forma idempotente en `weather_records` por `recorded_date`.
- **RF-GEO-02 (`CEN-49`):** El comando `FetchDailyWeatherCommand` (programado diariamente a las 06:00 en `routes/console.php`) debe almacenar en caché de contingencia los últimos 7 días ante caídas de red, degradando sin fallos fatales cuando el nodo CAPS opera offline.
- **RF-GEO-03 (`CEN-50`):** El componente `App\Livewire\Admin\EpidemiologySettings` (ruta `/epidemiologia/configuracion`, protegido por `can:access-epidemiology`) debe permitir configurar y persistir en `epidemiology_settings` el umbral de lluvia (`rain_threshold_mm`), ventana de desfase (`time_lag_days`, 7–14 días), umbral de casos febriles y probabilidad de alerta alta.

### Telemetría Outbox, Anonimización y Hub FastAPI (`HU06`)
- **RF-GEO-04 (`CEN-51`):** Al escucharse `App\Events\ConsultationCompleted`, `App\Services\Telemetry\AnonymizerService` debe construir un payload estrictamente disociado según `docs/specs/00_contracts.md` §4.1 (solo `neighborhood_id`, `neighborhood_name`, `age_group`, `triage_priority`, `presumptive_diagnosis`, `symptoms_summary`, `temperature_c`, `recorded_at`, `caps_code`, `event_uuid`), excluyendo de forma irreversible DNI, nombre, apellido, teléfono, domicilio y fecha de nacimiento, y registrarlo en `outbox_telemetry_events` con `status = 'pending'`.
- **RF-GEO-05 (`CEN-51`):** `App\Jobs\DispatchTelemetryJob` debe enviar eventos pendientes hacia `POST /api/v1/telemetry` con cabecera `X-CAPS-Key`. Ante indisponibilidad de red o error HTTP 5xx, debe conservar el evento en `outbox_telemetry_events`, incrementar `attempts`, registrar `last_error` y reintentar con retroceso exponencial sin afectar la atención clínica.
- **RF-GEO-06 (`CEN-52`):** El microservicio `backend_analytics/` (Python 3.12 + FastAPI) debe exponer `POST /api/v1/telemetry` con validación Pydantic estricta (`extra = "forbid"`), verificación de `X-CAPS-Key` (`401` si inválida) y persistencia idempotente en la base aislada `centinela_hub`.

### Motor Predictivo Scikit-Learn y Alertas (`HU07`)
- **RF-GEO-07 (`CEN-54`, `CEN-55`, `CEN-56`):** El pipeline en `backend_analytics/pipeline/` y `backend_analytics/ml/` debe agrupar series temporales por semana epidemiológica y barrio, generar variables con retardo lluvia-casos (`rain_lag_7d`, `rain_lag_14d`, `temp_mean_lag_7d`), entrenar un `RandomForestClassifier` (3 clases: `0=low`, `1=moderate`, `2=high`) —incluyendo generador reproducible de dataset sintético epidemiológico de Formosa cuando no haya histórico real— y serializar el artefacto en `backend_analytics/models/risk_classifier.pkl` con `joblib`.
- **RF-GEO-08 (`CEN-57`):** `backend_analytics/routers/predict.py` debe servir `GET /api/v1/neighborhoods/{id}/risk` y `GET /api/v1/neighborhoods/risks` cargando el modelo serializado en memoria para responder en `< 200 ms` con el vector de probabilidades y nivel de riesgo según `docs/specs/00_contracts.md` §4.2.
- **RF-GEO-09 (`CEN-58`):** `App\Services\Analytics\PredictiveRiskClient` y `SyncNeighborhoodRiskCommand` deben sincronizar los niveles de riesgo retornados por FastAPI en `neighborhoods` (`risk_level`, `risk_score`, `risk_updated_at`), manejando desconexión del Hub sin corromper los datos locales previos.
- **RF-GEO-10 (`CEN-59`):** `App\Livewire\Dashboard\EpidemiologicalAlerts` debe visualizar banners de alerta preventiva, ranking de barrios por riesgo (`low`/`moderate`/`high`), métricas de casos febriles recientes y resumen climático usando SVGs vectoriales exclusivos.

### Visor Geoespacial PostGIS y Leaflet.js (`HU08`)
- **RF-GEO-11 (`CEN-63`):** `App\Http\Controllers\Api\GeoSpatialController` debe exponer `GET /api/v1/geo/neighborhoods` utilizando `ST_AsGeoJSON()` en PostgreSQL/PostGIS para entregar la `FeatureCollection` de los 105 barrios de Formosa Capital con sus propiedades de riesgo y casos febriles de los últimos 7 días.
- **RF-GEO-12 (`CEN-60`, `CEN-61`, `CEN-62`, `CEN-64`, `CEN-65`):** El visor `resources/views/components/leaflet-map.blade.php` (`resources/js/{map,choropleth,heatmap}.js`) centrado en Formosa Capital (`[-26.18, -58.17]`, zoom 13) debe renderizar capas coropléticas coloreadas por nivel de riesgo, capa de calor (`leaflet.heat`) conmutables, y abrir `NeighborhoodDetailModal` al hacer clic en cada barrio mostrando consultas acumuladas, nivel de alerta, clima reciente y recomendaciones operativas.

---

## 3. Criterios de Aceptación (Gherkin)

```gherkin
Escenario: Disociación irreversible y resiliencia offline del Outbox (Ley N° 25.326)
  Dado un paciente "Juan Pérez" con DNI "38123456" cuya consulta finaliza con diagnóstico "sospecha_dengue"
  Cuando se dispara el evento "ConsultationCompleted" y el Hub FastAPI no responde
  Entonces se crea un registro en "outbox_telemetry_events" en estado "pending"
  Y el campo JSON "payload" no contiene DNI, nombre, apellido, teléfono ni domicilio
  Y al restablecerse el Hub, "DispatchTelemetryJob" entrega el evento y lo marca como "sent".

Escenario: Rechazo de payload con datos personales o clave inválida en FastAPI
  Dado un cliente externo que envía una solicitud a "POST /api/v1/telemetry"
  Cuando omite o envía una cabecera "X-CAPS-Key" inválida
  Entonces FastAPI responde HTTP 401
  Cuando envía una clave válida pero incluye el campo "dni" en el JSON
  Entonces FastAPI rechaza la solicitud con HTTP 422 sin persistir datos.

Escenario: Endpoint espacial PostGIS y renderizado de riesgo barrial
  Dado que existen barrios cargados en "neighborhoods" con geometría PostGIS y nivel de riesgo asignado
  Cuando un usuario autenticado solicita "GET /api/v1/geo/neighborhoods"
  Entonces recibe HTTP 200 con un GeoJSON "FeatureCollection" válido
  Y cada Feature contiene "risk_level", "risk_score", "febrile_cases_7d" y geometría poligonal válida.

Escenario: Fallback climático ante corte de conectividad externa
  Dado que Open-Meteo se encuentra inaccesible
  Cuando se ejecuta "php artisan weather:fetch-daily"
  Entonces el comando registra el incidente en logs sin lanzar excepción fatal
  Y el sistema continúa sirviendo los últimos 7 días desde caché/base local.
```
