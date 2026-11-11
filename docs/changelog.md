# 📝 Registro de Cambios (Changelog) - Centinela

Todas las modificaciones notables a este proyecto serán documentadas en este archivo.
El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y se adhiere a [Semantic Versioning](https://semver.org/lang/es/).

---

## [Unreleased]
### Added
- `CEN-17`: Contenerización integral del entorno de desarrollo (`Dockerfile` y `docker-compose.yml` con `PHP 8.5.12`, `Composer 2.11.2`, `Node.js 26.13.0`, `PostgreSQL 18 + PostGIS 3.7` y usuario no-root `1000:1000`), arranque automático de `php artisan serve` y `npm run dev` en segundo plano, pruebas de integración en `DockerEnvironmentTest` y estandarización de directivas agénticas (`docker compose exec app ...`).
- `CEN-12`: Design System en Tailwind CSS v3 (colores MTS del backlog, marca, neutros, feedback y riesgo), tipografía Plus Jakarta Sans empaquetada localmente (sin CDN, apta para modo offline) y spec modular `docs/specs/ui/spec.md`.
- `CEN-12`: Layout `guest` de pantalla dividida con branding de Centinela y layout `app` con barra superior (nodo CAPS y perfil activo) y barra lateral colapsable / menú deslizable en tablet y celular.
- `CEN-12`: Componentes `application-logo` (isotipo de Centinela), `centinela-logo`, `icon` (Heroicons + Lucide) y `sidebar-link`; configuración `config/centinela.php` (`CENTINELA_NODE_NAME`).
- Inicialización del entorno agéntico multiherramienta (Google Antigravity, OpenCode y Claude Code).
- Especificación técnica global (`docs/specs/global_spec.md`) y plantilla modular SDD.
- Configuración de subagentes en `.opencode/agents/` (Coordinator, Planner, Implementer, Reviewer).
- Reglas y skills de desarrollo y auditoría en `.agents/` configuradas para PHPUnit, Livewire y Tailwind v3.
