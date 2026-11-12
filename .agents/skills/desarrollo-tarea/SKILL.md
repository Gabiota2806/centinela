---
name: desarrollo-tarea
description: Guía y flujo operativo paso a paso para el desarrollo e implementación de subtareas técnicas en Laravel. Usar al iniciar o trabajar en cualquier tarea (TASK-X / CEN-X).
---

# 🚀 Skill: Desarrollo e Implementación de Tareas

Este skill guía a Antigravity en la ejecución paso a paso del ciclo de vida de desarrollo de cualquier tarea técnica junto al desarrollador.

---

## 🔄 Flujo Operativo Paso a Paso

### 1. Activación Híbrida, Planificación y Creación de Rama
* **Protocolo de Activación Híbrida (`/desarrollo-tarea`)**:
  - **Invocación Inline**: Si se envía la clave y modo directamente (ej. `/desarrollo-tarea CEN-101 local`), extrae los parámetros y avanza de inmediato a consultar el contexto de la tarea.
  - **Invocación Asistida**: Si se envía solo `/desarrollo-tarea`, activa `ask_question` para consultar:
    * Clave de la tarea o ticket asignado.
    * Modo de trabajo (`(Recommended) Modo Local (en la PC)` | `Modo Remoto (desde el celular con túnel Cloudflare)`).
* **Consulta de la Tarea y Alcance (docs/backlog.md)**:
  - El **Sprint Activo** está siempre al principio de `docs/backlog.md`. Para ahorrar tokens, localiza la subtarea con `grep -n "CEN-xxx" docs/backlog.md` y lee solo ese rango de líneas. No leas `docs/backlog_archive.md` salvo consulta histórica explícita.
  - Si existen credenciales de Jira Cloud (`.env.jira`), sincroniza opcionalmente con Jira; de lo contrario, opera de forma 100% autónoma con el backlog local.
* **Consulta de Especificación Modular SDD**:
  - Antigravity DEBE consultar la especificación modular correspondiente en `docs/specs/<modulo>/spec.md` y `docs/specs/global_spec.md` para extraer los Requerimientos Funcionales (RF) y Criterios de Aceptación (Gherkin).
* **Inspección de Estado Vivo**:
  - Inspeccionar el esquema de base de datos viva (vía Laravel Boost MCP si está instalado, o leyendo migraciones en `database/migrations/` y modelos en `app/Models/`).
* **Plan Técnico Estructurado**:
  1. Modo de Trabajo (Local o Remoto).
  2. Nombre de rama estandarizado (ej. `CEN-xxx-nombre-tarea`).
  3. Clases, modelos, enums, migraciones o vistas a crear o modificar.
  4. Estrategia de pruebas TDD.
* 🚫 **PAUSA**: Espera el "OK" explícito del desarrollador antes de codificar o crear ramas.
* **Creación y Push de Rama**:
  Tras recibir el "OK", crea la rama desde `develop` y la sube a GitHub.

---

### 2. Desarrollo, TDD y QA Manual
* Implementa la lógica siguiendo la spec modular y las convenciones del repositorio (`AGENTS.md`).
* **TDD Estricto**: Escribe o actualiza las pruebas unitarias y de integración primero.
* **Ejecución de Pruebas**: Ejecuta la suite mediante `docker compose exec app php artisan test`. El 100% debe pasar en verde.
* **Iconografía**: Utiliza exclusivamente SVGs vectoriales (Heroicons / Lucide) con clases utilitarias. PROHIBIDO el uso de emojis en la interfaz.
* **Pre-Verificación Técnica en Navegador**:
  - Si hay cambios visuales, navegar con `/browser` o Playwright a `http://localhost/...`.
  - Validar que no haya excepciones HTTP 500 ni caídas de componentes reactivos.
  - Verificar que la consola JavaScript y los logs de Laravel (`laravel.log`) permanezcan completamente limpios.
* **Guía de QA Manual**:
  - Presentar la guía paso a paso para validación humana (Local en `localhost` o Remoto con túnel Cloudflare si está configurado).
  - 🚫 **PAUSA OBLIGATORIA**: Esperar el "OK" explícito del desarrollador confirmando sus pruebas en el navegador.

---

### 3. Registro en Documentación y Commit
Tras el "OK" humano de QA:
* Marca la subtarea como completada `[x]` en `docs/specs/<modulo>/spec.md` y en `docs/backlog.md`.
* **Rotación de Sprint**: Si con esta subtarea se completa el 100% del **Sprint Activo**, mueve el Sprint finalizado a `docs/backlog_archive.md` y promueve el siguiente Sprint como `(ACTIVO)` al inicio de `docs/backlog.md`.
* Registra la entrada en `docs/changelog.md` bajo `## [Unreleased]`.
* Realiza el commit en español con Conventional Commits referenciando únicamente la clave de la subtarea técnica.
* Sube la rama a GitHub (`git push origin <rama>`).

---

### 4. Creación de Pull Request (Sin Auto-Merge)
* 🚫 **PROHIBIDO EL AUTO-MERGE**: La Pull Request NO debe fusionarse automáticamente; debe quedar abierta para revisión.
* **Creación Asistida**:
  1. Si GitHub MCP está disponible: Invocar `create_pull_request` apuntando a `develop`.
  2. Si CLI `gh` está autenticado: Ejecutar `gh pr create`.
  3. Si no hay herramientas programáticas activas:
     - Imprimir la URL directa de comparación: `https://github.com/Gabiota2806/centinela/compare/develop...<rama>?expand=1`.
     - Entregar el bloque Markdown con el Título y Descripción listos para copiar y pegar en la web de GitHub.
* Confirma al desarrollador que la rama y la PR quedaron publicadas a la espera de auditoría.
