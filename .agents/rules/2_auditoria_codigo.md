# 🕵️ Regla de Cumplimiento: Auditoría y Control de Calidad de Código (Auto-Auditoría y Revisión de PR)

Esta regla rige cada vez que se invoque `/auditoria` (sea como **auto-auditoría obligatoria del autor antes de abrir PR** o como revisión de una PR de otro integrante):

1. **POSICIONAMIENTO Y CONTEXTO DE LA HU / SUB-VERTICAL**:
   - Si se audita la rama actual del autor, verificar `git status` limpio o los cambios pendientes. Si se audita una rama remota:
     ```bash
     git fetch origin
     git checkout <rama>
     git pull origin <rama>
     ```
   - Identificar las subtareas cubiertas por la rama en `.github/jira-scopes.conf` y `docs/team/guia_trabajo_paralelo_y_asignacion_hus.md`, leyendo únicamente ese bloque en `docs/backlog.md` (`grep -n`).
   - Cargar `docs/specs/00_contracts.md`, `docs/specs/global_spec.md` y la spec modular afectada.

2. **AUDITORÍA DE ESTILO Y TESTS (Pint + PHPUnit)**:
   - Ejecutar `./vendor/bin/pint --test` y `docker compose exec app php artisan test` (y `pytest` si la rama modifica `backend_analytics/`).
   - 🚫 **BLOQUEO INMEDIATO**: Si falla algún test o Pint, emitir `VEREDICTO: CAMBIOS NECESARIOS` detallando los fallos.

3. **INSPECCIÓN SEMÁNTICA PROFUNDA (`git diff origin/develop...HEAD`)**:
   El CI valida formato y tests; `/auditoria` busca fallos semánticos y de arquitectura:
   1. **Contratos (`00_contracts.md`) y Spec Modular:** ¿Cumple todos los RFs, enums, eventos (`ConsultationCompleted`), schemas JSON y criterios Gherkin?
   2. **Seguridad, RBAC y Privacidad (Ley N° 25.326):** Autorización explícita en Policies/Gates (`$this->authorize`), ausencia de queries N+1, y garantía estricta de que ninguna telemetría/log expone DNI, nombre, teléfono o domicilio.
   3. **Protocolo MTS:** Respeto a los 5 colores Manchester y auditoría inmutable (`triage_audit_logs`).
   4. **Estándar de Iconografía:** Cero emojis de texto en vistas Blade/componentes; solo SVGs vectoriales.
   5. **Calidad de Tests:** Verificar que los tests validen comportamiento real (casos borde, HTTP 403/422, persistencia) y no aserciones triviales.
   6. **Anti-conflictos (`docs/`):** Confirmar que no se modificaron innecesariamente `docs/backlog.md` ni `docs/changelog.md` en ramas de features/HUs.
   7. **Integridad de Claves Jira (Bloqueante):** Verificar que el nombre de rama tenga como máximo una clave `CEN-xxx` y que todos los commits propios (`git log origin/develop..HEAD --no-merges --format=%B`), título y descripción de PR solo contengan claves autorizadas en `.github/jira-scopes.conf` (o cero claves en ramas sin ticket). Cualquier clave ajena obliga a emitir `VEREDICTO: CAMBIOS NECESARIOS`.

4. **SMOKE TESTING VISUAL**:
   - Si hay cambios UI/Livewire, validar que la pantalla cargue sin HTTP 500, errores de consola JS ni trazas en `storage/logs/laravel.log`.

5. **EMISIÓN DEL VEREDICTO FORMAL**:
   Encabezar obligatoriamente el informe con:
   - **`VEREDICTO: APROBADO`**: Listo para pegar en la descripción de la PR y habilitar merge humano en GitHub.
   - **`VEREDICTO: CAMBIOS NECESARIOS`**: Lista numerada con `archivo:línea`, defecto y corrección exacta.

6. **FUSIÓN SOLO CON CONFIRMACIÓN HUMANA (PROHIBIDO AUTO-MERGE)**:
   - 🚫 Nunca fusionar automáticamente. En auto-auditoría, dejar el veredicto listo para la PR; en revisión de PR ajena, esperar el "OK" explícito humano antes de asistir con `gh pr merge` o merge en GitHub.
