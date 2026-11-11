<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## 🚀 Guía de Inicio Rápido (Entorno Contenerizado con Docker)

El entorno de desarrollo de **Centinela** está completamente contenerizado para garantizar paridad exacta de versiones entre todos los integrantes del equipo sin requerir instalaciones nativas en el host:
- **PHP:** `8.5.12` (`pdo_pgsql`, `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `intl`, `bcmath`, `opcache`)
- **Composer:** `2.11.2`
- **Node.js:** `v26.13.0` + `npm`
- **Base de Datos Espacial:** `postgis/postgis:18-3.7` (**PostgreSQL 18 + PostGIS 3.7**)
- **Usuario no-root:** Mapeado a `UID:GID` (`1000:1000`) del host.

### 1. Prerrequisitos
Asegúrate de contar únicamente con:
- **Docker** y **Docker Compose v2+**

### 2. Variables de Entorno
Copiar la plantilla de configuración antes de levantar los contenedores:
```bash
cp .env.example .env
```

Verifica que la sección de base de datos en `.env` coincida con:
```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=centinela
DB_USERNAME=centinela
DB_PASSWORD=centinela_secret
DB_SCHEMA=centinela,public
```

### 3. Construir y Levantar los Contenedores (`app` y `postgres`)
Construir la imagen de aplicación e iniciar los servicios en segundo plano:
```bash
docker compose up -d --build
```

### 4. Instalación de Dependencias y Clave de Aplicación
Ejecutar Composer, NPM y generar la `APP_KEY` dentro del contenedor `app`:
```bash
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app php artisan key:generate
```

### 5. Ejecutar Migraciones
El esquema `centinela` y la extensión espacial `postgis` se configuran automáticamente:
```bash
docker compose exec app php artisan migrate
```

### 6. Servidores en Segundo Plano, Compilación y Pruebas
Al levantar el entorno con `docker compose up -d`, el contenedor `app` inicia **automáticamente en segundo plano**:
- **Backend Laravel:** `http://localhost:8000`
- **Frontend Vite (HMR):** `http://localhost:5173`

Si deseas generar el build estático de producción manualmente:
```bash
docker compose exec app npm run build
```

Para ejecutar la suite de pruebas (PHPUnit 12):
```bash
docker compose exec app php artisan test
```

### 7. Apagar el Entorno
Para detener y dar de baja todos los contenedores y procesos en segundo plano (`artisan serve`, `vite` y `postgres`), liberando los puertos `8000`, `5173` y `5432` para trabajar en otros proyectos:
```bash
docker compose down
```

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
