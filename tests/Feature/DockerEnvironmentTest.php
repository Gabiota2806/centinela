<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DockerEnvironmentTest extends TestCase
{
    public function test_dockerfile_defines_required_runtimes_and_non_root_user(): void
    {
        $dockerfilePath = base_path('Dockerfile');

        $this->assertFileExists($dockerfilePath);

        $content = (string) file_get_contents($dockerfilePath);

        $this->assertStringContainsString('php:8.5.12-cli', $content);
        $this->assertStringContainsString('node:26.13.0-slim', $content);
        $this->assertStringContainsString('composer:2.11.2', $content);
        $this->assertStringContainsString('ARG UID=1000', $content);
        $this->assertStringContainsString('ARG GID=1000', $content);
        $this->assertStringContainsString('USER ${UID}:${GID}', $content);
        $this->assertStringContainsString('npm run dev', $content);
        $this->assertStringContainsString('php artisan serve', $content);
    }

    public function test_docker_compose_defines_app_and_postgis_18_services(): void
    {
        $composePath = base_path('docker-compose.yml');

        $this->assertFileExists($composePath);

        $content = (string) file_get_contents($composePath);

        $this->assertStringContainsString('app:', $content);
        $this->assertStringContainsString('container_name: centinela_app', $content);
        $this->assertStringContainsString('postgres:', $content);
        $this->assertStringContainsString('image: postgis/postgis:18-3.7', $content);
        $this->assertStringContainsString('container_name: centinela_postgis', $content);
    }

    public function test_required_php_extensions_are_loaded(): void
    {
        $requiredExtensions = [
            'pdo_pgsql',
            'pgsql',
            'mbstring',
            'xml',
            'curl',
            'zip',
            'intl',
            'bcmath',
            'Zend OPcache',
        ];

        foreach ($requiredExtensions as $extension) {
            $this->assertTrue(
                extension_loaded($extension),
                "La extensión PHP requerida '{$extension}' no está cargada."
            );
        }
    }

    public function test_agent_directives_and_readme_document_containerized_execution(): void
    {
        $readme = (string) file_get_contents(base_path('README.md'));
        $agents = (string) file_get_contents(base_path('AGENTS.md'));

        $this->assertStringContainsString('docker compose up -d --build', $readme);
        $this->assertStringContainsString('docker compose exec app', $readme);
        $this->assertStringContainsString('docker compose exec app php artisan test', $agents);
    }

    public function test_database_runs_postgresql_18_with_postgis_3_7(): void
    {
        $pgVersion = (string) DB::selectOne('SELECT version() as ver')->ver;
        $postgisVersion = (string) DB::selectOne('SELECT PostGIS_Version() as ver')->ver;

        $this->assertStringContainsString('PostgreSQL 18', $pgVersion);
        $this->assertStringStartsWith('3.7', $postgisVersion);
    }
}
