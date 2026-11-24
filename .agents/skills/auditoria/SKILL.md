---
name: auditoria
description: Guía operativa para ejecutar auto-auditoría previa a la PR o revisión de una Pull Request de HU/sub-vertical validando contratos, specs, seguridad, Ley 25.326, tests y claves Jira.
---

# 🕵️ Skill: Auditoría y Revisión de Código

Este skill guía a Antigravity en la **auto-auditoría del autor antes de abrir PR** y en revisiones de Pull Requests hacia `develop`.

---

## 🔄 Flujo Operativo Paso a Paso

### 1. Contexto y Alcance de la Rama
* Si se audita la rama local actual (auto-auditoría), continuar sobre `HEAD`. Si se audita una rama remota de otro integrante:
  ```bash
  git fetch origin
  git checkout <rama>
  git pull origin <rama>
  ```
* Determinar el alcance de claves en `.github/jira-scopes.conf` y leer únicamente esas subtareas en `docs/backlog.md` (`grep -n`).
* Leer `docs/specs/00_contracts.md`, `docs/specs/global_spec.md` y la spec modular afectada.

### 2. Verificación Técnica Automática
* Ejecutar:
  - `docker compose exec app ./vendor/bin/pint --test`
  - `docker compose exec app php artisan test`
  - *(Si la rama modifica `backend_analytics/`, ejecutar su suite de tests Python).*
* 🚫 **BLOQUEO INMEDIATO**: Si falla cualquier check, emitir `VEREDICTO: CAMBIOS NECESARIOS`.

### 3. Auditoría Semántica del Diff (`git diff origin/develop...HEAD`)
Revisar los aspectos que el CI no puede evaluar:
1. **Contratos y Spec Modular:** Cumplimiento RF por RF de `00_contracts.md` y `docs/specs/<modulo>/spec.md`.
2. **RBAC, Seguridad y Ley N° 25.326:** Autorización con Gates/Policies, prevención de N+1, validación estricta y cero filtración de DNI/nombre/teléfono/domicilio en telemetría o logs.
3. **Protocolo MTS:** 5 colores Manchester e inmutabilidad en `triage_audit_logs`.
4. **Iconografía:** Cero emojis en Blade/Livewire (solo SVGs vectoriales).
5. **Calidad de Tests:** Validar que cubran casos reales y bordes (403/422/offline), no aserciones vacías.
6. **Anti-conflictos:** Confirmar que no se modificaron `docs/backlog.md` ni `docs/changelog.md` en ramas de features/HUs.
7. **Integridad de Claves Jira (Bloqueante):** Confirmar que la rama tiene máximo 1 clave `CEN-xxx` y que todos los commits propios (`git log origin/develop..HEAD --no-merges --format=%B`), título y descripción de PR solo incluyen claves autorizadas en `.github/jira-scopes.conf` (o ninguna en mantenimiento/docs).

### 4. Smoke Test Visual
* Si hay UI/Livewire, comprobar en `http://localhost:8000` ausencia de HTTP 500, errores de consola JS y trazas en `storage/logs/laravel.log`.

### 5. Emisión del Veredicto
Encabezar el informe obligatoriamente con:
* **`VEREDICTO: APROBADO`** (incluir resumen compacto RF por RF listo para pegar en la PR).
* **`VEREDICTO: CAMBIOS NECESARIOS`** (lista numerada `archivo:línea` con defecto y corrección).

### 6. Cierre sin Auto-Merge
* 🚫 Nunca fusionar automáticamente.
* En auto-auditoría: devolver el veredicto para publicarlo en la PR.
* En revisión de PR de otro integrante: tras `APROBADO`, esperar el "OK" explícito humano antes de asistir en el merge (`gh pr merge` / web) y limpiar la rama local.
