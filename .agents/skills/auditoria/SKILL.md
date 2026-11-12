---
name: auditoria
description: Guía y flujo operativo paso a paso para auditar, revisar y validar tareas y Pull Requests creadas por otros desarrolladores del equipo o para auto-auditorías previas al merge. Usar ante "Modo Auditoría" o "/auditoria".
---

# 🕵️ Skill: Auditoría y Revisión de Código

Este skill guía a Antigravity en la ejecución paso a paso de la auditoría técnica de Pull Requests y tareas, garantizando calidad, apego a las specs modulares y estabilidad antes de cualquier integración.

---

## 🔄 Flujo Operativo Paso a Paso

### 1. Activación y Posicionamiento en Rama
* **Protocolo de Activación (`/auditoria`)**:
  - Si se invoca con argumentos (ej. `/auditoria CEN-12 Valeria local`), sincroniza de inmediato.
  - Si se invoca sin argumentos, utiliza `ask_question` para consultar:
    * Clave de tarea o rama a auditar.
    * Desarrollador asignado.
    * Modo de validación (Local vs. Remoto).
* **Checkout y Sincronización**:
  ```bash
  git fetch origin
  git checkout <rama>
  git pull origin <rama>
  ```
* **Consulta Quirúrgica del Backlog**:
  - Busca la tarea con `grep -n "CEN-xxx" docs/backlog.md` y lee únicamente el rango de líneas de esa subtarea (no leas `docs/backlog_archive.md` salvo auditoría histórica).

---

### 2. Ejecución Inmediata de Tests
* Ejecuta la suite de pruebas del proyecto: `docker compose exec app php artisan test`.
* 🚫 **GUARDIA BLOQUEANTE**: Si falla alguna prueba, la auditoría se detiene y emite inmediatamente `VEREDICTO: CAMBIOS NECESARIOS` detallando el fallo.

---

### 3. Inspección Profunda de Código (Git Diff)
* Inspecciona los cambios introducidos frente a `develop`:
  ```bash
  git diff origin/develop...HEAD
  ```
* **Criterios de Evaluación**:
  1. **Apego a la Spec Modular**: ¿Cumple los Requerimientos Funcionales y escenarios Gherkin de `docs/specs/<modulo>/spec.md`?
  2. **Calidad y Arquitectura**: Código limpio, tipado estricto, buenas prácticas Laravel y principios SOLID/DDD.
  3. **Seguridad y Rendimiento**: Autorización correcta con Policies/Gates, ausencia de queries N+1 en Eloquent y validación adecuada.
  4. **Iconografía**: Verificación estricta de ausencia de emojis de texto en vistas o componentes.

---

### 4. Smoke Test y Verificación Visual
* Si la PR introduce cambios visuales, navegar a la ruta con `/browser` o Playwright en `http://localhost`.
* Constatar que la consola JavaScript y los logs (`laravel.log`) permanezcan limpios.

---

### 5. Emisión del Veredicto Formal
Presenta el informe estructurado encabezado obligatoriamente por una de estas opciones:
* **`VEREDICTO: APROBADO`**: Si cumple al 100% con tests, especificación y estilo.
* **`VEREDICTO: CAMBIOS NECESARIOS`**: Si existen defectos o incumplimientos, desglosando:
  - `archivo:línea`: Motivo del defecto y solución requerida.

---

### 6. Fusión Asistida y Limpieza (Solo tras Veredicto Aprobado y OK Humano)
* 🚫 **PAUSA OBLIGATORIA**: El asistente NUNCA debe fusionar automáticamente sin confirmación.
* Tras el veredicto `APROBADO`, solicita el "OK" explícito del desarrollador.
* Si la tarea auditada es la última subtarea pendiente del **Sprint Activo**, verifica que el Sprint completado se migre a `docs/backlog_archive.md` y que el siguiente Sprint quede como `(ACTIVO)` al principio de `docs/backlog.md`.
* Con el "OK" confirmado:
  1. Si se usa GitHub CLI: `gh pr merge <id> --merge`.
  2. Si se usa GitHub MCP: `merge_pull_request`.
  3. Si es manual: Guiar en la web de GitHub para hacer clic en "Merge Pull Request".
* Limpieza ordenada de ramas:
  ```bash
  git checkout develop
  git pull origin develop
  git branch -d <rama>
  git fetch --prune
  ```
* Confirma al desarrollador que la PR fue fusionada y el árbol quedó sincronizado y limpio.
