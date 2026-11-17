# 📋 Tablero de Backlog del Proyecto (Espejo Jira Cloud - CEN)

> **MANIFIESTO OPERATIVO PARA ASISTENTES AGÉNTICOS (Antigravity, Claude Code, OpenCode):**
> Este archivo es la **fuente de verdad técnica local** del tablero Jira Cloud (`CEN`). Los agentes deben leer las especificaciones, modelos de datos, rutas y criterios Gherkin aquí estipulados antes de redactar código o proponer cambios, evitando suposiciones o patrones no consensuados.
>
> **Directiva de Optimización de Tokens y Flujo de Sprints:**
> - El **Sprint Activo** se ubica **siempre al principio** de este archivo.
> - **Lectura Quirúrgica:** Para consultar una tarea específica (`CEN-xxx`), busca primero su línea con `grep -n "CEN-xxx" docs/backlog.md` y lee únicamente ese rango de líneas (o el bloque del Sprint Activo), evitando cargar el archivo completo en contexto.
> - **Archivo Histórico:** Los Sprints finalizados (`Sprint 1`: `CEN-4` a `CEN-9`; `Sprint 2`: `CEN-11` a `CEN-17`) se encuentran archivados en `docs/backlog_archive.md`. **No leas `docs/backlog_archive.md`** salvo consulta histórica explícita.
> - **Rotación de Sprint:** Al marcar como `[x] Listo` o auditar la última subtarea del Sprint Activo, el agente debe migrar el Sprint completado a `docs/backlog_archive.md` y promover el siguiente Sprint como `(ACTIVO)` al inicio de este documento.
> 
> **Convención de Estados:**
> - `[ ]` **Por hacer (To Do)**
> - `[/]` **En curso (In Progress)**
> - `[x]` **Listo (Done)**

---

## 🏃 Sprint 3: Módulo de Triage Clínico (Sistema Manchester), Clinical Override y Admisión (ACTIVO)
- **Épica:** `CEN-18`
- **Estado:** 🚧 **En curso (In Progress)** (Inicio: 12/11/2026 - Fin: 17/11/2026) | **Hito:** 🎯 **Prototipo Funcional Core Clínico**
- **Cómputo Total:** 36 Story Points (108 hs cátedra)

### 📖 HU02: Admisión y Clasificación de Triage (Sistema Manchester)
- **Clave:** `CEN-24` | **Estado:** `En curso` | **Puntos:** 20 SP | **Épica:** `CEN-18`
- **Spec Modular:** `docs/specs/triage/spec.md` y `docs/specs/ui/spec.md`

#### 📌 CEN-34: [T3.1] Maquetación del formulario reactivo de admisión en Livewire y TailwindCSS
- **Estado:** `[x]` Listo
- **Responsable:** Valeria Budiño
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `app/Livewire/Patients/CreatePatient.php`
  * `resources/views/livewire/patients/create-patient.blade.php`
  * `resources/views/patients/create.blade.php`
  * `routes/web.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Componente Livewire 4:** Manejo reactivo de campos demográficos:
     * `dni`: string(20), requerido, único, solo dígitos numéricos.
     * `first_name`: string(100), requerido.
     * `last_name`: string(100), requerido.
     * `birth_date`: date, requerida, no futura (`before_or_equal:today`).
     * `gender`: enum/select (`masculino`, `femenino`, `otro`).
     * `phone`: string(50), nullable.
     * `address`: string(255), requerido.
     * `neighborhood_id`: foreignId requerido constrained a `neighborhoods`.
  2. **Estilo y Accesibilidad UI:**
     * Integrar clases utilitarias de Tailwind v3 según `docs/specs/ui/spec.md`.
     * Iconografía con **SVG vectorial exclusivo** de Heroicons (prohibido emojis tipográficos).
     * Mensajes de error en tiempo real con directiva `@error`.
  3. **Ruta y Gate:** Ruta `/pacientes/nuevo` protegida por middleware `auth` y Gate `access-admission`.
- **Criterio de Aceptación (Gherkin):**
  ```gherkin
  Escenario: Carga exitosa de paciente con validación reactiva
    Dado un usuario autenticado con rol "admission"
    Cuando completa el formulario con datos válidos y selecciona un barrio existente
    Entonces el paciente se persiste en la tabla "patients"
    Y el sistema emite notificación flash de éxito y permite iniciar triage de inmediato.
  ```

---

#### 📌 CEN-35: [T3.2] Programación de la lógica del árbol de decisión clínico Manchester (MTS)
- **Estado:** `[x]` Listo
- **Responsable:** Enrique M. González
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `app/Services/ManchesterTriageService.php`
  * `app/Enums/TriagePriority.php`
  * `app/Livewire/Triage/EvaluatePatient.php`
  * `resources/views/livewire/triage/evaluate-patient.blade.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Enum Nativo PHP 8.3 (`app/Enums/TriagePriority.php`):**
     * `RED = 'red'` (Emergencia / Reanimación, 0 min, `#DC2626`).
     * `ORANGE = 'orange'` (Muy Urgente, 10 min, `#EA580C`).
     * `YELLOW = 'yellow'` (Urgente, 60 min, `#EAB308`).
     * `GREEN = 'green'` (Poco Urgente, 120 min, `#16A34A`).
     * `BLUE = 'blue'` (No Urgente, 240 min, `#2563EB`).
  2. **Motor Determinístico (`ManchesterTriageService`):**
     * Evalúa discriminadores generales y signos vitales (`temperature`, `systolic_bp`, `diastolic_bp`, `heart_rate`, `respiratory_rate`, `spo2`).
     * Reglas clínicas:
       - Vía aérea obstruida, apnea, pulso no detectable, SpO2 < 85% ➔ **ROJO** (0 min).
       - Dolor severo, disnea aguda, letargo, SpO2 85-92%, shock ➔ **NARANJA** (10 min).
       - Fiebre persistente > 39°C con cefalea/mialgias (sospecha febril dengue), SpO2 93-94%, dolor moderado ➔ **AMARILLO** (60 min).
       - Fiebre < 38.5°C sin alteración hemodinámica, dolor leve reciente ➔ **VERDE** (120 min).
       - Chequeo de rutina, retiro de puntos, administrativo ➔ **AZUL** (240 min).
     * Devuelve `['priority' => TriagePriority, 'target_wait_minutes' => int, 'discriminator' => string]`.
- **Criterio de Aceptación (DoD):**
  * `ManchesterTriageServiceTest` valida cada rama del árbol determinístico sin fallas.

---

#### 📌 CEN-36: [T3.3] Mapeo de barrios de Formosa Capital en el formulario de admisión
- **Estado:** `[x]` Listo
- **Responsable:** Enrique M. González
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `database/seeders/NeighborhoodSeeder.php`
  * `app/Models/Neighborhood.php`
  * `resources/views/livewire/patients/create-patient.blade.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Seeder de Barrios de Formosa:**
     * Cargar polígonos GeoJSON de barrios oficiales de Formosa Capital (Lote 4, Bernardino Rivadavia, San Martín, Don Bosco, Eva Perón, Fontana, San Miguel, Circuito Cinco, Guadalupe, etc.).
     * Inserción en tabla `neighborhoods` usando `ST_GeomFromGeoJSON()` en SRID 4326.
  2. **Selector Reactivo en Admisión:**
     * Desplegable con autocompletado y búsqueda rápida en Livewire para selección fluida durante la guardia.
- **Criterio de Aceptación (DoD):**
  * `php artisan db:seed --class=NeighborhoodSeeder` inserta los barrios sin errores de formato geométrico.

---

#### 📌 CEN-37: [T3.4] Emisión y visualización de ticket digital con color de prioridad asignado
- **Estado:** `[x]` Listo
- **Responsable:** Valeria Budiño
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `resources/views/livewire/triage/ticket-modal.blade.php`
  * `app/Livewire/Triage/PrintTicket.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Diseño de Ticket Térmico / Digital:**
     * N° de Turno / Identificador de Triage (`TR-00123`).
     * Badge de gran visibilidad con el color oficial MTS (`bg-red-600`, etc.).
     * Datos del paciente: Nombre, DNI, Barrio, Fecha y Hora exacta de ingreso.
     * Tiempo máximo de espera recomendado según Manchester.
     * Nombre del profesional evaluador.
  2. **Soporte de Impresión:**
     * Estilos CSS `@media print` optimizados para ticketeras térmicas estándar de 80 mm y visualización responsiva en pantalla.
- **Criterio de Aceptación (DoD):**
  * El ticket renderiza en pantalla inmediatamente al concluir la evaluación y se visualiza correctamente en ventana de impresión.

---

#### 📌 CEN-38: [T3.7] Pruebas de integración de clasificación clínica automatizada
- **Estado:** `[x]` Listo
- **Responsable:** Gabriel Pineda
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `tests/Feature/ManchesterTriageCalculationTest.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Suite PHPUnit 12:**
     * Probar los 5 niveles Manchester ante distintas combinaciones de signos vitales.
     * Verificar que ante signos de riesgo inminente (ej. SpO2 < 85%) el sistema asigne ineludiblemente ROJO con 0 minutos de espera.
- **Criterio de Aceptación (DoD):**
  * Cobertura del 100% en métodos de cálculo de prioridad.

---

### 📖 HU09: Reclasificación Manual y Auditoría Clínica (Clinical Override)
- **Clave:** `CEN-25` | **Estado:** `Por hacer` | **Puntos:** 16 SP | **Épica:** `CEN-18`
- **Spec Modular:** `docs/specs/triage/spec.md`

#### 📌 CEN-39: [T3.5] Componentes UX y modal de Clinical Override rápido con justificación diferida
- **Estado:** `[ ]` Por hacer
- **Responsable:** Valeria Budiño
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `resources/views/livewire/triage/clinical-override-modal.blade.php`
  * `app/Livewire/Triage/ClinicalOverrideModal.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Flujo de Acción Inmediata (1 Clic):**
     * Botón accesible de "Reclasificar Prioridad (Override)" en la ficha del paciente.
     * Selector del nuevo nivel MTS (con los 5 colores estandarizados).
     * Modal con opción de emergencia: Checkbox / Botón de "Justificación Diferida (Emergencia Crítica)". Permite salvar el nuevo nivel en 1 segundo sin detener al profesional cuando un paciente entra en paro o shock.
     * Si no es diferido, campo de texto obligatorio `override_reason` (mínimo 10 caracteres).
- **Criterio de Aceptación (Gherkin):**
  ```gherkin
  Escenario: Override clínico de emergencia en un clic con justificación diferida
    Dado un paciente evaluado inicialmente con prioridad "Verde"
    Cuando el profesional activa el Clinical Override a "Rojo" marcando "Justificación Diferida"
    Entonces el paciente pasa inmediatamente a prioridad "Rojo" en la cola
    Y se registra en auditoría con estado de motivo pendiente de redacción.
  ```

---

#### 📌 CEN-40: [T3.6] Desarrollo de la tabla inmutable triage_audit_logs y lógica de reclasificación
- **Estado:** `[x]` Listo
- **Responsable:** Enrique M. González
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `app/Models/TriageAuditLog.php`
  * `app/Models/Triage.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Inmutabilidad Absoluta en Eloquent:**
     * Modelo `TriageAuditLog` bloquea eventos `updating` y `deleting` en su `booted()` lanzando `ImmutableModelException`.
     * Desactivar `$timestamps` de actualización (`const UPDATED_AT = null`).
  2. **Método en `Triage`:**
     * `overridePriority(TriagePriority $newPriority, string $reason, User $user, bool $deferred = false)`:
       Actualiza `final_priority`, activa flag `is_override = true` y crea el registro inmutable en `triage_audit_logs`.
- **Criterio de Aceptación (DoD):**
  * Todo intento de `update()` o `delete()` en `triage_audit_logs` lanza excepción y es bloqueado por la aplicación.

---

#### 📌 CEN-41: [T3.8] Testing de casos de discrepancia y reordenamiento de cola por override clínico
- **Estado:** `[ ]` Por hacer
- **Responsable:** Gabriel Pineda
- **Estimación:** 8 Story Points (8 hs)
- **Archivos Objetivo:**
  * `tests/Feature/ClinicalOverrideAuditTest.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Validación de Integridad:**
     * Verificar que un paciente con triage Verde reclasificado a Rojo pase a encabezar la cola médica inmediatamente.
     * Verificar la inmutabilidad de `triage_audit_logs` e integridad de claves foráneas.
- **Criterio de Aceptación (DoD):**
  * `docker compose exec app php artisan test --filter=ClinicalOverrideAuditTest` pasa en verde.

---

## 🏃 Sprint 4: Cola de Atención Médica en Tiempo Real y Dashboard
- **Épica:** `CEN-19`
- **Estado:** `[ ]` Por hacer (Inicio: 18/11/2026 - Fin: 21/11/2026) | **Puntos:** 36 SP
- **Alcance:** Cola de espera médica dinámica con ordenamiento MTS, llamada a consultorio y cierre de consulta.

### 📖 HU03: Gestión de Cola Médica en Tiempo Real (`CEN-26`, 20 SP)

#### 📌 CEN-42: [T4.1] Diseño y maquetación del panel médico con ordenamiento dinámico por MTS
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 4 SP
- **Archivos Objetivo:** `app/Livewire/Doctor/MedicalQueue.php`, `resources/views/livewire/doctor/medical-queue.blade.php`.
- **Detalle Técnico:** Tarjetas de espera ordenadas por color MTS (Rojo al frente, luego Naranja, Amarillo, Verde, Azul). Cronómetro regresivo con tiempo restante antes del límite máximo de espera. Botón "Llamar a Consultorio" con SVG Heroicons.

#### 📌 CEN-43: [T4.2] Implementación de eventos y actualización reactiva de colas en Livewire
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 4 SP
- **Archivos Objetivo:** `app/Livewire/Doctor/MedicalQueue.php`.
- **Detalle Técnico:** Actualización reactiva mediante eventos Livewire (`#[On('patient-triaged')]`, `wire:poll.5s`). Ordenamiento SQL nativo:
  `ORDER BY CASE final_priority WHEN 'red' THEN 1 WHEN 'orange' THEN 2 WHEN 'yellow' THEN 3 WHEN 'green' THEN 4 WHEN 'blue' THEN 5 END, created_at ASC`.

#### 📌 CEN-44: [T4.3] Lógica de cambio de estados del paciente (Llamado, En Consulta, Atendido)
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 4 SP
- **Archivos Objetivo:** `app/Models/Triage.php`, `app/Models/Consultation.php`, `app/Enums/TriageStatus.php`.
- **Detalle Técnico:** Transiciones válidas: `pending_consultation` ➔ `called` ➔ `in_consultation` ➔ `completed` / `referred_sipec`. Registro de timestamps `started_at` y `completed_at`.

#### 📌 CEN-45: [T4.5] Optimización de consultas a la cola de espera mediante índices compuestos
- **Estado:** `[ ]` Por hacer | **Responsable:** Enrique M. González | **Estimación:** 3 SP
- **Archivos Objetivo:** `database/migrations/xxxx_add_indexes_to_triages_table.php`.
- **Detalle Técnico:** Índice compuesto en PostgreSQL: `CREATE INDEX idx_triages_active_queue ON triages (status, final_priority, created_at);`. Tiempo de respuesta < 20 ms probado con `EXPLAIN ANALYZE`.

#### 📌 CEN-46: [T4.6] Testing de flujo completo de sala de espera desde admisión hasta alta médica
- **Estado:** `[ ]` Por hacer | **Responsable:** Gabriel Pineda | **Estimación:** 5 SP
- **Archivos Objetivo:** `tests/Feature/MedicalQueueWorkflowTest.php`.
- **Detalle Técnico:** Test E2E de ciclo de vida completo: Admisión ➔ Evaluación MTS ➔ Cola de Espera ➔ Llamado Médico ➔ Atención ➔ Cierre.

### 📖 HU04: Registro Diagnóstico al Cierre de Consulta (`CEN-27`, 16 SP)

#### 📌 CEN-47: [T4.4] Formulario de registro de diagnóstico presuntivo y cierre de consulta
- **Estado:** `[ ]` Por hacer | **Responsable:** Valeria Budiño | **Estimación:** 8 SP
- **Archivos Objetivo:** `app/Livewire/Doctor/ConsultationRoom.php`, `resources/views/livewire/doctor/consultation-room.blade.php`.
- **Detalle Técnico:** Selección obligatoria de diagnóstico presuntivo estandarizado (Síndrome Febril Agudo inespecífico, Sospecha de Dengue, IRA, Gastroenteritis), campo de notas clínicas y prescripción. Al guardar, el paciente sale de la cola médica activa y se emite evento para telemetría.

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

