<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CEN-14: Barrios de Formosa Capital con delimitación poligonal PostGIS y nivel de riesgo epidemiológico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->geometry('geometry', subtype: 'geometry', srid: 4326);
            $table->string('risk_level')->default('normal');
            $table->timestamps();

            $table->spatialIndex('geometry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
    }
};
