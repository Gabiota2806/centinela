# 🛡️ Regla de Cumplimiento: Metodología de Desarrollo Paralelo por HU / Sub-vertical (Centinela)

Esta regla rige de forma permanente en todas las sesiones y conversaciones en Centinela:

1. **ACTIVACIÓN HÍBRIDA, DETECCIÓN DE ALCANCE Y CREACIÓN DE RAMA**:
   - **Activación Híbrida (Slash Command `/desarrollo-tarea`)**:
     * **Invocación Inline**: Si el desarrollador escribe `/desarrollo-tarea <CLAVE_HU_O_SUBTAREA> <modo>` (ej. `/desarrollo-tarea CEN-28 local`), extrae directamente los parámetros y avanza de inmediato sin preguntas redundantes.
     * **Invocación Vacía / Asistida**: Si se invoca `/desarrollo-tarea` sin argumentos, activar `ask_question` para pedir la clave (`CEN-xxx`) y el modo (`Local` vs. `Remoto`).
   - **Detección Automática de Alcance (1 Rama = 1 HU o Sub-vertical)**:
     * Consultar `.github/jira-scopes.conf` y `docs/team/guia_trabajo_paralelo_y_asignacion_hus.md` para identificar el nombre exacto de la rama y **todas las subtareas `CEN-xxx` asignadas a esa rama**.
     * Consultar `docs/backlog.md` de forma quirúrgica (`grep -n "CEN-xxx" docs/backlog.md`) leyendo únicamente las subtareas de esa rama (sin leer `docs/backlog_archive.md`).
     * Consultar `docs/specs/00_contracts.md`, `docs/specs/global_spec.md` y la spec modular correspondiente (`docs/specs/<modulo>/spec.md`).
     * Inspeccionar migraciones en `database/migrations/` y modelos en `app/Models/`.
   - **Plan Técnico Único**: Presentar un único plan ordenado por subtarea (`CEN-xx` → `CEN-yy` → ...) y el nombre exacto de rama antes de tocar código.
   - 🚫 **PAUSA**: Esperar el "OK" explícito del desarrollador sobre el plan antes de crear la rama o escribir código.
   - Tras el "OK", sincronizar `develop`, crear la rama (con una única clave `CEN-xxx` en su nombre) y subirla a GitHub (`git push -u origin <rama>`) para que Jira la pase a *En curso*.

2. **REGLA ESTRICTA DE ALCANCE DE CLAVES JIRA EN RAMAS, COMMITS Y PRs**:
   - No modificar tarjetas en Jira Cloud mediante transitions API salvo pedido expreso. Jira transiciona automáticamente: rama (`En curso`), PR abierta (`En revisión`) y PR mergeada (`Listo`).
   - **Nombre de rama:** solo **una** clave `CEN-xxx` (la clave de HU o sub-vertical definida en `.github/jira-scopes.conf`, o ninguna en mantenimiento/docs).
   - **Commits (1 commit por subtarea terminada):** cada subtarea completada en la rama lleva su propia clave autorizada (`feat(CEN-48): ...`, `feat(CEN-49): ...`).
   - **PR (título y cuerpo):** solo pueden figurar claves dentro del alcance de `.github/jira-scopes.conf` para esa rama (o subtareas hijas de esa HU en `docs/backlog.md`).
   - **PROHIBIDO** mencionar claves `CEN-xxx` fuera de ese alcance (épicas, otras HUs, tareas de otros integrantes ni rangos de Sprint). Usar nombres técnicos/funcionales o códigos `RF-xxx`/`T4.x`.
   - El check bloqueante `jira-key-guard` y los hooks locales `.githooks/` validan esta regla.

3. **TDD ITERATIVO POR SUBTAREA, QA MANUAL UNIFICADA Y AUTO-AUDITORÍA**:
   - Implementar cada subtarea del bloque en orden con TDD (`docker compose exec app php artisan test` 100% en verde y `./vendor/bin/pint --test` limpio).
   - Al cerrar cada subtarea con tests verdes, realizar su commit atómico (`feat(CEN-xx): ...`).
   - **Anti-conflictos:** **NO editar `docs/backlog.md` ni `docs/changelog.md`** dentro de ramas de features/HUs (se consolidan directo en `develop` al cierre del día/bloque).
   - **Iconografía:** Prohibido usar emojis de texto en vistas Blade o componentes; usar exclusivamente SVGs vectoriales (`w-4 h-4` o `w-5 h-5`).
   - **Pre-Verificación y Guía de QA Manual Unificada**: Al completar todas las subtareas de la rama, verificar consola JS / `laravel.log` en `http://localhost:8000` (si aplica UI) y entregar una **única Guía de QA Manual** cubriendo toda la HU.
   - 🚫 **PAUSA OBLIGATORIA**: Esperar el "OK" explícito del desarrollador confirmando sus pruebas manuales (+ pruebas extra que agregue).

4. **AUTO-AUDITORÍA PREVIA Y PULL REQUEST (PROHIBIDO AUTO-MERGE)**:
   - Tras el "OK" de QA manual, ejecutar **`/auditoria` sobre la propia rama** verificando `php artisan test`, `pint --test`, concordancia RF por RF contra `00_contracts.md` y la spec modular, RBAC, Ley N° 25.326, ausencia de emojis e integridad de claves Jira.
   - Si surgen observaciones, corregirlas y re-validar hasta emitir `VEREDICTO: APROBADO`.
   - Empujar la rama (`git push origin <rama>`) y crear la PR hacia `develop` pegando el resumen de `/auditoria` y el checklist de QA.
   - 🚫 **PROHIBIDO EL AUTO-MERGE**: Nunca ejecutar `gh pr merge` ni fusionar a `develop` sin aprobación humana. El desarrollador puede iniciar de inmediato su siguiente rama independiente desde `develop` sin esperar el merge.
