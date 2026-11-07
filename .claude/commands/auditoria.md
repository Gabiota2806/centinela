---
description: Audita y valida rigurosamente una Pull Request o rama técnica en Centinela antes de su integración.
argument: "[CLAVE] [desarrollador] [modo]"
---

Ejecuta la auditoría y control de calidad de la tarea indicada ($ARGUMENTS) siguiendo rigurosamente las directivas de Centinela:

1. **Lectura de la Skill y Reglas:**
   - Consulta la tarea correspondiente en `docs/backlog.md` para conocer los resultados esperados y criterios de aceptación (DoD).
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
   - Corre la suite de pruebas del proyecto: `php artisan test`.
   - Si falla algún test, detén la auditoría de inmediato con `VEREDICTO: CAMBIOS NECESARIOS`.
   - Revisa el diff frente a `develop`: `git diff origin/develop...HEAD`.
   - Verifica concordancia con los criterios Gherkin, tipado, reglas de negocio MTS (5 colores Manchester) y que no existan emojis en vistas (solo SVGs vectoriales).
   - Si hay cambios de UI, navega y valida consola y `laravel.log`.
4. **Veredicto y Fusión:**
   - Emite el informe estructurado encabezado por:
     * `VEREDICTO: APROBADO` o `VEREDICTO: CAMBIOS NECESARIOS`.
   - Si el veredicto es APROBADO:
     * **Pausa obligatoria**: Solicita confirmación humana. NUNCA fusiones automáticamente.
     * Con el "OK" explícito del usuario, asiste en la fusión hacia `develop` (vía `gh pr merge` o enlace web) y limpia las ramas.
