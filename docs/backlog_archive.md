# 🗄️ Archivo Histórico de Sprints Finalizados (Espejo Jira Cloud - CEN)

> **DIRECTIVA DE AHORRO DE TOKENS PARA AGENTES (Antigravity, Claude Code, OpenCode):**
> Este documento contiene **únicamente Sprints 100% finalizados (`[x] Listo`)** para trazabilidad académica y auditoría histórica del TFI.
> **NO LEAS ESTE ARCHIVO** durante el desarrollo o auditoría de tareas del Sprint Activo. Para trabajar en tareas activas o futuras, consulta exclusivamente `docs/backlog.md`.

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

## 🏃 Sprint 2: Modelado de Persistencia, Repositorio y Autenticación Base
- **Épica:** `CEN-2`
- **Estado:** ✅ **Listo (Done)**
- **Duración:** 7 días (Semana 2) | **Cómputo Total:** 28 Story Points (84 hs cátedra)

### 📖 HU01: Autenticación y Roles del Personal en Nodo Local
- **Clave:** `CEN-10` | **Estado:** `Listo` | **Puntos:** 28 SP | **Épica:** `CEN-2`
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
- **Estado:** ✅ **Listo (Done)** (Inicio: 12/11/2026 - Fin: 17/11/2026) | **Hito:** 🎯 **Prototipo Funcional Core Clínico**
- **Cómputo Total:** 36 Story Points (108 hs cátedra)

### 📖 HU02: Admisión y Clasificación de Triage (Sistema Manchester)
- **Clave:** `CEN-24` | **Estado:** `Listo` | **Puntos:** 20 SP | **Épica:** `CEN-18`
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
- **Clave:** `CEN-25` | **Estado:** `Listo` | **Puntos:** 16 SP | **Épica:** `CEN-18`
- **Spec Modular:** `docs/specs/triage/spec.md`

#### 📌 CEN-39: [T3.5] Componentes UX y modal de Clinical Override rápido con justificación diferida
- **Estado:** `[x]` Listo
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
- **Estado:** `[x]` Listo
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
