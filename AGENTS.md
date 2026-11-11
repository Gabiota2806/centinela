<laravel-guidelines>
=== foundation rules ===

# Directivas Agénticas del Proyecto (Centinela)

## 1. Contexto de Ejecución y Dependencias
Esta aplicación es el Core Operativo de **Centinela** (Sistema de Triage Digital y Alerta Temprana Epidemiológica para CAPS de Formosa Capital).
- Corre en entorno Dockerizado (`centinela_app`) sobre **PHP 8.5.12**, **Composer 2.11.2**, **Node.js 26.13.0** y **Laravel 13.x** con usuario no-root (`1000:1000`).
- La base de datos es **PostgreSQL 18 + PostGIS 3.7** (`postgis/postgis:18-3.7`) corriendo en contenedor Docker (`centinela_postgis`).
- Conexión: servicio `postgres` (puerto `5432`), base de datos `centinela`, usuario `centinela`.
- Verifica dependencias:
  * PHP: `docker compose exec app composer show --direct`
  * Node/JS: Consulta `package.json`

## 2. Entorno de Comandos (Docker Compose: `app` + `postgres`)
- Levantar entorno completo: `docker compose up -d --build`
- Ejecución Artisan: `docker compose exec app php artisan [comando]`
- Ejecución Composer: `docker compose exec app composer [comando]`
- Ejecución de Pruebas: `docker compose exec app php artisan test`
- Ejecución de Migraciones: `docker compose exec app php artisan migrate`
- Entorno Frontend: `docker compose exec app npm run dev` / `docker compose exec app npm run build`

## 3. Arquitectura Multiagente y Entornos del Equipo
Este proyecto implementa el flujo de trabajo multiagente basado en especificaciones modulares adaptado a las herramientas de cada integrante:
- **Antonio Gabriel Pineda** (Full Stack / QA): Desarrolla con Google Antigravity.
- **Valeria Budiño** (Frontend / UX): Desarrolla con **Claude Code** (comandos nativos en `.claude/commands/`).
- **Enrique Maximiliano González** (Backend / Datos): Desarrolla con **Google Antigravity** o **OpenCode** (agentes en `.opencode/agents/`).

### Roles Operativos:
- **Coordinador**: Dirige el flujo sin escribir código.
- **Planificador**: Redacta specs modulares en `docs/specs/<modulo>/spec.md` y planes de tareas.
- **Implementador**: Ejecuta tareas mediante TDD estricto con `docker compose exec app php artisan test`.
- **Revisor (QA)**: Audita specs y valida la implementación RF por RF emitiendo veredictos (`APROBADO` / `CAMBIOS NECESARIOS`).

## 4. Especificaciones Técnicas Modulares (SDD)
- Toda funcionalidad pertenece a un módulo y se especifica en `docs/specs/<modulo>/spec.md` (ej. `triage`, `pacientes`, `consultas`, `telemetria`).
- Las decisiones y convenciones transversales residen en `docs/specs/global_spec.md`.
- No generes código sin contrastar antes contra la spec modular del componente afectado.

## 5. Convenciones de Código y Arquitectura
- Sigue la arquitectura del proyecto: Models, Enums de dominio para tipos cerrados (`app/Enums/`), Policies para autorización.
- Iconografía: Utiliza exclusivamente **SVGs vectoriales** (Heroicons / Lucide) estilizados con clases utilitarias (`w-4 h-4` o `w-5 h-5`). **PROHIBIDO** el uso de emojis o caracteres tipográficos en la interfaz de usuario.
- Protocolo Clínico: Sistema Manchester de Triage (MTS) de 5 niveles por colores (Rojo, Naranja, Amarillo, Verde, Azul).
- Geoespacial: Consultas espaciales con PostGIS y visualización Leaflet.js.

=== tests rules ===

# Cumplimiento de Pruebas (PHPUnit 12)
- Toda modificación de código debe respaldarse con pruebas unitarias o de integración.
- Ejecuta los tests con: `docker compose exec app php artisan test`.
- Escribe tests extendiendo `Tests\TestCase` con aserciones nativas de PHPUnit y helpers HTTP de Laravel.
- Garantiza el 100% en verde antes de dar por finalizada cualquier tarea.

=== livewire rules ===

# Livewire & Frontend Reactivo
- Mantén el estado en el servidor para que la interfaz lo refleje.
- Valida y autoriza en las acciones de los componentes.
- Utiliza Alpine.js para interacciones puramente del lado del cliente.

=== styling rules ===

# Tailwind CSS (v3)
- El proyecto utiliza Tailwind CSS v3 con configuración en `tailwind.config.js`.
- Respeta los colores Manchester estandarizados y tokens de diseño del proyecto.

=== git and pr rules ===

# Regla Estricta de Pull Requests y Control de Versiones
- **PROHIBIDO EL AUTO-MERGE**: Bajo ninguna circunstancia el agente debe fusionar automáticamente ramas hacia `develop` o `main`.
- La tarea culmina empujando la rama (`git push origin <rama>`) y creando la Pull Request (vía MCP de GitHub, `gh pr create` o enlace directo a GitHub compare) para revisión humana por parte de Gabriel o del equipo.
- Los commits deben seguir el estándar Conventional Commits en español referenciando únicamente la clave de la subtarea técnica (ej. `CEN-12`, `CEN-13`).

</laravel-guidelines>
