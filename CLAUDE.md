# Instrucciones para Claude Code - Centinela

## Entorno de Ejecución Contenerizado (`docker compose exec app ...`)
- Levantar servicios (`app` y `postgres`): `docker compose up -d --build`
- Comandos Artisan: `docker compose exec app php artisan [comando]`
- Comandos Composer: `docker compose exec app composer [comando]`
- Comandos NPM: `docker compose exec app npm run dev` / `docker compose exec app npm run build`
- Ejecución de Pruebas: `docker compose exec app php artisan test`
- Stack en Contenedores: PHP 8.5.12, Composer 2.11.2, Node.js 26.13.0 y PostgreSQL 18 + PostGIS 3.7 (`postgis/postgis:18-3.7`)

---

## Flujo Multiagente y Especificaciones Modulares (SDD)
Este repositorio opera bajo flujo paralelo por Historia de Usuario / sub-vertical:
1. **Contratos y Specs Modulares**: Consulta siempre `docs/specs/00_contracts.md`, `docs/specs/global_spec.md` y `docs/specs/<modulo>/spec.md`.
2. **Guía de Ramas del Equipo**: Consulta `docs/team/guia_trabajo_paralelo_y_asignacion_hus.md` y `.github/jira-scopes.conf` para conocer la rama exacta y las subtareas permitidas en cada vertical.
3. **Backlog Optimizado (`docs/backlog.md` y `docs/backlog_archive.md`)**:
   - `docs/backlog.md` ubica siempre primero el **Sprint Activo**. Para ahorrar tokens, busca la clave con `grep -n "CEN-xxx" docs/backlog.md` y lee únicamente ese bloque.
   - `docs/backlog_archive.md` almacena los Sprints finalizados (`[x] Listo`). No lo leas salvo consulta histórica explícita.
   - **Anti-conflictos:** **NO edites `docs/backlog.md` ni `docs/changelog.md` dentro de ramas de features/HUs**; se consolidan directamente en `develop` al cierre del día/bloque.
4. **Roles Operativos**:
   - **Planificación**: Un único plan técnico por HU/sub-vertical antes de escribir código.
   - **Implementación**: TDD estricto con `docker compose exec app php artisan test` y **1 commit por subtarea terminada** usando su propia clave `CEN-xxx`.
   - **Auto-Auditoría**: Antes de abrir la PR, ejecuta `/auditoria` sobre tu propia rama y pega el veredicto en la descripción de la PR.

---

## Reglas Inviolables
- **Cero Auto-Merge**: Nunca fusiones ramas automáticamente a `develop`. Deja la Pull Request abierta para aprobación y merge humano en GitHub.
- **Alcance Estricto de Claves Jira (`CEN-xxx`) en Ramas, Commits y PRs**: Jira Cloud transiciona tarjetas al crear ramas (`En curso`), abrir PRs (`En revisión`) y fusionar PRs (`Listo`).
  - El **nombre de la rama** lleva **una sola clave** `CEN-xxx` (la HU o sub-vertical asignada en `.github/jira-scopes.conf`, o **ninguna** en ramas de mantenimiento/docs).
  - En los **commits** (título y cuerpo) y en la **PR** (título y descripción) solo pueden figurar las claves autorizadas para esa rama en `.github/jira-scopes.conf` (o las subtareas hijas de esa HU en `docs/backlog.md`).
  - **PROHIBIDO** escribir cualquier clave `CEN-xxx` fuera de ese alcance (épicas, otras HUs, tareas de otros compañeros ni rangos de Sprint).
  - Para explicar contexto externo, usa nombres técnicos/funcionales (ej. "sobre `Triage::overridePriority()`", "cola médica", "Sprint 4") o códigos locales (`RF-CONS-01`, `T4.x`) **sin el prefijo `CEN-`**.
  - El incumplimiento falla el check bloqueante `jira-key-guard` y frena `/auditoria`. (Dentro de archivos de `docs/` las referencias cruzadas siguen permitidas).
- **TDD Obligatorio**: Todo cambio de lógica debe contar con su correspondiente prueba en PHPUnit (`php artisan test`).
- **Iconografía**: En vistas y componentes UI usa únicamente SVGs vectoriales (Heroicons / Lucide) con clases CSS. No uses emojis de texto.
- **Protocolo MTS**: Respetar los 5 colores Manchester y la auditoría clínica inmutable (*Clinical Override*).
