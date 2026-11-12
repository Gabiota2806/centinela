# 📝 Registro de Cambios (Changelog) - Centinela

Todas las modificaciones notables a este proyecto serán documentadas en este archivo.
El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y se adhiere a [Semantic Versioning](https://semver.org/lang/es/).

---

## [Unreleased]
### Added
- `CEN-14`: Migraciones del esquema relacional y geoespacial en PostgreSQL 18 / PostGIS 3.7 para barrios con delimitación poligonal espacial e índice GIST (`neighborhoods`), padrón demográfico de pacientes con DNI único y soft deletes (`patients`), evaluaciones clínicas de triage con signos vitales en JSONB (`triages`), bitácora inmutable de auditoría para *Clinical Override* sin `updated_at` (`triage_audit_logs`) y consultas médicas de guardia (`consultations`).
- `CEN-14`: Modelos Eloquent (`Neighborhood`, `Patient`, `Triage`, `TriageAuditLog`, `Consultation`) con relaciones, casteo de signos vitales, inmutabilidad estricta bloqueando modificaciones/borrados en `TriageAuditLog` y relaciones inversas en `User`.
- `CEN-14`: Enums nativos PHP 8.3 de dominio: `TriagePriority` (5 colores Manchester, niveles 1-5, tiempos objetivo de espera y badges Tailwind), `TriageStatus` y `ConsultationStatus`.
- `CEN-14`: Diccionario de datos y diagrama Entidad-Relación (ERD) en `docs/database_dictionary.md` y actualización de `docs/specs/global_spec.md`.
- `CEN-14`: Batería de pruebas automatizadas en PHPUnit 12 (`DatabaseMigrationsTest` y `ModelRelationshipsAndCastsTest`) y factories para cada modelo del ecosistema.
- `CEN-15`: Roles del personal con el Enum `UserRole` del backlog (`admission`, `triage_nurse`, `doctor`, `director`), métodos `isAdmission()`/`isTriageNurse()`/`isDoctor()`/`isDirector()` y Gates `access-admission`, `access-triage`, `access-doctor` y `access-analytics`.
- `CEN-15`: Redirección por rol después del login (`/pacientes`, `/triage`, `/consultas`, `/dashboard`) con pantallas mínimas protegidas por Gate, login en español, rol visible en la barra superior y menú lateral filtrado por permisos. Spec modular `docs/specs/auth/spec.md`.

### Changed
- `CEN-15`: Migración `update_role_values_on_users_table` (default `admission` y conversión de valores previos); la migración original de `users` usa el valor literal histórico.
- `CEN-17`: Contenerización integral del entorno de desarrollo (`Dockerfile` y `docker-compose.yml` con `PHP 8.5.12`, `Composer 2.11.2`, `Node.js 26.13.0`, `PostgreSQL 18 + PostGIS 3.7` y usuario no-root `1000:1000`), arranque automático de `php artisan serve` y `npm run dev` en segundo plano, pruebas de integración en `DockerEnvironmentTest` y estandarización de directivas agénticas (`docker compose exec app ...`).
- `CEN-13`: Contenedor Docker `centinela_postgis` (`postgis/postgis:18-3.7`) con PostgreSQL 18 y extensión espacial PostGIS 3.7 configurado en puerto 5432 y volumen persistente.
- `CEN-13`: Configuración de entorno y conexión `pgsql` con search path `centinela,public`, base de datos aislada `centinela_test` en `phpunit.xml` con script de inicialización `docker/init-testing-db.sql`, y suite de integración `PostgisConnectionTest` con tipado estricto y validación de PostgreSQL 18, PostGIS 3.7 y search_path.
- `CEN-12`: Design System en Tailwind CSS v3 (colores MTS del backlog, marca, neutros, feedback y riesgo), tipografía Plus Jakarta Sans empaquetada localmente (sin CDN, apta para modo offline) y spec modular `docs/specs/ui/spec.md`.
- `CEN-12`: Layout `guest` de pantalla dividida con branding de Centinela y layout `app` con barra superior (nodo CAPS y perfil activo) y barra lateral colapsable / menú deslizable en tablet y celular.
- `CEN-12`: Componentes `application-logo` (isotipo de Centinela), `centinela-logo`, `icon` (Heroicons + Lucide) y `sidebar-link`; configuración `config/centinela.php` (`CENTINELA_NODE_NAME`).
- Inicialización del entorno agéntico multiherramienta (Google Antigravity, OpenCode y Claude Code).
- Especificación técnica global (`docs/specs/global_spec.md`) y plantilla modular SDD.
- Configuración de subagentes en `.opencode/agents/` (Coordinator, Planner, Implementer, Reviewer).
- Reglas y skills de desarrollo y auditoría en `.agents/` configuradas para PHPUnit, Livewire y Tailwind v3.
