---
name: regla-desarrollo-tareas
description: Regla de cumplimiento obligatorio para el ciclo de vida de desarrollo de tareas técnicas con Jira Cloud en Centinela.
trigger: always_on
---

# 🛡️ Regla de Cumplimiento: Metodología de Desarrollo de Tareas (Gabriel & Antigravity)

Esta regla rige de forma permanente en todas las sesiones y conversaciones en Centinela:

1. **ACTIVACIÓN HÍBRIDA, DETECCIÓN DE MODO Y CREACIÓN DE RAMA**:
   - **Activación Híbrida (Slash Command `/desarrollo-tarea`)**:
     * **Invocación Inline**: Si Gabriel escribe `/desarrollo-tarea <CLAVE> <modo>` (ej. `/desarrollo-tarea CEN-12 local`), Antigravity extrae directamente los parámetros y avanza de inmediato a consultar Jira sin preguntas redundantes.
     * **Invocación Vacía / Asistida**: Si Gabriel invoca `/desarrollo-tarea` sin argumentos, Antigravity **DEBE activar de inmediato la herramienta interactiva `ask_question`** presentando el modal (Clave de Jira y selector de Modo Local vs. Remoto).
   - NUNCA escribas código, modifiques archivos ni crees ramas sin antes presentar un Plan Técnico detallado en el chat.
   - **Consulta de la Tarea (Local First en docs/backlog.md)**:
     * Antigravity DEBE consultar primero el archivo `docs/backlog.md` en el repositorio para extraer el detalle técnico exhaustivo de la tarea (`CEN-xxx`), responsable, archivos objetivo, criterios Gherkin y resultados esperados.
     * Si el entorno cuenta con credenciales en `.env.jira` o servidor MCP de Jira activo, puede consultar opcionalmente Jira Cloud; en caso contrario, `docs/backlog.md` es la fuente de verdad definitiva y suficiente.
     * Estructura el nombre de rama estandarizado (ej. `CEN-xxx-nombre-tarea`).
   - **Consulta de Especificación Modular SDD**:
     * Antigravity DEBE consultar la especificación del módulo correspondiente en `docs/specs/<modulo>/spec.md` y las directivas generales en `docs/specs/global_spec.md` para extraer los requerimientos funcionales (RF) y criterios de aceptación (Gherkin).
   - **Inspección de Estado Vivo**:
     * Inspeccionar migraciones en `database/migrations/`, modelos en `app/Models/` y el contenedor Docker de PostgreSQL/PostGIS.
   - DEBES pausar y esperar el "OK" explícito de Gabriel sobre el plan antes de tocar código.
   - Tras el "OK" de Gabriel, sincroniza `develop`, crea la rama en Git y súbela a GitHub para vincular con Jira.

2. **REGLA ESTRICTA DE JIRA EN COMMITS Y PULL REQUESTS**:
   - Antigravity **NO debe modificar el estado de las tarjetas en Jira Cloud mediante transitions API** a menos que el usuario lo solicite. Las transiciones las manejan las automatizaciones de Jira.
   - NUNCA incluyas la clave de la Historia de Usuario (`HU-xx` / clave padre) en mensajes de commit ni títulos de PR para evitar cierres prematuros de la historia en Jira.
   - SOLO debes referenciar la clave de la Subtarea específica (`CEN-xxx`).

3. **TDD, SMOKE TESTING Y PRE-VERIFICACIÓN TÉCNICA EN NAVEGADOR**:
   - **TDD Estricto**: Escribe o actualiza las pruebas unitarias y de integración cubriendo los criterios Gherkin en PHPUnit antes o en paralelo al código de producción.
   - Ejecuta la suite de pruebas mediante: `docker compose exec app php artisan test`. Garantiza el 100% en verde.
   - **Estándar de Iconografía**: Queda **PROHIBIDO** usar emojis de texto o caracteres tipográficos sustitutos. Utilizar exclusivamente **SVGs vectoriales** con clases utilitarias (`w-4 h-4` o `w-5 h-5`).
   - **Pre-Verificación Técnica en Navegador**:
     * Si la tarea afecta interfaz gráfica o Livewire, navegar a la pantalla afectada con `/browser` o Playwright en `http://localhost:8000` (o `http://localhost`).
     * Constatar que renderice sin excepciones HTTP 500 ni caídas de componentes Livewire.
     * Validar que la consola JavaScript y los logs de Laravel (`laravel.log`) permanezcan completamente limpios.
   - 🚫 **GUARDIA ESTRICTA DE DOBLE VERIFICACIÓN**: La pre-verificación técnica del asistente **NUNCA** sustituye la prueba manual de Gabriel. La pausa para validación humana es obligatoria e inviolable.
   - **Entrega de la Guía de QA Manual**:
     * **En Modo Local**: Apuntar directamente a `http://localhost`.
     * Nota: Entorno sin túnel remoto configurado. Todas las pruebas se realizan directamente en `http://localhost`.
   - 🚫 **PAUSA OBLIGATORIA**: Esperar el "OK" explícito de Gabriel confirmando sus pruebas en el navegador.
   - 🔄 **CICLO ITERATIVO ANTE OBSERVACIONES**: Si Gabriel plantea observaciones, Antigravity DEBE presentar un Plan Técnico de Ajuste, esperar el "OK", implementar, re-verificar y presentar la guía de re-validación.

4. **REGISTRO EN DOCUMENTACIÓN Y COMMIT (SOLO TRAS EL OK DE QA)**:
   - Marca la tarea técnica como completada `[x]` en `docs/specs/<modulo>/spec.md`.
   - Registra la entrada en `docs/changelog.md` bajo `## [Unreleased]`.
   - Realiza el commit en español con Conventional Commits (`feat: ...`, `fix: ...`, `test: ...`) referenciando únicamente la clave de la subtarea técnica (ej. `CEN-12`).
   - Sube la rama a GitHub (`git push origin <rama>`).

5. **CREACIÓN DE PULL REQUEST (PROHIBIDO AUTO-MERGE)**:
   - 🚫 **PROHIBICIÓN ESTRICTA DE AUTO-MERGE**: Antigravity **NUNCA** ejecutará `gh pr merge`, ni mergeará la rama local o remotamente a `develop`. La PR DEBE quedar abierta para revisión de código por el equipo (Valeria, Enrique, Gabriel).
   - **Protocolo de Creación de PR**:
     1. Si el servidor MCP de GitHub está autenticado y activo, invocar `create_pull_request` apuntando a `develop`.
     2. Si el CLI `gh` está disponible y autenticado en el sistema, ejecutar:
        ```bash
        gh pr create --base develop --head <rama> --title "feat(CEN-xxx): [Título]" --body "[Cuerpo Markdown con tareas cubiertas y checklist de QA]"
        ```
     3. Si ni el MCP ni el CLI de GitHub están autenticados:
        * Generar y mostrar en el chat la URL directa de comparación de GitHub:
          `https://github.com/Gabiota2806/centinela/compare/develop...<rama>?expand=1`
        * Imprimir un bloque Markdown preformateado con el Título y Descripción listos para copiar y pegar en la interfaz web de GitHub.
   - Informar a Gabriel que la rama fue empujada exitosamente y la Pull Request quedó abierta a la espera de la auditoría y revisión del equipo.
