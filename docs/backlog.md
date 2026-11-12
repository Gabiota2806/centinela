# 📋 Tablero de Backlog del Proyecto (Espejo Jira Cloud - CEN)

> **MANIFIESTO OPERATIVO PARA ASISTENTES AGÉNTICOS (Antigravity, Claude Code, OpenCode):**
> Este archivo es la **fuente de verdad técnica local** del tablero Jira Cloud (`CEN`). Los agentes deben leer las especificaciones, modelos de datos, rutas y criterios Gherkin aquí estipulados antes de redactar código o proponer cambios, evitando suposiciones o patrones no consensuados.
> 
> **Convención de Estados:**
> - `[ ]` **Por hacer (To Do)**
> - `[/]` **En curso (In Progress)**
> - `[x]` **Listo (Done)**

---

## 🏃 Sprint 1: Relevamiento, Análisis de Dominio y Diseño Arquitectónico
- **Épica:** `CEN-1`
- **Estado:** ✅ **Listo (Done)**
- **Duración:** 7 días (Semana 1) | **Cómputo Total:** 24 Story Points (72 hs cátedra)

### 📖 HU00: Relevamiento de Procesos, SRS y Arquitectura Técnica Edge-to-Hub
- **Clave:** `CEN-3` | **Estado:** `Listo` | **Puntos:** 24 SP | **Épica:** `CEN-1`
- **Spec Asociada:** `docs/specs/global_spec.md`
- **Subtareas Técnicas:**
  - [x] **`CEN-4`** - `[T1.1]` Relevamiento de procesos de atención y fichas de guardia en CAPS
    * **Asignado:** Enrique M. González | **Estimación:** 4 SP (4 hs)
    * **Entregable:** Análisis empírico del CAPS Dr. Luis María Codda (Lote 4).
    * **Validación:** Confirmación de interoperabilidad con fichas SFI, SIGHO 4.0 y resiliencia offline.
  - [x] **`CEN-5`** - `[T1.2]` Especificación formal de requerimientos funcionales y no funcionales (SRS)
    * **Asignado:** Valeria Budiño | **Estimación:** 4 SP (4 hs)
    * **Entregable:** Matriz formal de RF01 a RF12 y RNF01 a RNF06 en el Plan de Trabajo.
  - [x] **`CEN-6`** - `[T1.3]` Estudio del árbol de decisión del Sistema Manchester de Triage (MTS)
    * **Asignado:** Gabriel Pineda | **Estimación:** 4 SP (4 hs)
    * **Entregable:** Adaptación algorítmica de 5 niveles MTS con Clinical Override y tabla inmutable.
  - [x] **`CEN-7`** - `[T1.4]` Diseño de arquitectura técnica del sistema y diagramas de componentes
    * **Asignado:** Gabriel Pineda | **Estimación:** 4 SP (4 hs)
    * **Entregable:** Diagrama C4/Mermaid de topología Edge-to-Hub con Outbox Pattern (Ley 25.326).
  - [x] **`CEN-8`** - `[T1.5]` Diseño de wireframes y flujo de navegación de pantallas en Figma
    * **Asignado:** Valeria Budiño | **Estimación:** 4 SP (4 hs)
    * **Entregable:** Prototipo de pantallas de admisión, categorización MTS y visor médico.
  - [x] **`CEN-9`** - `[T1.6]` Recopilación de capas vectoriales GeoJSON de barrios de Formosa Capital
    * **Asignado:** Enrique M. González | **Estimación:** 4 SP (4 hs)
    * **Entregable:** Dataset vectorial de barrios normalizado en SRID 4326.

---

## 🏃 Sprint 2: Modelado de Persistencia, Repositorio y Autenticación Base (ACTIVO)
- **Épica:** `CEN-2`
- **Estado:** 🚧 **En curso (In Progress)**
- **Duración:** 7 días (Semana 2) | **Cómputo Total:** 28 Story Points (84 hs cátedra)

### 📖 HU01: Autenticación y Roles del Personal en Nodo Local
- **Clave:** `CEN-10` | **Estado:** `En curso` | **Puntos:** 28 SP | **Épica:** `CEN-2`
- **Spec Modular:** `docs/specs/auth/spec.md` y `docs/specs/global_spec.md`
- **Subtareas Técnicas Detalladas:**

---

#### 📌 CEN-11: [T2.1] Configuración de repositorios GitHub bajo GitFlow y tableros Kanban en Jira
- **Estado:** `[x]` Listo
- **Responsable:** Gabriel Pineda
- **Estimación:** 2 Story Points (2 hs)
- **Detalle Técnico y Entregables:**
  * Repositorio de código `Gabiota2806/centinela` bajo GitFlow con ramas `main` (protegida para releases) y `develop` (integración continua).
  * Tablero Kanban en Jira Cloud (`CEN`, Tablero 102) con Épicas CEN-1 y CEN-2 y automatizaciones de transición.

---

#### 📌 CEN-12: [T2.2] Inicialización de proyecto Laravel y configuración de TailwindCSS
- **Estado:** `[x]` Listo
- **Responsable:** Valeria Budiño
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `tailwind.config.js`
  * `resources/css/app.css`
  * `resources/views/layouts/app.blade.php`
  * `resources/views/layouts/guest.blade.php`
  * `resources/views/components/`
- **Especificaciones Técnicas Obligatorias:**
  1. **Versión de Tailwind:** Mantener **Tailwind CSS v3** con configuración en `tailwind.config.js` (no migrar a v4 `@theme`).
  2. **Paleta Estandarizada MTS (Manchester Triage System):**
     * 🔴 Rojo (Emergencia): `#DC2626` / `bg-red-600 text-white` (Espera: 0 min).
     * 🟠 Naranja (Muy Urgente): `#EA580C` / `bg-orange-600 text-white` (Espera: 10 min).
     * 🟡 Amarillo (Urgente): `#EAB308` / `bg-yellow-500 text-black` (Espera: 60 min).
     * 🟢 Verde (Poco Urgente): `#16A34A` / `bg-green-600 text-white` (Espera: 120 min).
     * 🔵 Azul (No Urgente): `#2563EB` / `bg-blue-600 text-white` (Espera: 240 min).
  3. **Estándar de Iconografía Frontend:** Queda **TERMINANTEMENTE PROHIBIDO** usar emojis tipográficos (`🔴`, `⚠️`, `👤`) en vistas Blade o Livewire. Utilizar exclusivamente **SVG vectoriales de Heroicons o Lucide** con clases utilitarias (`w-4 h-4` o `w-5 h-5`), según `docs/specs/ui/spec.md`.
  4. **Diseño de Layouts:**
     * `guest.blade.php`: Tarjeta centrada limpia para login con branding institucional de Centinela.
     * `app.blade.php`: Sidebar de navegación responsivo colapsable con indicador de nodo CAPS y perfil activo.
- **Criterio de Aceptación (DoD):**
  * `npm run build` compila sin advertencias ni errores.
  * Renderizado probado en desktop (1080p) y tablet/móvil (768px/375px) sin saltos de layout.

---

#### 📌 CEN-13: [T2.3] Instalación y configuración de PostgreSQL con extensión espacial PostGIS
- **Estado:** `[x]` Listo
- **Responsable:** Enrique M. González
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `docker-compose.yml`
  * `.env` y `.env.example`
  * `config/database.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Contenedor Docker:** Imagen `postgis/postgis:18-3.7` (PostgreSQL 18 + PostGIS 3.7).
  2. **Configuración de Red y Puertos:**
     * Puerto: `5432:5432`.
     * Contenedor: `centinela_postgis`.
     * Volumen persistente: `postgres_data:/var/lib/postgresql/data`.
  3. **Variables de Conexión en `.env`:**
     ```env
     DB_CONNECTION=pgsql
     DB_HOST=127.0.0.1
     DB_PORT=5432
     DB_DATABASE=centinela
     DB_USERNAME=centinela
     DB_PASSWORD=centinela_secret
     ```
  4. **Habilitación de Extensión:**
     * Crear migración inicial o comando para ejecutar `CREATE EXTENSION IF NOT EXISTS postgis;`.
- **Criterio de Aceptación (DoD):**
  * `php artisan migrate` conecta sin errores de timeout ni de autenticación.
  * La consulta `SELECT PostGIS_Version();` devuelve la versión 3.7.x sobre PostgreSQL 18.

---

#### 📌 CEN-14: [T2.4] Diseño del diagrama entidad-relación y migraciones de base de datos
- **Estado:** `[x]` Listo
- **Responsable:** Enrique M. González
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `database/migrations/xxxx_create_neighborhoods_table.php`
  * `database/migrations/xxxx_create_patients_table.php`
  * `database/migrations/xxxx_create_triages_table.php`
  * `database/migrations/xxxx_create_triage_audit_logs_table.php`
  * `database/migrations/xxxx_create_consultations_table.php`
  * Modelos en `app/Models/` (`Neighborhood.php`, `Patient.php`, `Triage.php`, `TriageAuditLog.php`, `Consultation.php`).
- **Esquema Relacional y Tipos de Datos:**
  1. **`neighborhoods` (Barrios de Formosa):**
     * `id`: bigint (PK).
     * `name`: string (nombre oficial del barrio).
     * `geometry`: geometry(Polygon / MultiPolygon, 4326) con índice espacial GIST.
     * `risk_level`: string con default `'normal'`.
     * `timestamps()`.
  2. **`patients` (Pacientes de CAPS):**
     * `id`: bigint (PK).
     * `dni`: string(20) unique index.
     * `first_name`: string(100), `last_name`: string(100).
     * `birth_date`: date.
     * `gender`: string(20).
     * `phone`: string(50) nullable.
     * `address`: string(255).
     * `neighborhood_id`: foreignId constrained a `neighborhoods`.
     * `timestamps()`, `softDeletes()`.
  3. **`triages` (Evaluaciones Clínicas MTS):**
     * `id`: bigint (PK).
     * `patient_id`: foreignId constrained a `patients` (cascade on delete).
     * `user_id`: foreignId constrained a `users` (profesional que realizó la evaluación).
     * `symptoms`: text (motivo de consulta).
     * `vital_signs`: jsonb (`temperature`, `systolic_bp`, `diastolic_bp`, `heart_rate`, `respiratory_rate`, `spo2`).
     * `algorithmic_priority`: enum/string (`red`, `orange`, `yellow`, `green`, `blue`).
     * `final_priority`: enum/string (`red`, `orange`, `yellow`, `green`, `blue`).
     * `is_override`: boolean default `false`.
     * `target_wait_minutes`: integer (0, 10, 60, 120, 240).
     * `status`: string default `'pending_consultation'` (`pending_consultation`, `in_consultation`, `completed`, `cancelled`).
     * `timestamps()`.
  4. **`triage_audit_logs` (Bitácora Inmutable de Clinical Override):**
     * `id`: bigint (PK).
     * `triage_id`: foreignId constrained a `triages`.
     * `user_id`: foreignId constrained a `users`.
     * `original_priority`: string (prioridad calculada por algoritmo).
     * `overridden_priority`: string (prioridad modificada por criterio médico).
     * `override_reason`: text (fundamento clínico obligatorio).
     * `created_at`: timestamp (sin `updated_at` para garantizar inmutabilidad).
  5. **`consultations` (Atención Médica):**
     * `id`: bigint (PK).
     * `triage_id`: foreignId constrained a `triages`.
     * `doctor_id`: foreignId constrained a `users`.
     * `clinical_notes`: text nullable.
     * `diagnosis`: text nullable.
     * `prescription`: text nullable.
     * `status`: string default `'in_progress'` (`in_progress`, `completed`, `referred_sipec`).
     * `started_at`: timestamp, `completed_at`: timestamp nullable.
     * `timestamps()`.
- **Criterio de Aceptación (DoD):**
  * Todas las migraciones corren sin errores con `php artisan migrate:fresh`.
  * Índices de claves foráneas y espaciales creados correctamente.

---

#### 📌 CEN-15: [T2.5] Implementación de autenticación y autorización con roles (Laravel Breeze)
- **Estado:** `[x]` Listo
- **Responsable:** Valeria Budiño
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `app/Enums/UserRole.php`
  * `database/migrations/xxxx_add_role_to_users_table.php`
  * `app/Models/User.php`
  * `app/Providers/AppServiceProvider.php`
  * Controladores y vistas de Breeze (`resources/views/auth/login.blade.php`).
- **Especificaciones Técnicas Obligatorias:**
  1. **Enum Nativo PHP 8.3 (`app/Enums/UserRole.php`):**
     ```php
     namespace App\Enums;

     enum UserRole: string {
         case ADMISSION = 'admission';         // Mesa de entrada / Recepción
         case TRIAGE_NURSE = 'triage_nurse';   // Enfermero de Triage MTS
         case DOCTOR = 'doctor';               // Médico de Guardia
         case DIRECTOR = 'director';           // Director de CAPS / Autoridad Sanitaria

         public function label(): string {
             return match($this) {
                 self::ADMISSION => 'Admisión / Mesa de Entrada',
                 self::TRIAGE_NURSE => 'Enfermero de Triage',
                 self::DOCTOR => 'Médico de Guardia',
                 self::DIRECTOR => 'Dirección de CAPS',
             };
         }
     }
     ```
  2. **Cast en Modelo `User`:**
     * Columna: `role` (string) con default `'admission'`.
     * Cast en `$casts`: `'role' => UserRole::class`.
     * Métodos de conveniencia: `isAdmission()`, `isTriageNurse()`, `isDoctor()`, `isDirector()`.
  3. **Autorización con Laravel Gates/Policies (`AppServiceProvider`):**
     * Gate `access-admission`: roles `ADMISSION`, `DIRECTOR`.
     * Gate `access-triage`: roles `TRIAGE_NURSE`, `DOCTOR`, `DIRECTOR`.
     * Gate `access-doctor`: roles `DOCTOR`, `DIRECTOR`.
     * Gate `access-analytics`: rol `DIRECTOR`.
  4. **Redirección por Rol Post-Login:**
     * `ADMISSION` ➔ `/pacientes`
     * `TRIAGE_NURSE` ➔ `/triage`
     * `DOCTOR` ➔ `/consultas`
     * `DIRECTOR` ➔ `/dashboard`
- **Criterio de Aceptación (Gherkin):**
  ```gherkin
  Escenario: Inicio de sesión exitoso y redirección por rol
    Dado un usuario registrado con rol "triage_nurse"
    Cuando ingresa sus credenciales válidas en "/login"
    Entonces es autenticado en el sistema
    Y es redirigido a la pantalla "/triage"
    Y la vista muestra el nombre del profesional y su rol "Enfermero de Triage"

  Escenario: Denegación de acceso cruzado
    Dado un usuario autenticado con rol "admission"
    Cuando intenta navegar a la ruta "/consultas"
    Entonces el sistema responde con código HTTP 403 Forbidden
  ```

---

#### 📌 CEN-16: [T2.6] Pruebas unitarias de migraciones y modelos Eloquent con PostGIS
- **Estado:** `[x]` Listo
- **Responsable:** Gabriel Pineda
- **Estimación:** 6 Story Points (6 hs)
- **Archivos Objetivo:**
  * `tests/Feature/NeighborhoodSpatialTest.php`
  * `tests/Feature/TriageAuditLogImmutableTest.php`
  * `tests/Unit/UserRoleEnumTest.php`
  * `tests/Feature/AuthRoleAccessTest.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Framework:** Utilizar exclusivamente **PHPUnit 12** extendiendo de `Tests\TestCase` (no usar sintaxis de Pest).
  2. **Tests Espaciales:**
     * Verificar que un punto geográfico (lat/lng de prueba en Formosa Capital) se asocie correctamente a su polígono de barrio mediante funciones espaciales de PostGIS.
  3. **Test de Inmutabilidad de Auditoría:**
     * Crear un registro en `triage_audit_logs`.
     * Intentar ejecutar `update()` o `delete()` sobre la instancia y constatar que el modelo bloquee la mutación o lance excepción.
  4. **Tests de Autenticación y Gates:**
     * Validar que los 4 roles puedan autenticarse y que las rutas protegidas retornen 403 ante accesos no autorizados.
- **Criterio de Aceptación (DoD):**
  * `php artisan test` ejecuta la suite completa y reporta 100% de tests en verde.
  * Tiempo total de ejecución de la suite inferior a 5 segundos.

---

#### 📌 CEN-17: [T2.7] Contenerización integral del entorno de desarrollo (PHP 8.5.12, Node 26.13.0, Composer 2.11.2, PostgreSQL 18 / PostGIS 3.7) y actualización de directivas agénticas
- **Estado:** `[x]` Listo
- **Responsable:** Gabriel Pineda
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `Dockerfile`
  * `docker-compose.yml`
  * `README.md`
  * `AGENTS.md`
  * `.agents/rules/`
- **Especificaciones Técnicas Obligatorias:**
  1. **Runtime PHP:** `PHP 8.5.12` con extensiones `pdo_pgsql`, `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `intl`, `bcmath`, `opcache`.
  2. **Runtime JS/Node:** `Node.js 26.13.0` con `npm`.
  3. **Manejador de Paquetes PHP:** `Composer 2.11.2`.
  4. **Base de Datos Espacial:** Imagen `postgis/postgis:18-3.7` (PostgreSQL 18 + PostGIS 3.7).
  5. **Mapeo de Usuario:** Usuario de desarrollo no-root con `UID:GID` (1000:1000) idénticos al host para evitar conflictos con archivos creados por root.
  6. **Estandarización Agéntica:** Documentación de arranque en `README.md` y actualización de directivas en `AGENTS.md` y `.agents/rules/` para uso de `docker compose exec app ...`.
- **Criterio de Aceptación (DoD):**
  * `docker compose up -d --build` construye y levanta los servicios `app` y `postgres` sin fallos.
  * `docker compose exec app php -v` retorna `PHP 8.5.12`.
  * `docker compose exec app node -v` retorna `v26.13.0`.
  * `docker compose exec app composer --version` retorna `2.11.2`.
  * `docker compose exec postgres psql -U centinela -d centinela -c "SELECT PostGIS_Version();"` retorna versión `3.7` sobre PostgreSQL 18.
  * `docker compose exec app php artisan test` pasa el 100% de las pruebas en verde sin requerir dependencias en el host.
  * Los archivos generados por Artisan o npm conservan propietario `gabrielp2806:gabrielp2806`.

---

## 🏃 Sprint 3: Módulo de Triage Clínico (Sistema Manchester), Clinical Override y Admisión
- **Épica:** `CEN-18`
- **Estado:** `[ ]` Por hacer (Inicio: 12/11/2026 - Fin: 17/11/2026) | **Hito:** 🎯 **Prototipo Funcional Core Clínico**
- **Cómputo Total:** 36 Story Points (108 hs cátedra)

### 📖 HU02: Admisión y Clasificación de Triage (Sistema Manchester)
- **Clave:** `CEN-24` | **Estado:** `Por hacer` | **Puntos:** 20 SP | **Épica:** `CEN-18`
- **Spec Modular:** `docs/specs/triage/spec.md` y `docs/specs/ui/spec.md`

#### 📌 CEN-34: [T3.1] Maquetación del formulario reactivo de admisión en Livewire y TailwindCSS
- **Estado:** `[ ]` Por hacer
- **Responsable:** Valeria Budiño
- **Estimación:** 4 Story Points (4 hs)
- **Archivos Objetivo:**
  * `app/Livewire/Patients/CreatePatient.php`
  * `resources/views/livewire/patients/create-patient.blade.php`
  * `resources/views/patients/create.blade.php`
  * `routes/web.php`
- **Especificaciones Técnicas Obligatorias:**
  1. **Componente Livewire 3:** Manejo reactivo de campos demográficos:
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
- **Estado:** `[ ]` Por hacer
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
- **Estado:** `[ ]` Por hacer
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
- **Estado:** `[ ]` Por hacer
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
- **Estado:** `[ ]` Por hacer
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
- **Estado:** `[ ]` Por hacer
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

