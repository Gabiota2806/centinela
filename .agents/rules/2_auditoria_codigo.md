---
name: regla-auditoria-codigo
description: Regla de cumplimiento obligatorio para la auditoría y revisión rigurosa de código y Pull Requests de compañeros (Valeria, Enrique) o auto-auditorías previas a la fusión en Centinela.
trigger: always_on
---

# 🕵️ Regla de Cumplimiento: Metodología de Auditoría y Control de Calidad de Código

Esta regla rige cada vez que se invoque el Slash Command `/auditoria` o se soliciten revisiones de código de compañeros (Valeria, Enrique) o auto-auditorías previas a la fusión:

1. **POSICIONAMIENTO EN LA RAMA Y SINCRONIZACIÓN**:
   - Al recibir la clave de la tarea o rama a auditar, Antigravity debe sincronizar y hacer checkout a la rama correspondiente:
     ```bash
     git fetch origin
     git checkout <rama>
     git pull origin <rama>
     ```
   - Antigravity DEBE identificar al desarrollador asignado (Valeria, Enrique, Gabriel) y el propósito de la tarea en Centinela.
   - Para verificar el alcance de la tarea en `docs/backlog.md`, localiza la línea con `grep -n "CEN-xxx" docs/backlog.md` y lee únicamente ese bloque (consulta `docs/backlog_archive.md` solo si se trata de una auditoría histórica).

2. **AUDITORÍA DE TESTS Y COBERTURA (PHPUnit)**:
   - Ejecutar de inmediato la suite de pruebas del proyecto: `docker compose exec app php artisan test`.
   - 🚫 **BLOQUEO INMEDIATO**: Si algún test falla, la auditoría se detiene. Se emite de inmediato el veredicto `VEREDICTO: CAMBIOS NECESARIOS` detallando los fallos detectados.

3. **INSPECCIÓN PROFUNDA DE CÓDIGO (GIT DIFF)**:
   - Comparar los cambios frente a `develop`:
     ```bash
     git diff origin/develop...HEAD
     ```
   - **Criterios de Inspección**:
     1. **Concordancia con la Spec Modular**: ¿Cumple los RF y criterios Gherkin estipulados en `docs/specs/<modulo>/spec.md`?
     2. **Convenciones de Código y Arquitectura**: Tipado estricto, métodos declarativos, respeto a SOLID y principios DDD/MVC.
     3. **Protocolo MTS**: Respeto a los 5 colores Manchester y registros de auditoría clínica (*Clinical Override*).
     4. **Seguridad y Rendimiento**: Prevención de consultas N+1 en Eloquent, autorización en Policies/Gates, validación estricta de inputs.
     5. **Estándar de Iconografía**: Prohibición absoluta de emojis en vistas Blade o componentes Livewire. Uso exclusivo de SVGs vectoriales.

4. **SMOKE TESTING Y VERIFICACIÓN VISUAL**:
   - Si la tarea incluye componentes de interfaz o vistas Livewire, navegar a la pantalla en `http://localhost:8000` con `/browser` o Playwright.
   - Constatar que la consola JavaScript y los logs (`laravel.log`) permanezcan completamente limpios.

5. **EMISIÓN DEL VEREDICTO FORMAL**:
   Antigravity debe presentar en el chat el informe estructurado encabezado obligatoriamente por una de estas dos líneas:
   - **`VEREDICTO: APROBADO`**: Si la suite pasó al 100%, el diff está limpio y cumple con todos los estándares.
   - **`VEREDICTO: CAMBIOS NECESARIOS`**: Si existen defectos, tests rotos o incumplimiento de convenciones, listando de forma numerada:
     * `archivo:línea`: Qué se incumple y qué corrección exacta se requiere.

6. **FUSIÓN ASISTIDA CON CONFIRMACIÓN HUMANA (DOBLE VERIFICACIÓN)**:
   - 🚫 **GUARDIA BLOQUEANTE**: Aunque el veredicto sea `APROBADO`, Antigravity **NUNCA debe fusionar la Pull Request automáticamente**.
   - Antigravity debe solicitar la confirmación y "OK" explícito del desarrollador.
   - Si con esta tarea quedan el 100% de las subtareas del **Sprint Activo** en `[x] Listo` y aún no se rotó el Sprint, verifica o ejecuta la migración del Sprint finalizado hacia `docs/backlog_archive.md` dejando el nuevo Sprint como `(ACTIVO)` al inicio de `docs/backlog.md`.
   - Tras el "OK", procede a asistir en la fusión hacia `develop` (vía GitHub MCP `merge_pull_request`, CLI `gh pr merge`, o guiando en la web de GitHub) y realiza la limpieza ordenada de ramas locales y remotas.
