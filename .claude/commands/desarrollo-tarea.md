---
description: Inicia el ciclo de desarrollo paralelo de una HU, sub-vertical o subtarea en Centinela (1 rama por bloque, commits por subtarea, TDD, QA manual, auto-auditoría y PR sin auto-merge).
argument: "[CLAVE_HU_O_TAREA] [modo]"
---

Ejecuta el ciclo de vida de desarrollo del bloque indicado ($ARGUMENTS) siguiendo rigurosamente las directivas de Centinela:

1. **Detección de Alcance y Specs:**
   - Si falta la clave o el modo (`local`/`remoto`), pídelo antes de continuar.
   - Consulta `.github/jira-scopes.conf` y `docs/team/guia_trabajo_paralelo_y_asignacion_hus.md` para obtener el nombre exacto de rama y **todas las subtareas `CEN-xxx` asignadas a esa rama**.
   - Localiza únicamente esas subtareas en `docs/backlog.md` con `grep -n` (no leas `docs/backlog_archive.md`).
   - Lee `docs/specs/00_contracts.md`, `docs/specs/global_spec.md` y la spec modular (`docs/specs/<modulo>/spec.md`).
2. **Plan Técnico Único:**
   - Formula un único Plan Técnico ordenado por subtarea (`CEN-xx` → `CEN-yy` → ...) indicando el nombre exacto de rama, archivos a crear/modificar y estrategia TDD.
   - **Pausa obligatoria**: Espera el "OK" explícito del usuario antes de crear la rama o escribir código.
3. **Desarrollo Iterativo con 1 Commit por Subtarea:**
   - Sincroniza `develop`, crea la rama (1 sola clave `CEN-xxx` en el nombre) y súbela (`git push -u origin <rama>`).
   - Para cada subtarea del bloque:
     * Aplica TDD escribiendo pruebas en `tests/` y asegurando `docker compose exec app php artisan test` 100% verde y `docker compose exec app ./vendor/bin/pint --test` limpio.
     * Respeta `docs/specs/00_contracts.md` y usa exclusivamente SVGs vectoriales (cero emojis).
     * Haz **1 commit por subtarea terminada** usando su propia clave autorizada en `.github/jira-scopes.conf` (`feat(CEN-47): ...`).
   - **Anti-conflictos:** **NO edites `docs/backlog.md` ni `docs/changelog.md`** en la rama de feature (se consolidan directo en `develop` al final del día).
4. **QA Manual Unificada y Auto-Auditoría:**
   - Si hay vistas o Livewire, valida ausencia de errores en consola JS y `storage/logs/laravel.log` en `http://localhost:8000`.
   - Presenta una **única Guía de QA Manual** cubriendo toda la rama y espera el "OK" del usuario.
   - Tras el "OK", ejecuta **`/auditoria` sobre la rama actual** y corrige cualquier observación hasta obtener `VEREDICTO: APROBADO`.
5. **Pull Request (Sin Auto-Merge):**
   - Verifica que el nombre de rama, todos los commits y el título/descripción de la PR solo contengan claves autorizadas en `.github/jira-scopes.conf` (o cero claves en mantenimiento/docs).
   - Empuja la rama (`git push origin <rama>`) y crea la PR hacia `develop` pegando el resumen de `/auditoria` y el checklist de QA.
   - 🚫 **NUNCA HAGAS AUTO-MERGE**: Deja la PR abierta para aprobación/merge humano en GitHub.
