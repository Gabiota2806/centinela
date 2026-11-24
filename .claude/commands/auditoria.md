---
description: Ejecuta auto-auditoría previa a la PR o audita una rama de HU/sub-vertical en Centinela antes de integrarla a develop.
argument: "[CLAVE_O_RAMA] [desarrollador] [modo]"
---

Ejecuta la auditoría y control de calidad de la rama indicada ($ARGUMENTS) siguiendo rigurosamente las directivas de Centinela:

1. **Contexto y Posicionamiento:**
   - Si se ejecuta como **auto-auditoría** sobre la rama local actual, trabaja directo sobre `HEAD`. Si se audita una rama remota de otro integrante, ejecuta `git fetch origin && git checkout <rama> && git pull origin <rama>`.
   - Consulta `.github/jira-scopes.conf` para conocer las claves permitidas de esa rama y localiza solo esas subtareas en `docs/backlog.md` con `grep -n`.
   - Lee `docs/specs/00_contracts.md`, `docs/specs/global_spec.md` y la spec modular (`docs/specs/<modulo>/spec.md`).
2. **Formato y Suite de Pruebas:**
   - Ejecuta `docker compose exec app ./vendor/bin/pint --test` y `docker compose exec app php artisan test` (y `pytest` si modifica `backend_analytics/`).
   - Si falla cualquier prueba o Pint, detén la auditoría de inmediato con `VEREDICTO: CAMBIOS NECESARIOS`.
3. **Inspección Semántica del Diff (`git diff origin/develop...HEAD`):**
   - Valida cumplimiento RF por RF contra `docs/specs/00_contracts.md` y la spec modular (criterios Gherkin, enums, eventos).
   - Valida RBAC (`$this->authorize` / Gates), ausencia de queries N+1, respeto a los 5 colores MTS e **irreversibilidad de anonimización (Ley N° 25.326: cero DNI/nombre/teléfono/domicilio en telemetría o logs)**.
   - Verifica cero emojis en vistas/componentes (solo SVGs vectoriales) y que los tests validen casos reales/borde (no aserciones vacías).
   - Verifica que no se hayan editado `docs/backlog.md` ni `docs/changelog.md` dentro de ramas de features/HUs.
   - **Integridad de Claves Jira (Bloqueante):** Revisa nombre de rama (máx. 1 clave `CEN-xxx`), todos los commits propios (`git log origin/develop..HEAD --no-merges --format=%B`), título y descripción de PR contra `.github/jira-scopes.conf`. Cualquier clave fuera del alcance autorizado obliga a emitir `VEREDICTO: CAMBIOS NECESARIOS`.
   - Si hay cambios UI, valida ausencia de errores en consola JS y `storage/logs/laravel.log` en `http://localhost:8000`.
4. **Veredicto y Cierre (Sin Auto-Merge):**
   - Encabeza el informe obligatoriamente con **`VEREDICTO: APROBADO`** (con resumen compacto listo para pegar en la PR) o **`VEREDICTO: CAMBIOS NECESARIOS`** (lista numerada `archivo:línea`).
   - 🚫 **NUNCA fusiones automáticamente.** Espera siempre confirmación humana explícita.
