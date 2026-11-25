# Guía Interna del Equipo: Flujo Paralelo por HU y Asignación de Ramas

> **DOCUMENTO DE USO INTERNO DEL EQUIPO (EXCLUIR EN EL RELEASE FINAL A `main`).**
> Centraliza el flujo operativo rápido hacia el Code Freeze (**02/12/2026**) y la Defensa (**08/12/2026**), las ramas exactas que corresponden a cada integrante y los criterios de asignación de Historias de Usuario.

---

## 1. Reglas de Oro del Flujo Paralelo (5 Pasos)

1. **1 Rama = 1 Dueño = 1 HU o Sub-vertical Completa:**
   - Buscá tu siguiente fila en tu tabla de la **Sección 2** y copiá el nombre exacto de rama.
   - Todas las subtareas listadas para esa rama las implementa el mismo responsable (cero solapamiento de archivos entre compañeros).
   - Al crear la rama y subirla (`git push -u origin <rama>`), Jira pasa automáticamente la tarjeta principal a **En curso**.
2. **Contratos Congelados Obligatorios:**
   - Antes de programar, consultá `docs/specs/00_contracts.md` y la spec del módulo (`docs/specs/consultas/spec.md`, `docs/specs/geo/spec.md`, `docs/specs/triage/spec.md` o `docs/specs/pacientes/spec.md`).
   - El comando `/desarrollo-tarea <CLAVE_RAMA> local` lee automáticamente `.github/jira-scopes.conf` para conocer todas las subtareas de tu rama.
3. **1 Commit por Subtarea Terminada (con su propia clave `CEN-xxx`):**
   - Cada vez que terminás una subtarea dentro de tu rama (con `docker compose exec app php artisan test` 100% verde), hacés un commit referenciando esa subtarea:
     - Ejemplo en rama `CEN-28-ingestion-meteorologica-open-meteo`:
       * `feat(CEN-48): cliente HTTP Open-Meteo y persistencia en weather_records`
       * `feat(CEN-49): comando programado diario con caché de contingencia de 7 días`
       * `feat(CEN-50): vista reactiva de configuración de parámetros epidemiológicos`
   - **No edites `docs/backlog.md` ni `docs/changelog.md` dentro de tu rama de feature** (se consolidan en `develop` al final del día para eliminar conflictos de merge).
4. **Pruebas y Auto-Auditoría Obligatorias Antes de Abrir la PR:**
   - Ejecutá `docker compose exec app php artisan test` y `docker compose exec app ./vendor/bin/pint --test` (100% verde).
   - Seguí la **Guía de QA Manual** entregada por el agente en `http://localhost:8000` (más cualquier prueba adicional de borde que quieras validar).
   - Ejecutá **`/auditoria` (o `@reviewer`) con tu propio agente** sobre tu rama y pegá el veredicto (`VEREDICTO: APROBADO`) en la descripción de la Pull Request.
5. **Cero Esperas al Abrir la PR:**
   - Apenas abrís la PR hacia `develop` (Jira pasa las tarjetas a **En revisión**), **no esperás a nadie**: volvés a `develop`, creás la siguiente rama de tu tabla y seguís trabajando.
   - **Cualquier compañero disponible (o Gabriel)** verifica en GitHub en 5–10 min que:
     1. GitHub Actions (`CI` + `Jira Key Guard`) estén en verde.
     2. El reporte de auto-auditoría (`/auditoria`) y el checklist de QA estén completos.
     3. No haya violaciones de contratos, RBAC, Ley N° 25.326 ni emojis en UI.
     4. Aprueba y hace Merge a `develop` (Jira pasa las tarjetas a **Listo**). **Nunca hace auto-merge un agente.**

---

## 2. Qué Rama le Corresponde a Cada Integrante (Orden de Ejecución)

> **Comando para iniciar cualquier rama:**
> ```bash
> git checkout develop && git pull origin develop && git checkout -b <NOMBRE_DE_RAMA>
> ```
> **Activar hooks locales (una sola vez por PC):**
> ```bash
> git config core.hooksPath .githooks
> ```

### Enrique Maximiliano González (Backend, Clima, Telemetría/FastAPI, PostGIS y Seguridad — 82 SP totales / 54 SP pend.)

| Orden | Comando `/desarrollo-tarea` | Nombre Exacto de Rama | Claves Permitidas en Commits/PR | Alcance / Archivos Exclusivos | Spec |
| :---: | :--- | :--- | :--- | :--- | :--- |
| **1** | `/desarrollo-tarea CEN-73 local` | `CEN-73-rbac-admision-print-ticket-padron` | `CEN-73` | `AppServiceProvider`, `routes/web.php`, `PrintTicket`, `PatientDirectory` (`/pacientes`) | `auth`, `pacientes` |
| **2** | `/desarrollo-tarea CEN-28 local` | `CEN-28-ingestion-meteorologica-open-meteo` | `CEN-28`, `CEN-48`, `CEN-49`, `CEN-50` | `OpenMeteoService`, `WeatherRecord`, `FetchDailyWeatherCommand`, `routes/console.php`, `EpidemiologySettings` | `00_contracts`, `geo` |
| **3** | `/desarrollo-tarea CEN-29 local` | `CEN-29-telemetria-outbox-fastapi` | `CEN-29`, `CEN-51`, `CEN-52` | `AnonymizerService`, `DispatchTelemetryJob`, listener `ConsultationCompleted`, base `backend_analytics/` (`POST /api/v1/telemetry`, `X-CAPS-Key`, BD `centinela_hub`) | `00_contracts`, `geo` |
| **4** | `/desarrollo-tarea CEN-57 local` | `CEN-57-api-inferencia-sync-postgis` | `CEN-57`, `CEN-58`, `CEN-63` | `backend_analytics/routers/predict.py`, `PredictiveRiskClient`, `SyncNeighborhoodRiskCommand`, `GeoSpatialController` (`GET /api/v1/geo/neighborhoods`) | `00_contracts`, `geo` |
| **5** | `/desarrollo-tarea CEN-67 local` | `CEN-67-hardening-owasp-green-software` | `CEN-67`, `CEN-69` | `SecurityHeadersMiddleware`, `docs/security/owasp_audit.md`, `docs/sustainability/green_software_report.md` | `global_spec`, `00_contracts` |

### Valeria Budiño (Frontend/UX, Cola Médica, Consultorio, Alertas/Modales y Entregables — 90 SP totales / 42 SP pend.)

| Orden | Comando `/desarrollo-tarea` | Nombre Exacto de Rama | Claves Permitidas en Commits/PR | Alcance / Archivos Exclusivos | Spec |
| :---: | :--- | :--- | :--- | :--- | :--- |
| ~~**1**~~ *(PR #21)* | ~~`/desarrollo-tarea CEN-26 local`~~ | ~~`CEN-26-cola-medica-tiempo-real`~~ | `CEN-26`, `CEN-42`, `CEN-43`, `CEN-44`, `CEN-45`, `CEN-46` | **Mergeada (PR #21):** `MedicalQueue` (`/consultas`), transiciones `Triage`, polling/eventos, `MedicalQueueWorkflowTest` | `00_contracts`, `consultas`, `ui` |
| **2** | `/desarrollo-tarea CEN-27 local` | `CEN-27-consultorio-diagnostico-cierre` | `CEN-27`, `CEN-47` | `ConsultationRoom` (`/consultas/{triage}`), ficha clínica de signos/discriminadores, diagnóstico presuntivo obligatorio, cierre (`completed`/`referred_sipec`) y despacho `ConsultationCompleted` | `00_contracts`, `consultas`, `ui` |
| **3** | `/desarrollo-tarea CEN-59 local` | `CEN-59-alertas-epidemiologicas-modales-mapa` | `CEN-59`, `CEN-64`, `CEN-65` | `EpidemiologicalAlerts` (badges/banners/tendencias leyendo `neighborhoods`), `NeighborhoodDetailModal` (evento `open-neighborhood-detail`), validación visual/rendimiento en navegador | `00_contracts`, `geo`, `ui` |
| **4** | `/desarrollo-tarea CEN-53 local` | `CEN-53-compliance-manual-caps-informe-apa` | `CEN-53`, `CEN-70`, `CEN-71` | `docs/compliance/ley_25326_audit.md`, `docs/manuals/manual_operativo_caps.md`, `docs/thesis/informe_final_tfi.md` | `global_spec`, `00_contracts` |

### Antonio Gabriel Pineda (Cierre Triage, Motor ML, Visor Leaflet y QA/Demo — 94 SP totales / 12 SP pend.)

| Orden | Comando `/desarrollo-tarea` | Nombre Exacto de Rama | Claves Permitidas en Commits/PR | Alcance / Archivos Exclusivos | Spec |
| :---: | :--- | :--- | :--- | :--- | :--- |
| ~~**1**~~ *(PR #22)* | ~~`/desarrollo-tarea CEN-72 local`~~ | ~~`CEN-72-cierre-bugfix-triage-ui`~~ | `CEN-72`, `CEN-76`, `CEN-77`, `CEN-78` | **Mergeada (PR #22):** `EvaluatePatient` (DNI, pestañas En espera/Historial, edición auditada), remediación UI signos vitales, suite Feature | `triage`, `pacientes`, `ui` |
| ~~**2**~~ *(PR #23)* | ~~`/desarrollo-tarea CEN-54 local`~~ | ~~`CEN-54-pipeline-ml-random-forest`~~ | `CEN-54`, `CEN-55`, `CEN-56` | **Mergeada (PR #23):** `pipeline/{dataset_builder,lag_features,synthetic_generator}.py`, `ml/train_random_forest.py`, `risk_classifier.pkl` (`rf-v1.0`) | `00_contracts`, `geo` |
| ~~**3**~~ *(PR #24)* | ~~`/desarrollo-tarea CEN-60 local`~~ | ~~`CEN-60-visor-leaflet-coropletas-heatmap`~~ | `CEN-60`, `CEN-61`, `CEN-62` | **Mergeada (PR #24):** `leaflet-map.blade.php`, `resources/js/{map,choropleth,heatmap}.js`, 18 tests `node --test` | `00_contracts`, `geo`, `ui` |
| **4** | `/desarrollo-tarea CEN-66 local` | `CEN-66-qa-e2e-demo-defensa` | `CEN-66`, `CEN-68` | `FullSystemE2ETest`, `DemoScenarioSeeder` (offline para defensa), `docs/defense/demo_script.md` | `global_spec`, `00_contracts` |

### Balance Consolidado de Story Points (Sprints 1 a 8)

| Integrante | Hechos Hoy | Pendiente Hoy | **TOTAL S1–S8** | Ramas Pendientes |
| :--- | :---: | :---: | :---: | :---: |
| **Valeria Budiño** | 48 SP | **42 SP** | **90 SP** | 3 ramas (`CEN-27`, `CEN-59`, `CEN-53`) |
| **Enrique M. González** | 28 SP | **54 SP** | **82 SP** | 5 ramas (`CEN-73`, `CEN-28`, `CEN-29`, `CEN-57`, `CEN-67`) |
| **Antonio Gabriel Pineda** | 82 SP | **12 SP** | **94 SP** | 1 rama (`CEN-66`) |
| **TOTAL EQUIPO** | **158 SP** | **108 SP** | **266 SP** | **9 ramas** *(4 mergeadas)* |

> **Nota sobre Jira Cloud:** En las dos macro-HUs divididas en sub-verticales (`HU07`/`CEN-30` y `HU08`/`CEN-31`), las ramas usan las claves `CEN-54`/`CEN-57`/`CEN-59`/`CEN-60` para evitar que Jira cierre la HU padre antes de completar ambas mitades. Una vez mergeadas todas sus sub-verticales, Gabriel cierra `CEN-30` y `CEN-31` en el tablero.

---

## 3. Método Consciente para Asignar HUs (Los 4 Filtros)

Si durante la semana cambia la disponibilidad de alguien y hace falta reasignar una rama, evaluá el movimiento aplicando estos **4 filtros en orden**:

1. **Filtro 1 — Cohesión de Archivos (Cero Solapamiento):**
   Si dos subtareas editan los mismos archivos (ej. `evaluate-patient.blade.php` o `MedicalQueue.php`), **nunca** las separes entre dos personas. Agrupalas en la misma rama.
2. **Filtro 2 — Tamaño Operativo (Regla de 16–24 SP):**
   - HUs de **≤ 20 SP** (`HU03`, `HU04`, `HU05`, `HU06`) van completas a **1 sola persona**.
   - Macro-HUs de **≥ 30 SP** (`HU07`, `HU08`) se cortan por **frontera técnica clara** (Python ML vs. API/PostGIS vs. UI Dashboard/Modales) apoyándose en un contrato JSON congelado (`docs/specs/00_contracts.md`).
3. **Filtro 3 — Camino Crítico y Desbloqueo (PERT):**
   Priorizá primero las piezas que destraban integración (`Paso 0`, `CEN-73`, `HU03`, `HU04`, `HU06`) mientras los módulos satelitales (`HU05` Open-Meteo, `CEN-59` Alertas contra datos seedeados, `CEN-54` ML con dataset sintético) avanzan en paralelo sin esperas.
4. **Filtro 4 — Perfil Oficial (§3.6 del Plan de Trabajo) y Equilibrio de Commits:**
   Respetá las especialidades declaradas para cada integrante y chequeá en GitHub (`Insights → Contributors`) que los tres acumulen commits y PRs equilibrados y coherentes con su rol:
   - **Valeria Budiño:** UI/UX, Livewire/Blade, Consultorio, Alertas/Modales, Compliance y Documentación APA/Manual.
   - **Enrique M. González:** RBAC, BD/PostGIS, APIs REST (Open-Meteo, FastAPI, GeoJSON), Integración Outbox y Seguridad/Green Software.
   - **Antonio G. Pineda:** Triage/Cola en tiempo real, Pipeline ML Scikit-Learn, Capas Leaflet.js y QA E2E/Demo.

---

## 4. Checklist de Limpieza Pre-Entrega hacia `main` (Code Freeze 02/12)

Antes de abrir el release final `develop -> main` (o antes de invitar docentes al repositorio si corresponde):
- [ ] Excluir `docs/team/` de la rama `main` (documento exclusivamente interno).
- [ ] Revisar si corresponde limpiar/ocultar configuraciones internas de asistentes (`AGENTS.md`, `CLAUDE.md`, `.agents/`, `.claude/`, `.opencode/`) en el corte final de `main`.
- [ ] Reemplazar las secciones genéricas de Laravel en `README.md` (incluyendo el bloque por defecto *Agentic Development / Laravel Boost*) por la ficha técnica institucional de **Centinela** (arquitectura Edge-to-Hub, guía de despliegue Docker de Edge + Hub, credenciales demo y pruebas).
- [ ] Verificar que `docs/specs/` contenga únicamente lenguaje formal de Ingeniería de Software (contratos, RF EARS y criterios Gherkin).
- [ ] Actualizar en Jira Cloud el estado final de las HUs padre (`CEN-30`, `CEN-31`, `CEN-32`, `CEN-33`) y verificar que `docs/backlog.md` y `docs/changelog.md` estén 100% al día.
