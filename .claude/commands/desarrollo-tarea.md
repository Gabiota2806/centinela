---
description: Inicia el ciclo de vida de desarrollo de una subtarea técnica en Centinela (TDD, smoke test, PR sin auto-merge).
argument: "[CLAVE] [modo]"
---

Ejecuta el ciclo de vida de desarrollo de la tarea técnica indicada ($ARGUMENTS) siguiendo rigurosamente las directivas de Centinela:

1. **Lectura de la Skill y Reglas:**
   - En `docs/backlog.md` el **Sprint Activo** está siempre al inicio. Para ahorrar tokens, localiza primero la tarea con `grep -n` y lee solo su rango de líneas (no leas `docs/backlog_archive.md` salvo consulta histórica).
   - Lee el archivo `.agents/skills/desarrollo-tarea/SKILL.md` y `.agents/rules/1_desarrollo_tareas.md`.
   - Consulta la especificación técnica en `docs/specs/` correspondiente al módulo de la tarea y las directivas transversales en `docs/specs/global_spec.md`.
2. **Plan Técnico:**
   - Si no se especificó la clave de la tarea o el modo (local/remoto), solicita la información antes de continuar.
   - Formula un Plan Técnico estructurado (archivos a crear/modificar, migraciones, pruebas TDD con PHPUnit).
   - **Pausa obligatoria**: Espera el "OK" explícito del usuario antes de crear ramas o escribir código.
3. **Desarrollo y TDD:**
   - Crea la rama desde `develop` con una única clave Jira (`CEN-xxx-nombre`, o sin clave `CEN-` en tareas de mantenimiento/docs).
   - Aplica TDD: escribe primero las pruebas unitarias/integración en `tests/`.
   - Corre la suite con `docker compose exec app php artisan test` (debe estar 100% verde).
   - Si hay vistas o Livewire, valida ausencia de errores en consola JS y `laravel.log`.
   - Presenta la guía de QA manual y espera el "OK" del usuario.
4. **Pull Request (Sin Auto-Merge):**
   - Marca la tarea como `[x]` en `docs/specs/<modulo>/spec.md` y `docs/backlog.md`. Si es la última subtarea del Sprint Activo, migra el Sprint finalizado a `docs/backlog_archive.md` y promueve el siguiente como `(ACTIVO)` al inicio de `docs/backlog.md`.
   - **Regla estricta de clave Jira única (bloqueante en CI):** Verifica que en el **nombre de rama**, el **título y cuerpo del commit** y el **título y descripción de la PR** figure **únicamente** la clave `CEN-xxx` de la subtarea activa (o ninguna en ramas de mantenimiento). **NO menciones otras claves `CEN-xxx`** (ni épicas, ni HUs padre, ni dependencias previas, ni tareas futuras, ni rangos de cierre de Sprint): usa descripciones técnicas/funcionales o códigos locales (`RF-xxx`, `T4.x`) sin el prefijo `CEN-`.
   - Haz commit con Conventional Commits referenciando solo la subtarea (`feat(CEN-xxx): ...`).
   - Empuja la rama a GitHub (`git push origin <rama>`).
   - Crea la Pull Request hacia `develop` usando `gh pr create` (si está disponible) o genera la URL directa de comparación en GitHub.
   - 🚫 **NUNCA HAGAS AUTO-MERGE**: Deja la PR abierta para revisión del equipo.
