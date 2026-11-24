# 📐 Contratos Técnicos Transversales (Sprints 4 a 7)

- **Estado:** `Congelado (Contract-First)`
- **Última Actualización:** 2026-11-24
- **Alcance:** Interfaces estables entre el Core Clínico Local (Laravel/Livewire), la Capa de Ingestión Meteorológica, el Hub Analítico Central (FastAPI/Scikit-Learn) y el Visor Geoespacial (PostGIS/Leaflet.js).

> Todo desarrollo de los módulos de Consultas (`docs/specs/consultas/spec.md`) y Geoespacial/Telemetría (`docs/specs/geo/spec.md`) debe respetar estrictamente los enums, eventos, tablas y contratos JSON aquí definidos.

---

## 1. Máquina de Estados Clínicos y Evento de Dominio

### 1.1. Enum `App\Enums\TriageStatus`
- `pending_consultation` (*En espera de consulta*)
- `called` (*Llamado a consultorio*)
- `in_consultation` (*En consulta*)
- `completed` (*Completado*)
- `cancelled` (*Cancelado*)

### 1.2. Transiciones Válidas
```text
pending_consultation ──▶ called
called               ──▶ in_consultation   (crea Consultation en status = 'in_progress', started_at = now())
called               ──▶ pending_consultation (conserva created_at FIFO original)
called               ──▶ cancelled         (ausente/retirado; libera bloqueo para nueva admisión)
in_consultation      ──▶ completed         (Consultation pasa a 'completed' o 'referred_sipec', completed_at = now())
```
- **Bloqueo de admisión recurrente:** Un paciente se considera con episodio activo mientras posea un triage en `pending_consultation`, `called` o `in_consultation`.
- **Ordenamiento canónico de cola activa:** `CASE final_priority WHEN 'red' THEN 1 WHEN 'orange' THEN 2 WHEN 'yellow' THEN 3 WHEN 'green' THEN 4 WHEN 'blue' THEN 5 ELSE 6 END, created_at ASC, id ASC`.

### 1.3. Evento de Dominio `App\Events\ConsultationCompleted`
- **Clase:** `App\Events\ConsultationCompleted`
- **Propiedades públicas (`readonly`):** `Consultation $consultation`, `Triage $triage`.
- **Emisor:** `App\Livewire\Doctor\ConsultationRoom` al confirmar el cierre de consulta (`HU04`).
- **Consumidor:** Listener del módulo de telemetría (`HU06`), que invoca `AnonymizerService` y encola un registro en `outbox_telemetry_events` dentro del flujo asíncrono sin bloquear el consultorio.

---

## 2. Esquema Compartido de Base de Datos (Migración Base)

La migración `2026_11_24_000001_add_contracts_schema_for_sprints_4_to_7.php` garantiza las siguientes estructuras:

| Tabla | Columnas / Índices Clave | Uso |
| :--- | :--- | :--- |
| `triages` | Índice compuesto `idx_triages_active_queue (status, final_priority, created_at)` | Optimización de cola médica (`< 20 ms`). |
| `neighborhoods` | `risk_level` (`string`, default `'normal'`), `risk_score` (`decimal(5,4)` nullable), `risk_updated_at` (`timestamp` nullable) | Nivel de riesgo predictivo y lectura directa para mapa y alertas. Valores canónicos: `low`, `moderate`, `high` (`normal` se mapea visualmente como `low`). |
| `weather_records` | `recorded_date` (`unique`), `latitude` (`-26.1800`), `longitude` (`-58.1700`), `temperature_max`, `temperature_min`, `precipitation_sum`, `relative_humidity_mean`, `source`, `raw_payload` (`jsonb`) | Serie climática diaria de Open-Meteo (Formosa Capital). |
| `epidemiology_settings` | `rain_threshold_mm` (`40.00`), `time_lag_days` (`10`), `febrile_alert_threshold` (`5`), `high_risk_probability` (`0.70`), `updated_by` (`users.id`) | Parámetros operativos configurables de alerta epidemiológica. |
| `outbox_telemetry_events` | `event_uuid` (`uuid unique`), `consultation_id` (`nullable`), `payload` (`jsonb`), `status` (`pending\|sent\|failed`), `attempts`, `last_error`, `sent_at`, índice `idx_outbox_pending_queue (status, created_at)` | Patrón Outbox *Offline-First* para sincronización asíncrona con FastAPI. |

---

## 3. Topología Aislada del Hub Analítico (`backend_analytics/`)

Para sostener el aislamiento físico y normativo (**Ley N° 25.326**) dentro del repositorio multi-servicio:
1. **Directorio y contenedor propios:** `backend_analytics/` con su `Dockerfile` (Python 3.12 + FastAPI + Pydantic + Pandas + Scikit-Learn) expuesto en el servicio Docker `hub_analytics` (puerto `8001`).
2. **Base de datos aislada:** Base PostgreSQL `centinela_hub` (esquema `hub`) bajo rol `hub_user` sin privilegios de conexión ni lectura sobre la base clínica `centinela` (`docker/init-testing-db.sql`).
3. **Comunicación exclusiva HTTP REST:** Autenticación obligatoria de nodo emisor mediante cabecera `X-CAPS-Key` (variable `CENTINELA_CAPS_API_KEY`). Ningún servicio accede a tablas del otro.

---

## 4. Contratos HTTP / JSON

### 4.1. Ingesta de Telemetría Anonimizada (Hub FastAPI)
- **Endpoint:** `POST /api/v1/telemetry`
- **Headers obligatorios:** `X-CAPS-Key: <clave>`, `Content-Type: application/json`
- **Request JSON (estrictamente disociado):**
  ```json
  {
    "event_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "caps_code": "CAPS-CODDA-01",
    "neighborhood_id": 14,
    "neighborhood_name": "San Miguel",
    "age_group": "adulto",
    "triage_priority": "yellow",
    "presumptive_diagnosis": "sospecha_dengue",
    "symptoms_summary": "fiebre_mialgias_cefalea",
    "temperature_c": 39.2,
    "recorded_at": "2026-11-24T15:30:00-03:00"
  }
  ```
- **Regla de Privacidad (Ley N° 25.326):** El esquema Pydantic debe operar con `extra = "forbid"` y rechazar (`422 Unprocessable Entity`) cualquier payload que contenga claves identificatorias (`dni`, `first_name`, `last_name`, `full_name`, `phone`, `address`, `birth_date`).
- **Respuestas:**
  - `202 Accepted` / `201 Created`: `{"status": "accepted", "event_uuid": "..."}` (idempotente ante `event_uuid` repetido).
  - `401 Unauthorized`: cabecera `X-CAPS-Key` ausente o inválida.
  - `422 Unprocessable Entity`: validación Pydantic fallida o campo prohibido detectado.

### 4.2. Inferencia de Riesgo Barrial (Hub FastAPI)
- **Endpoint individual:** `GET /api/v1/neighborhoods/{id}/risk`
- **Endpoint batch (sincronización eficiente):** `GET /api/v1/neighborhoods/risks`
- **Header:** `X-CAPS-Key: <clave>`
- **Response `200 OK` (individual):**
  ```json
  {
    "neighborhood_id": 14,
    "risk_class": 2,
    "risk_level": "high",
    "risk_score": 0.8421,
    "probabilities": {
      "low": 0.0510,
      "moderate": 0.1069,
      "high": 0.8421
    },
    "model_version": "rf-v1.0",
    "predicted_at": "2026-11-24T15:35:00-03:00"
  }
  ```
- **Response `200 OK` (batch):** `{"items": [ ...objetos iguales al individual... ], "model_version": "rf-v1.0", "predicted_at": "..."}`.

### 4.3. Capa Geoespacial Barrial (Laravel + PostGIS)
- **Endpoint:** `GET /api/v1/geo/neighborhoods` (protegido por middleware `auth`; accesible para roles `director`, `doctor` y `triage_nurse` en modo lectura).
- **Query SQL base:** `ST_AsGeoJSON(geometry)` + centroide `ST_Y(ST_Centroid(geometry))` / `ST_X(ST_Centroid(geometry))` + conteo de consultas febriles/totales últimos 7 días.
- **Response `200 OK` (GeoJSON `FeatureCollection`):**
  ```json
  {
    "type": "FeatureCollection",
    "features": [
      {
        "type": "Feature",
        "id": 14,
        "geometry": { "type": "MultiPolygon", "coordinates": [] },
        "properties": {
          "id": 14,
          "name": "San Miguel",
          "risk_level": "high",
          "risk_score": 0.8421,
          "febrile_cases_7d": 12,
          "total_consultations_7d": 28,
          "risk_updated_at": "2026-11-24T15:35:00-03:00",
          "centroid_lat": -26.1842,
          "centroid_lng": -58.1731
        }
      }
    ]
  }
  ```

---

## 5. Catálogos Canónicos de Dominio

### 5.1. Diagnósticos Presuntivos Estandarizados (`consultations.diagnosis`)
| Código | Etiqueta Clínica | Febril / Vigilancia |
| :--- | :--- | :---: |
| `sindrome_febril_inespecifico` | Síndrome Febril Agudo Inespecífico (SFI) | Sí |
| `sospecha_dengue` | Sospecha de Dengue / Arbovirosis | Sí |
| `ira` | Infección Respiratoria Aguda (IRA) | Sí |
| `gastroenteritis` | Gastroenteritis Aguda | No |
| `traumatismo_herida` | Traumatismo / Herida | No |
| `hipertension_cardiovascular` | Crisis Hipertensiva / Cardiovascular | No |
| `otro_clinico` | Otra Patología Clínica / Control | No |

### 5.2. Grupos Etarios Anonimizados (`age_group`)
- `infante`: `< 12` años
- `adolescente`: `12` a `17` años
- `adulto`: `18` a `59` años
- `mayor`: `>= 60` años

---

## 6. Rutas Web, Gates y Eventos Livewire Compartidos

| Ruta | Nombre | Middleware / Gate | Componente |
| :--- | :--- | :--- | :--- |
| `/consultas` | `consultations.index` | `auth`, `verified`, `can:access-doctor` | `App\Livewire\Doctor\MedicalQueue` |
| `/consultas/{triage}` | `consultations.show` | `auth`, `verified`, `can:access-doctor` | `App\Livewire\Doctor\ConsultationRoom` |
| `/epidemiologia/configuracion` | `epidemiology.settings` | `auth`, `verified`, `can:access-epidemiology` | `App\Livewire\Admin\EpidemiologySettings` |
| `/dashboard` | `dashboard` | `auth`, `verified` | Integra `EpidemiologicalAlerts`, mapa `leaflet-map` y `NeighborhoodDetailModal` |

**Eventos Livewire / Alpine estándar:**
- `patient-triaged`, `triage-overridden`, `triage-updated` (actualizan `MedicalQueue` y `EvaluatePatient`).
- `open-clinical-override` con `{ triageId: int }` (abre `ClinicalOverrideModal`).
- `open-neighborhood-detail` con `{ neighborhoodId: int }` (abre `NeighborhoodDetailModal` al hacer clic en un polígono de Leaflet).
