# Instrucciones para Claude Code - Centinela

## Entorno de Ejecución (Host Local + Docker Postgres)
- Comandos Artisan: `php artisan [comando]`
- Comandos Composer: `composer [comando]`
- Comandos NPM: `npm run dev` / `npm run build`
- Ejecución de Pruebas: `php artisan test`
- Base de datos: PostgreSQL 16 + PostGIS en Docker (`docker compose up -d`)

---

## Flujo Multiagente y Especificaciones Modulares (SDD)
Este repositorio opera bajo una metodología multiagente:
1. **Specs Modulares**: Las especificaciones funcionales se encuentran en `docs/specs/<modulo>/spec.md`.
2. **Spec Global**: La arquitectura y convenciones transversales residen en `docs/specs/global_spec.md`.
3. **Roles Operativos**:
   - **Planificación**: Define o actualiza la spec modular antes de escribir código.
   - **Implementación**: Sigue TDD estricto con `php artisan test` (tests primero, código después).
   - **Revisión**: Comprueba que la suite pase al 100% y valida el diff frente a los criterios Gherkin.

---

## Reglas Inviolables
- **Cero Auto-Merge**: Nunca fusiones ramas automáticamente a `develop`. Deja la Pull Request creada para revisión humana.
- **TDD Obligatorio**: Todo cambio de lógica debe contar con su correspondiente prueba en PHPUnit (`php artisan test`).
- **Iconografía**: En vistas y componentes UI usa únicamente SVGs vectoriales (Heroicons / Lucide) con clases CSS. No uses emojis de texto.
- **Protocolo MTS**: Respetar los 5 colores Manchester y la auditoría clínica inmutable (*Clinical Override*).
