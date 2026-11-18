## Resumen

<!-- Regla Jira (bloqueante en CI): solo puede figurar la clave CEN-xxx de la subtarea de esta rama (o ninguna en ramas de mantenimiento/docs). NO incluyas otras claves CEN-xxx (épicas, HUs padre, dependencias ni rangos de Sprint). Referencia trabajo relacionado por nombre técnico/funcional o códigos locales (RF-xxx, T4.x). -->

## Cambios Realizados
-

## Pruebas y Verificación QA
- [ ] `docker compose exec app php artisan test` pasa al 100% en verde.
- [ ] Consola JS y `storage/logs/laravel.log` limpios (si aplica UI/Livewire).
- [ ] Sin emojis en vistas/componentes (solo SVGs vectoriales).
- [ ] Validado que rama, commits, título y descripción de esta PR no contienen claves `CEN-xxx` ajenas.
