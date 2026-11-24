## Resumen

<!-- Regla Jira (bloqueante en CI): solo pueden figurar la clave de la rama y sus subtareas habilitadas en .github/jira-scopes.conf (o ninguna en ramas de mantenimiento/docs). NO incluyas claves CEN-xxx de épicas, otras HUs ni tarjetas de otros compañeros. Referencia trabajo fuera del alcance por nombre técnico/funcional o códigos locales (RF-xxx, T4.x). -->

## Cambios Realizados
-

## Pruebas y Verificación QA
- [ ] `docker compose exec app php artisan test` pasa al 100% en verde.
- [ ] Auto-auditoría ejecutada con agente (`/auditoria` o `@reviewer`) con `VEREDICTO: APROBADO` pegado abajo.
- [ ] Guía de QA Manual validada en navegador (`http://localhost:8000`) con consola JS y `storage/logs/laravel.log` limpios (si aplica UI/Livewire).
- [ ] Sin emojis en vistas/componentes (solo SVGs vectoriales).
- [ ] Validado que rama, commits, título y descripción solo contienen claves autorizadas en `.github/jira-scopes.conf`.
- [ ] Sin ediciones en `docs/backlog.md` ni `docs/changelog.md` (se consolidan directo en `develop`).

## Resumen de Auto-Auditoría (`/auditoria`)
-
