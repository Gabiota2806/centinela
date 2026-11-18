---
description: Audita y valida rigurosamente una Pull Request o rama técnica en Centinela antes de su integración.
argument: "[CLAVE] [desarrollador] [modo]"
---

Ejecuta la auditoría y control de calidad de la tarea indicada ($ARGUMENTS) siguiendo rigurosamente las directivas de Centinela:

1. **Lectura de la Skill y Reglas:**
   - Localiza la tarea en `docs/backlog.md` con `grep -n` y lee únicamente ese rango de líneas para ahorrar tokens (solo consulta `docs/backlog_archive.md` si auditas un Sprint histórico).
   - Lee el archivo `.agents/skills/auditoria/SKILL.md` y `.agents/rules/2_auditoria_codigo.md`.
   - Consulta la especificación del módulo en `docs/specs/<modulo>/spec.md` y `docs/specs/global_spec.md`.
2. **Posicionamiento en Rama:**
   - Si no se indicaron los argumentos, consulta la clave de la tarea y la rama a auditar.
   - Ejecuta:
     ```bash
     git fetch origin
     git checkout <rama>
     git pull origin <rama>
     ```
3. **Ejecución de Pruebas y Diff:**
   - Corre la suite de pruebas del proyecto: `docker compose exec app php artisan test`.
   - Si falla algún test, detén la auditoría de inmediato con `VEREDICTO: CAMBIOS NECESARIOS`.
   - Revisa el diff frente a `develop`: `git diff origin/develop...HEAD`.
   - Verifica concordancia con los criterios Gherkin, tipado, reglas de negocio MTS (5 colores Manchester) y que no existan emojis en vistas (solo SVGs vectoriales).
   - **Integridad de Claves Jira (Bloqueante):** Revisa el nombre de rama, los commits propios (`git log origin/develop..HEAD --no-merges --format=%B`), el título y la descripción de la PR y el check `jira-key-guard`. Si aparece cualquier clave `CEN-xxx` ajena a la subtarea (épicas, HUs, dependencias, rangos de Sprint) —o cualquier clave `CEN-xxx` en una rama sin ticket—, detén la aprobación y emite `VEREDICTO: CAMBIOS NECESARIOS`.
   - Si hay cambios de UI, navega y valida consola y `laravel.log`.
4. **Veredicto y Fusión:**
   - Emite el informe estructurado encabezado por:
     * `VEREDICTO: APROBADO` o `VEREDICTO: CAMBIOS NECESARIOS`.
   - Si el veredicto es APROBADO:
     * **Pausa obligatoria**: Solicita confirmación humana. NUNCA fusiones automáticamente.
     * Si con esta tarea se completa el 100% del Sprint Activo, verifica la rotación del Sprint hacia `docs/backlog_archive.md` y la promoción del nuevo Sprint `(ACTIVO)` al tope de `docs/backlog.md`.
     * Con el "OK" explícito del usuario, asiste en la fusión hacia `develop` (vía `gh pr merge` o enlace web) y limpia las ramas.
