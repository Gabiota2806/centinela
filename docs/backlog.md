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
- **Duración:** 7 días (Semana 2) | **Cómputo Total:** 24 Story Points (72 hs cátedra)

### 📖 HU01: Autenticación y Roles del Personal en Nodo Local
- **Clave:** `CEN-10` | **Estado:** `En curso` | **Puntos:** 24 SP | **Épica:** `CEN-2`
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
- **Estado:** `[ ]` Por hacer
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
- **Estado:** `[ ]` Por hacer
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
- **Estado:** `[ ]` Por hacer
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

## 🔮 Próximos Sprints Planificados (Plan de Trabajo Cátedra)

| Sprint | Épica / Nombre | HU Principal | Alcance Técnico | Estimación |
| :--- | :--- | :--- | :--- | :---: |
| **Sprint 3** | Módulo de Triage Clínico (MTS), Clinical Override y Admisión | `HU02` | Formulario Livewire, árbol MTS 5 colores, selector barrial y `triage_audit_logs`. | 36 SP |
| **Sprint 4** | Cola de Atención Médica en Tiempo Real y Dashboard | `HU03` | Cola dinámica con reordenamiento clínico, atención médica y prescripción. | 36 SP |
| **Sprint 5** | Ingestión Meteorológica y Capa de Anonimización | `HU04` | Integración Open-Meteo, Outbox Pattern y pipeline de anonimización (Ley 25.326). | 36 SP |
| **Sprint 6** | Motor Analítico de Alerta Temprana con Scikit-Learn | `HU05` | Microservicio FastAPI, Random Forest / Detección de Anomalías febriles. | 36 SP |
| **Sprint 7** | Visualización Geoespacial Barrial en Leaflet.js | `HU06` | Mapas de calor coropléticos con capas GeoJSON de barrios de Formosa Capital. | 36 SP |
| **Sprint 8** | Pruebas Integrales (QA), Green Software y Documentación | `HU07` | Stress testing, auditoría de Green Software, manuales y defensa de tesis. | 36 SP |
