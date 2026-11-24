---
name: desarrollo-tarea
description: Guía y flujo operativo paso a paso para desarrollar una Historia de Usuario, sub-vertical o subtarea en Centinela (1 rama por bloque, commits por subtarea, TDD, QA manual, auto-auditoría y PR sin auto-merge).
---

# 🚀 Skill: Desarrollo Paralelo por HU / Sub-vertical

Este skill guía a Antigravity durante el ciclo completo de desarrollo de una Historia de Usuario, sub-vertical o subtarea individual en Centinela.

---

## 🔄 Flujo Operativo Paso a Paso

### 1. Activación, Detección de Alcance y Plan Único
* **Activación Híbrida (`/desarrollo-tarea`)**:
  - Inline: `/desarrollo-tarea CEN-28 local` → extrae clave y modo directamente.
  - Asistida: si falta clave o modo, usar `ask_question`.
* **Detección de Rama y Subtareas**:
  - Buscar la clave en `.github/jira-scopes.conf` y `docs/team/guia_trabajo_paralelo_y_asignacion_hus.md` para obtener:
    1. Nombre exacto de la rama asignada (con una única clave `CEN-xxx`).
    2. Lista completa de subtareas `CEN-xxx` incluidas en esa rama (o solo esa clave si es una tarea individual).
  - Leer únicamente esas subtareas en `docs/backlog.md` (`grep -n "CEN-xxx" docs/backlog.md`; no leer `docs/backlog_archive.md`).
  - Leer `docs/specs/00_contracts.md`, `docs/specs/global_spec.md` y la spec modular (`docs/specs/<modulo>/spec.md`).
* **Plan Técnico Unificado**:
  1. Nombre exacto de la rama y subtareas incluidas.
  2. Archivos a crear/modificar (verificando cero solapamiento con otros carriles).
  3. Orden de implementación y estrategia TDD por subtarea.
* 🚫 **PAUSA**: Esperar el "OK" explícito del desarrollador.
* Tras el "OK", sincronizar `develop`, crear la rama y subirla (`git push -u origin <rama>`).

---

### 2. Implementación Iterativa con 1 Commit por Subtarea
Para cada subtarea del bloque, en orden:
* **TDD Estricto**: escribir pruebas en `tests/` cubriendo los criterios Gherkin y casos borde (403, 422, offline/privacidad).
* Implementar la lógica respetando `docs/specs/00_contracts.md` y `AGENTS.md` (solo SVGs vectoriales, cero emojis).
* Verificar suite y formato:
  - `docker compose exec app php artisan test` (100% verde)
  - `docker compose exec app ./vendor/bin/pint --test` (limpio)
  - Si toca `backend_analytics/`, ejecutar también su suite `pytest`.
* **Commit atómico por subtarea** usando únicamente la clave de esa subtarea autorizada en `.github/jira-scopes.conf` (`feat(CEN-48): ...`).
* **Regla anti-conflictos:** **NO editar `docs/backlog.md` ni `docs/changelog.md`** en la rama de feature (se consolidan directo en `develop` al cierre del día).

---

### 3. Pre-Verificación y Guía de QA Manual Unificada
Al terminar todas las subtareas de la rama:
* Si hay cambios UI/Livewire, validar ausencia de errores HTTP 500, consola JS y `storage/logs/laravel.log` en `http://localhost:8000`.
* Entregar una **Guía de QA Manual unificada** paso a paso para toda la HU/sub-vertical.
* 🚫 **PAUSA OBLIGATORIA**: Esperar el "OK" explícito del desarrollador confirmando sus pruebas manuales.

---

### 4. Auto-Auditoría y Pull Request (Sin Auto-Merge)
Tras el "OK" de QA manual:
* Ejecutar el flujo de **`/auditoria` sobre la rama actual** (validando `00_contracts.md`, spec RF por RF, RBAC, Ley N° 25.326, N+1, SVGs, tests reales y claves Jira permitidas).
* Corregir cualquier hallazgo hasta obtener `VEREDICTO: APROBADO`.
* Subir los commits (`git push origin <rama>`).
* Crear la Pull Request hacia `develop` (sin claves `CEN-xxx` fuera de `.github/jira-scopes.conf`) pegando el resumen de `/auditoria` y el checklist de QA:
  1. GitHub MCP (`create_pull_request`) o `gh pr create --base develop --head <rama> ...`
  2. Si no hay autenticación programática, entregar URL `compare` + bloque Markdown listo para copiar.
* 🚫 **PROHIBIDO EL AUTO-MERGE**: Dejar la PR abierta para aprobación/merge humano en GitHub y recordar al desarrollador que ya puede pasar a su siguiente rama independiente desde `develop`.
