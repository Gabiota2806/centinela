# 📝 Registro de Cambios (Changelog) - Centinela

Todas las modificaciones notables a este proyecto serán documentadas en este archivo.
El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y se adhiere a [Semantic Versioning](https://semver.org/lang/es/).

---

## [Unreleased]
### Added
- `CEN-13`: Contenedor Docker `centinela_postgis` (`postgis/postgis:16-3.4`) con PostgreSQL 16 y extensión espacial PostGIS 3.4 configurado en puerto 5432 y volumen persistente.
- `CEN-13`: Configuración de entorno y conexión `pgsql` con search path `centinela,public`, base de datos aislada `centinela_test` en `phpunit.xml` con script de inicialización `docker/init-testing-db.sql`, y suite de integración `PostgisConnectionTest` con tipado estricto y validación de PostGIS 3.4 y search_path.
- `CEN-12`: Design System en Tailwind CSS v3 (colores MTS del backlog, marca, neutros, feedback y riesgo), tipografía Plus Jakarta Sans empaquetada localmente (sin CDN, apta para modo offline) y spec modular `docs/specs/ui/spec.md`.
- `CEN-12`: Layout `guest` de pantalla dividida con branding de Centinela y layout `app` con barra superior (nodo CAPS y perfil activo) y barra lateral colapsable / menú deslizable en tablet y celular.
- `CEN-12`: Componentes `application-logo` (isotipo de Centinela), `centinela-logo`, `icon` (Heroicons + Lucide) y `sidebar-link`; configuración `config/centinela.php` (`CENTINELA_NODE_NAME`).
- Inicialización del entorno agéntico multiherramienta (Google Antigravity, OpenCode y Claude Code).
- Especificación técnica global (`docs/specs/global_spec.md`) y plantilla modular SDD.
- Configuración de subagentes en `.opencode/agents/` (Coordinator, Planner, Implementer, Reviewer).
- Reglas y skills de desarrollo y auditoría en `.agents/` configuradas para PHPUnit, Livewire y Tailwind v3.
