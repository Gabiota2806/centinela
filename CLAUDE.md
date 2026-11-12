# Instrucciones para Claude Code - Centinela

## Entorno de Ejecución Contenerizado (`docker compose exec app ...`)
- Levantar servicios (`app` y `postgres`): `docker compose up -d --build`
- Comandos Artisan: `docker compose exec app php artisan [comando]`
- Comandos Composer: `docker compose exec app composer [comando]`
- Comandos NPM: `docker compose exec app npm run dev` / `docker compose exec app npm run build`
- Ejecución de Pruebas: `docker compose exec app php artisan test`
- Stack en Contenedores: PHP 8.5.12, Composer 2.11.2, Node.js 26.13.0 y PostgreSQL 18 + PostGIS 3.7 (`postgis/postgis:18-3.7`)

---

## Flujo Multiagente y Especificaciones Modulares (SDD)
Este repositorio opera bajo una metodología multiagente:
1. **Specs Modulares**: Las especificaciones funcionales se encuentran en `docs/specs/<modulo>/spec.md`.
2. **Spec Global**: La arquitectura y convenciones transversales residen en `docs/specs/global_spec.md`.
3. **Backlog Optimizado (`docs/backlog.md` y `docs/backlog_archive.md`)**:
   - `docs/backlog.md` ubica siempre primero el **Sprint Activo**. Para ahorrar tokens, busca la clave con `grep -n "CEN-xxx" docs/backlog.md` y lee únicamente el bloque de esa subtarea.
   - `docs/backlog_archive.md` almacena los Sprints finalizados (`[x] Listo`). No lo leas salvo consulta histórica explícita.
   - Al cerrar o auditar la última subtarea `[x]` del Sprint Activo, mueve ese Sprint a `docs/backlog_archive.md` y promueve el siguiente Sprint como `(ACTIVO)` al tope de `docs/backlog.md`.
4. **Roles Operativos**:
   - **Planificación**: Define o actualiza la spec modular antes de escribir código.
   - **Implementación**: Sigue TDD estricto con `docker compose exec app php artisan test` (tests primero, código después).
   - **Revisión**: Comprueba que la suite pase al 100% y valida el diff frente a los criterios Gherkin.

---

## Reglas Inviolables
- **Cero Auto-Merge**: Nunca fusiones ramas automáticamente a `develop`. Deja la Pull Request creada para revisión humana.
- **TDD Obligatorio**: Todo cambio de lógica debe contar con su correspondiente prueba en PHPUnit (`php artisan test`).
- **Iconografía**: En vistas y componentes UI usa únicamente SVGs vectoriales (Heroicons / Lucide) con clases CSS. No uses emojis de texto.
- **Protocolo MTS**: Respetar los 5 colores Manchester y la auditoría clínica inmutable (*Clinical Override*).
