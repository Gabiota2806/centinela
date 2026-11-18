# 📋 Tablero de Backlog del Proyecto (Espejo Jira Cloud - CEN)

> **MANIFIESTO OPERATIVO PARA ASISTENTES AGÉNTICOS (Antigravity, Claude Code, OpenCode):**
> Este archivo es la **fuente de verdad técnica local** del tablero Jira Cloud (`CEN`). Los agentes deben leer las especificaciones, modelos de datos, rutas y criterios Gherkin aquí estipulados antes de redactar código o proponer cambios, evitando suposiciones o patrones no consensuados.
>
> **Directiva de Optimización de Tokens y Flujo de Sprints:**
> - El **Sprint Activo** se ubica **siempre al principio** de este archivo.
> - **Lectura Quirúrgica:** Para consultar una tarea específica (`CEN-xxx`), busca primero su línea con `grep -n "CEN-xxx" docs/backlog.md` y lee únicamente ese rango de líneas (o el bloque del Sprint Activo), evitando cargar el archivo completo en contexto.
> - **Archivo Histórico:** Los Sprints finalizados (`Sprint 1`: `CEN-4` a `CEN-9`; `Sprint 2`: `CEN-11` a `CEN-17`; `Sprint 3`: `CEN-34` a `CEN-41`) se encuentran archivados en `docs/backlog_archive.md`. **No leas `docs/backlog_archive.md`** salvo consulta histórica explícita.
> - **Rotación de Sprint:** Al marcar como `[x] Listo` o auditar la última subtarea del Sprint Activo, el agente debe migrar el Sprint completado a `docs/backlog_archive.md` y promover el siguiente Sprint como `(ACTIVO)` al inicio de este documento.
> 
> **Convención de Estados:**
> - `[ ]` **Por hacer (To Do)**
> - `[/]` **En curso (In Progress)**
> - `[x]` **Listo (Done)**

---

## 🏃 Sprint 4: Cola de Atención Médica en Tiempo Real y Dashboard (ACTIVO)
- **Épica:** `CEN-19`
- **Estado:** 🚧 **En curso (In Progress)** (Inicio: 18/11/2026 - Fin: 21/11/2026) | **Puntos:** 57 SP (21 SP Bugfix de entrada + 36 SP Cola/Consultorio)
- **Alcance:** Remediación de flujo unificado Admisión+Triage/RBAC/UI y cola de espera médica dinámica con ordenamiento MTS, llamada a consultorio y cierre de consulta.

### 🐛 CEN-72: [BUGFIX S4] Admisión con Signos Vitales en /pacientes/nuevo, RBAC de Enfermería, Gestión en /triage y Correcciones UI (21 SP)

#### 📌 CEN-73: [T4.B1] RBAC de enfermería en admisión (access-admission), PrintTicket en admisión y Padrón /pacientes
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 3 SP
- **Archivos Objetivo:** `app/Providers/AppServiceProvider.php`, `routes/web.php`, `app/Livewire/Triage/PrintTicket.php`, `resources/views/livewire/triage/ticket-modal.blade.php`, `app/Livewire/Patients/PatientDirectory.php`, `resources/views/livewire/patients/patient-directory.blade.php`, `resources/views/patients/index.blade.php`.
- **Detalle Técnico:** Sumar `UserRole::TRIAGE_NURSE` a `access-admission` (alineado con `admit-patient` y relevamiento CAPS Codda). Habilitar `PrintTicket` para `access-admission` || `access-triage` manteniendo `ClinicalOverrideModal` solo bajo `access-triage`. Reemplazar el placeholder de `/pacientes` por el componente `PatientDirectory` (padrón/buscador por DNI/nombre con enlace directo a `/pacientes/nuevo?dni=...`).

#### 📌 CEN-74: [T4.B2] Admisión + Triage unificados en /pacientes/nuevo (DNI recurrente, bloqueo activo y signos obligatorios)
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 5 SP
- **Archivos Objetivo:** `app/Livewire/Patients/CreatePatient.php`, `resources/views/livewire/patients/create-patient.blade.php`, `resources/views/patients/create.blade.php`.
- **Detalle Técnico:** Búsqueda/precarga editable por DNI (incluyendo `withTrashed()`), bloqueo si el paciente posee triage activo (`pending_consultation`, `called`, `in_consultation`) alertando con el `#TR-XXXXX` activo. Carga obligatoria de los 6 signos vitales y motivo de consulta ubicado antes de los discriminadores MTS opcionales, con panel sticky MTS en vivo y persistencia atómica `Patient` + `Triage` en `DB::transaction`.

#### 📌 CEN-75: [T4.B3] Cálculo y visualización de posición estimada en lista de espera (#N) en confirmación y ticket 80 mm
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 3 SP
- **Archivos Objetivo:** `app/Models/Triage.php`, `app/Livewire/Patients/CreatePatient.php`, `app/Livewire/Triage/PrintTicket.php`, `resources/views/livewire/triage/ticket-modal.blade.php`.
- **Detalle Técnico:** Método en `Triage` para calcular la posición estimada `#N` dentro de `pending_consultation` (jerarquía MTS + FIFO `created_at`/`id`), mostrado en la confirmación de alta de `/pacientes/nuevo` y en el ticket digital/80 mm con nota de posición dinámica al ingreso, recalculando tras override/edición.

#### 📌 CEN-76: [T4.B4] Evolución de /triage: buscador por DNI, pestaña "En espera" con edición auditada y pestaña "Historial"
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 5 SP
- **Archivos Objetivo:** `app/Livewire/Triage/EvaluatePatient.php`, `resources/views/livewire/triage/evaluate-patient.blade.php`, `app/Models/Triage.php`.
- **Detalle Técnico:** Buscador por DNI/nombre; pestaña "En espera" (`pending_consultation` con `#N` y `called` con badge "Llamado") con acciones Editar signos/motivo/discriminadores (recalcula MTS y registra en `triage_audit_logs` si cambia la prioridad), Clinical Override y Ver ticket; pestaña "Historial" (`in_consultation`, `completed`, `cancelled`).

#### 📌 CEN-77: [T4.B5] Remediación UI/UX de campos de signos vitales, estados de carga y tokens MTS
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 2 SP
- **Archivos Objetivo:** `resources/views/livewire/patients/create-patient.blade.php`, `resources/views/livewire/triage/evaluate-patient.blade.php`, `tailwind.config.js`.
- **Detalle Técnico:** Padding derecho reservado (`pr-9`, `pr-12`, `pr-14`) + `pointer-events-none` en unidades (`°C`, `mmHg`, `lpm`, `rpm`, `%`), `wire:target="save"` en botones de guardado, bordes `border-danger` / `aria-invalid` / `aria-describedby` según `docs/specs/ui/spec.md` y verificación de compilación CSS de los 5 colores MTS.

#### 📌 CEN-78: [T4.B6] Suite de pruebas Feature/Livewire para flujo unificado, RBAC actualizado, bloqueo activo y gestión en /triage
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 3 SP
- **Archivos Objetivo:** `tests/Feature/Patients/CreatePatientTest.php`, `tests/Feature/Patients/PatientDirectoryTest.php`, `tests/Feature/AuthRoleAccessTest.php`, `tests/Feature/Triage/EvaluatePatientTest.php`, `tests/Feature/Triage/PrintTicketTest.php`.
- **Detalle Técnico:** Cobertura Feature/Livewire completa de los criterios Gherkin del Bugfix Sprint 4 (`CEN-72`).

### 📖 HU03: Gestión de Cola Médica en Tiempo Real (`CEN-26`, 20 SP)

#### 📌 CEN-42: [T4.1] Diseño y maquetación del panel médico con ordenamiento dinámico por MTS
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 4 SP
- **Archivos Objetivo:** `app/Livewire/Doctor/MedicalQueue.php`, `resources/views/livewire/doctor/medical-queue.blade.php`.
- **Detalle Técnico:** Tarjetas de espera ordenadas por color MTS (Rojo al frente, luego Naranja, Amarillo, Verde, Azul) mostrando posición `#N` y ticket `TR-XXXXX`. Cronómetro regresivo con tiempo restante antes del límite máximo de espera. Botón "Llamar a Consultorio", botón de Clinical Override (`open-clinical-override`) y acciones selectivas sobre pacientes en estado `called`: "Ingresar a consulta", "Volver a espera" o "Cancelar / Ausente".

#### 📌 CEN-43: [T4.2] Implementación de eventos y actualización reactiva de colas en Livewire
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 4 SP
- **Archivos Objetivo:** `app/Livewire/Doctor/MedicalQueue.php`.
- **Detalle Técnico:** Actualización reactiva mediante eventos Livewire (`#[On('patient-triaged')]`, `#[On('triage-overridden')]`, `wire:poll.5s` para sincronización entre terminales). Ordenamiento SQL nativo:
  `ORDER BY CASE final_priority WHEN 'red' THEN 1 WHEN 'orange' THEN 2 WHEN 'yellow' THEN 3 WHEN 'green' THEN 4 WHEN 'blue' THEN 5 END, created_at ASC`.

#### 📌 CEN-44: [T4.3] Lógica de cambio de estados del paciente (Llamado, En Consulta, Atendido)
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 4 SP
- **Archivos Objetivo:** `app/Models/Triage.php`, `app/Models/Consultation.php`, `app/Enums/TriageStatus.php`.
- **Detalle Técnico:** Agregar `CALLED = 'called'` en `TriageStatus`. Transiciones válidas: `pending_consultation` ➔ `called` ➔ `in_consultation` ➔ `completed` (con `Consultation` en `completed` / `referred_sipec`), más transiciones selectivas desde `called`: ➔ `pending_consultation` (devolver a cola conservando `created_at` FIFO) o ➔ `cancelled` (ausente/retirado, habilitando re-admisión futura). Registro de timestamps `started_at` y `completed_at`.

#### 📌 CEN-45: [T4.5] Optimización de consultas a la cola de espera mediante índices compuestos
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 3 SP
- **Archivos Objetivo:** `database/migrations/xxxx_add_indexes_to_triages_table.php`.
- **Detalle Técnico:** Índice compuesto en PostgreSQL: `CREATE INDEX idx_triages_active_queue ON triages (status, final_priority, created_at);`. Tiempo de respuesta < 20 ms probado con `EXPLAIN ANALYZE`.

#### 📌 CEN-46: [T4.6] Testing de flujo completo de sala de espera desde admisión hasta alta médica
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 5 SP
- **Archivos Objetivo:** `tests/Feature/MedicalQueueWorkflowTest.php`.
- **Detalle Técnico:** Test E2E del flujo unificado real: Admisión+Triage (`/pacientes/nuevo`) ➔ Edición/Override en `/triage` ➔ Cola de Espera (`/consultas`) ➔ Llamado (`called` con retorno a espera y ausente) ➔ Atención (`in_consultation`) ➔ Cierre (`completed`).

### 📖 HU04: Registro Diagnóstico al Cierre de Consulta (`CEN-27`, 16 SP)

#### 📌 CEN-47: [T4.4] Formulario de registro de diagnóstico presuntivo y cierre de consulta
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 8 SP
- **Archivos Objetivo:** `app/Livewire/Doctor/ConsultationRoom.php`, `resources/views/livewire/doctor/consultation-room.blade.php`.
- **Detalle Técnico:** Visualización clínica de signos vitales, discriminadores y motivo de consulta cargados en `/pacientes/nuevo` (o editados en `/triage`), selección obligatoria de diagnóstico presuntivo estandarizado (Síndrome Febril Agudo inespecífico, Sospecha de Dengue, IRA, Gastroenteritis), campo de notas clínicas y prescripción. Al guardar, el triage pasa a `completed` (saliendo de la cola activa hacia el historial de `/triage`) y se emite evento para telemetría.

---

## 🏃 Sprint 5: Ingestión Meteorológica y Capa de Anonimización
- **Épica:** `CEN-20`
- **Estado:** `[ ]` Por hacer (Inicio: 22/11/2026 - Fin: 25/11/2026) | **Puntos:** 36 SP
- **Alcance:** Conexión con Open-Meteo, Outbox Pattern asíncrono y anonimización de datos (Ley 25.326).

### 📖 HU05: Ingestión Meteorológica Automatizada (`CEN-28`, 16 SP)

#### 📌 CEN-48: [T5.1] Desarrollo del cliente HTTP para consumo de la API abierta Open-Meteo
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 4 SP
- **Archivos Objetivo:** `app/Services/Weather/OpenMeteoService.php`, `database/migrations/xxxx_create_weather_records_table.php`, `app/Models/WeatherRecord.php`.
- **Detalle Técnico:** Consumo de endpoint Open-Meteo para Formosa Capital (`latitude=-26.18`, `longitude=-58.17`). Variables: `precipitation_sum`, `temperature_2m_max`, `temperature_2m_min`, `relative_humidity_2m`.

#### 📌 CEN-49: [T5.2] Implementación de caché y programación de tareas programadas (Laravel Cron)
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 3 SP
- **Archivos Objetivo:** `app/Console/Commands/FetchDailyWeatherCommand.php`, `routes/console.php`.
- **Detalle Técnico:** Ejecución diaria automatizada a las 06:00 AM. Almacenamiento en caché de contingencia ante caídas de enlace de red para mantener datos de los últimos 7 días.

#### 📌 CEN-50: [T5.5] Maquetación de vistas de configuración y parámetros epidemiológicos
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 5 SP
- **Archivos Objetivo:** `app/Livewire/Admin/EpidemiologySettings.php`, `resources/views/livewire/admin/epidemiology-settings.blade.php`.
- **Detalle Técnico:** Panel para ajustar parámetros de alerta (umbrales de lluvia en mm, ventana de tiempo crítico y factores de riesgo ambiental).

### 📖 HU06: Telemetría Epidemiológica Asíncrona y Anonimización (Ley 25.326) (`CEN-29`, 20 SP)

#### 📌 CEN-51: [T5.3] Módulo de disociación y anonimización de datos clínicos (Ley N° 25.326)
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 4 SP
- **Archivos Objetivo:** `app/Services/Telemetry/AnonymizerService.php`, `app/Jobs/DispatchTelemetryJob.php`.
- **Detalle Técnico:** Outbox Pattern: tabla `outbox_telemetry_events`. Filtro irreversible: conserva `neighborhood_id`, grupo etario (`infante`, `adulto`, `mayor`), `triage_priority`, `presumptive_diagnosis` y `symptoms`. Elimina absolutamente DNI, nombre, apellido, teléfono y domicilio.

#### 📌 CEN-52: [T5.4] Estructuración de microservicio FastAPI en Python para analítica
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 4 SP
- **Archivos Objetivo:** `backend_analytics/main.py`, `backend_analytics/routers/telemetry.py`, `backend_analytics/schemas/telemetry.py`.
- **Detalle Técnico:** Microservicio en Python 3.12 con FastAPI. Endpoint `POST /api/v1/telemetry` con validación Pydantic y autenticación de nodo mediante header `X-CAPS-Key`.

#### 📌 CEN-53: [T5.6] Verificación de seguridad y auditoría de protección de datos personales
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 4 SP
- **Archivos Objetivo:** `docs/compliance/ley_25326_audit.md`.
- **Detalle Técnico:** Informe formal de auditoría legal y técnica garantizando el cumplimiento de la Ley Nacional N° 25.326 y principios de Privacy by Design.

---

## 🏃 Sprint 6: Motor Analítico de Alerta Temprana con Scikit-Learn
- **Épica:** `CEN-21`
- **Estado:** `[ ]` Por hacer (Inicio: 26/11/2026 - Fin: 28/11/2026) | **Puntos:** 36 SP
- **Alcance:** Pipeline de Scikit-Learn, correlación con time-lag lluvia-casos e inferencia analítica en FastAPI.

### 📖 HU07: Predicción de Riesgo de Brote Epidemiológico con Scikit-Learn (`CEN-30`, 36 SP)

#### 📌 CEN-54: [T6.1] Procesamiento y limpieza de series temporales de consultas con Pandas
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 4 SP
- **Archivos Objetivo:** `backend_analytics/pipeline/dataset_builder.py`.
- **Detalle Técnico:** Transformación de telemetría y registros meteorológicos en series temporales agrupadas por semana epidemiológica (EpiWeek) y barrio.

#### 📌 CEN-55: [T6.2] Modelado matemático del retardo temporal (time-lag de 7 a 14 días) lluvia-casos
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 4 SP
- **Archivos Objetivo:** `backend_analytics/pipeline/lag_features.py`.
- **Detalle Técnico:** Generación de variables con retardo (`rain_lag_7d`, `rain_lag_14d`, `temp_mean_lag_7d`) correlacionadas con el ciclo biológico del mosquito transmisor.

#### 📌 CEN-56: [T6.3] Entrenamiento y ajuste del modelo de Machine Learning (Random Forest)
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 8 SP
- **Archivos Objetivo:** `backend_analytics/ml/train_random_forest.py`, `backend_analytics/models/risk_classifier.pkl`.
- **Detalle Técnico:** Entrenamiento con Scikit-Learn (RandomForestClassifier). Clasificación del riesgo barrial en 3 clases: Bajo (0), Moderado (1) y Alto (2). Exportación con `joblib`.

#### 📌 CEN-57: [T6.4] Desarrollo de endpoint en FastAPI para servir inferencias analíticas
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 6 SP
- **Archivos Objetivo:** `backend_analytics/routers/predict.py`.
- **Detalle Técnico:** Endpoint `GET /api/v1/neighborhoods/{id}/risk` que carga el modelo serializado y devuelve el vector de probabilidades y nivel de alerta en menos de 200 ms.

#### 📌 CEN-58: [T6.5] Integración del retorno de alertas desde FastAPI hacia el core de Laravel
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 6 SP
- **Archivos Objetivo:** `app/Services/Analytics/PredictiveRiskClient.php`, `app/Console/Commands/SyncNeighborhoodRiskCommand.php`.
- **Detalle Técnico:** Comando programado que sincroniza los niveles de riesgo predichos en la tabla `neighborhoods` de PostgreSQL y emite alertas internas en caso de riesgo alto.

#### 📌 CEN-59: [T6.6] Diseño de interfaz para visualización de alertas epidemiológicas y métricas
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 8 SP
- **Archivos Objetivo:** `app/Livewire/Dashboard/EpidemiologicalAlerts.php`, `resources/views/livewire/dashboard/epidemiological-alerts.blade.php`.
- **Detalle Técnico:** Componentes visuales de dashboard: badges de riesgo barrial, gráficos de tendencias y banners de alerta preventiva para la dirección del centro.

---

## 🏃 Sprint 7: Visualización Geoespacial Barrial en Leaflet.js
- **Épica:** `CEN-22`
- **Estado:** `[ ]` Por hacer (Inicio: 29/11/2026 - Fin: 01/12/2026) | **Puntos:** 36 SP
- **Alcance:** Integración Leaflet.js, mapas coropléticos de barrios de Formosa Capital y mapas de calor.

### 📖 HU08: Mapa Interactivo de Calor por Barrios en Leaflet.js (`CEN-31`, 36 SP)

#### 📌 CEN-60: [T7.1] Integración de la librería Leaflet.js sobre OpenStreetMap en el dashboard
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 4 SP
- **Archivos Objetivo:** `resources/js/map.js`, `resources/views/components/leaflet-map.blade.php`.
- **Detalle Técnico:** Mapa interactivo Leaflet.js integrado con OpenStreetMap, centrado en Formosa Capital (`[-26.18, -58.17]`, zoom 13).

#### 📌 CEN-61: [T7.2] Carga y renderizado de capas poligonales GeoJSON de barrios de la ciudad
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 6 SP
- **Archivos Objetivo:** `resources/js/choropleth.js`.
- **Detalle Técnico:** Renderizado de coropletas coloreadas dinámicamente según el nivel de riesgo predicho del barrio (Verde: Bajo, Amarillo: Moderado, Rojo: Alto) con eventos `mouseover` y `click`.

#### 📌 CEN-62: [T7.3] Implementación de capa de calor (Heatmap) basada en densidad de casos
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 6 SP
- **Archivos Objetivo:** `resources/js/heatmap.js`.
- **Detalle Técnico:** Representación continua de focos calientes mediante `leaflet.heat` con ponderación espacial basada en consultas febriles recientes.

#### 📌 CEN-63: [T7.4] Optimización de consultas espaciales en PostgreSQL usando funciones PostGIS
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 8 SP
- **Archivos Objetivo:** `app/Http/Controllers/Api/GeoSpatialController.php`, `routes/api.php`.
- **Detalle Técnico:** Endpoint de alta velocidad `GET /api/v1/geo/neighborhoods` que retorna la colección GeoJSON con propiedades de riesgo utilizando funciones nativas `ST_AsGeoJSON()`.

#### 📌 CEN-64: [T7.5] Desarrollo de ventanas modales informativas al hacer clic en cada barrio
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 6 SP
- **Archivos Objetivo:** `resources/views/livewire/map/neighborhood-detail-modal.blade.php`.
- **Detalle Técnico:** Popup interactivo con detalle de consultas acumuladas, nivel de alerta, condiciones climáticas y recomendaciones operativas.

#### 📌 CEN-65: [T7.6] Pruebas de rendimiento del mapa interactivo y compatibilidad en navegadores
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 6 SP
- **Archivos Objetivo:** `tests/Browser/MapPerformanceTest.php`.
- **Detalle Técnico:** Validación de renderizado fluido a 60 fps sin fugas de memoria en navegadores de escritorio y tabletas.

---

## 🏃 Sprint 8: Pruebas Integrales (QA), Auditoría de Rendimiento y Documentación
- **Épica:** `CEN-23`
- **Estado:** `[ ]` Por hacer (Inicio: 02/12/2026 - Fin: 08/12/2026) | **Hito:** 🎓 **Code Freeze, Auditoría y Defensa Final**
- **Cómputo Total:** 33 Story Points (99 hs cátedra)

### 📖 HU10: Pruebas Integrales QA, Stress Testing y Hardening OWASP (`CEN-32`, 18 SP)

#### 📌 CEN-66: [T8.1] Ejecución de pruebas integrales end-to-end de todos los módulos del sistema
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 6 SP
- **Archivos Objetivo:** `tests/Feature/FullSystemE2ETest.php`.
- **Detalle Técnico:** Ejecución de matriz completa de pruebas E2E automatizadas, resolución de defectos menores y congelamiento formal del código (Code Freeze).

#### 📌 CEN-67: [T8.2] Auditoría de seguridad OWASP Top 10 y hardening de la aplicación Laravel
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 6 SP
- **Archivos Objetivo:** `app/Http/Middleware/SecurityHeadersMiddleware.php`, `docs/security/owasp_audit.md`.
- **Detalle Técnico:** Verificación de protección contra inyecciones SQL, XSS, rate-limiting estricto y cabeceras seguras (CSP, HSTS, X-Frame-Options).

#### 📌 CEN-68: [T8.6] Preparación del entorno de demostración en vivo y presentación para defensa
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 6 SP
- **Archivos Objetivo:** `database/seeders/DemoScenarioSeeder.php`, `docs/defense/demo_script.md`.
- **Detalle Técnico:** Seeder con escenario epidemiológico simulado listo para ejecutar en modo offline durante la defensa presencial ante el tribunal.

### 📖 HU11: Auditoría Green Software, Memoria Técnica Final y Demo (`CEN-33`, 15 SP)

#### 📌 CEN-69: [T8.3] Evaluación de eficiencia energética y Green Software en el servidor
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 5 SP
- **Archivos Objetivo:** `docs/sustainability/green_software_report.md`.
- **Detalle Técnico:** Medición y reporte de consumo de cómputo, optimización de queries e impacto de huella de carbono digital bajo principios de software sustentable.

#### 📌 CEN-70: [T8.4] Elaboración del Manual de Usuario y Guía de Operación para personal de CAPS
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 5 SP
- **Archivos Objetivo:** `docs/manuals/manual_operativo_caps.md`.
- **Detalle Técnico:** Manual ilustrado paso a paso para personal de mesa de entrada, enfermería de triage y médicos de guardia.

#### 📌 CEN-71: [T8.5] Redacción del informe técnico final del TFI bajo normas APA
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 5 SP
- **Archivos Objetivo:** `docs/thesis/informe_final_tfi.md`.
- **Detalle Técnico:** Redacción y compilación de la memoria técnica final del TFI cumpliendo con los estándares de la cátedra y normas APA para su presentación formal.
