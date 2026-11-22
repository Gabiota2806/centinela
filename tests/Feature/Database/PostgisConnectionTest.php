<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgisConnectionTest extends TestCase
{
    /**
     * Test that the application connects to PostgreSQL using the pgsql driver.
     */
    public function test_database_connection_uses_pgsql_driver(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $this->assertSame('pgsql', $driver, 'The database connection driver must be pgsql.');
    }

    /**
     * Test that PostgreSQL version is 18.x.
     */
    public function test_postgresql_version_is_18(): void
    {
        $version = DB::selectOne('SELECT version() as ver')->ver;

        $this->assertStringContainsString('PostgreSQL 18', $version, 'The database must run on PostgreSQL 18.');
    }

    /**
     * Test that the PostGIS extension is installed and returns version 3.7.x.
     */
    public function test_postgis_extension_is_installed_and_version_3_7(): void
    {
        $postgisVersion = DB::selectOne('SELECT PostGIS_Version() as ver')->ver;

        $this->assertNotEmpty($postgisVersion, 'PostGIS version must not be empty.');
        $this->assertStringStartsWith('3.7', $postgisVersion, 'PostGIS version must be 3.7.x.');
    }

    /**
     * Test that PostGIS spatial functions operate correctly with SRID 4326.
     */
    public function test_postgis_spatial_geometry_functions_work(): void
    {
        // Coordinates for Formosa Capital (-58.1739, -26.1849)
        $point = DB::selectOne('SELECT ST_AsText(ST_SetSRID(ST_MakePoint(-58.1739, -26.1849), 4326)) as wkt')->wkt;

        $this->assertSame('POINT(-58.1739 -26.1849)', $point);
    }

    /**
     * Test that the centinela schema exists in the database.
     */
    public function test_centinela_schema_exists(): void
    {
        $schema = DB::selectOne("SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'centinela'");

        $this->assertNotNull($schema, "Schema 'centinela' must exist in the database.");
    }

    /**
     * Test that the default search_path includes the centinela schema.
     */
    public function test_search_path_includes_centinela_schema(): void
    {
        $searchPath = DB::selectOne('SHOW search_path')->search_path;

        $this->assertStringContainsString('centinela', $searchPath, 'The search_path must include the centinela schema.');
    }
}
