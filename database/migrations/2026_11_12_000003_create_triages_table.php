<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CEN-14: Evaluaciones clínicas de Triage bajo el Sistema Manchester de Triage (MTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('symptoms');
            $table->jsonb('vital_signs');
            $table->string('algorithmic_priority', 20);
            $table->string('final_priority', 20);
            $table->boolean('is_override')->default(false);
            $table->integer('target_wait_minutes')->default(120);
            $table->string('status', 30)->default('pending_consultation');
            $table->timestamps();

            $table->index('status');
            $table->index('final_priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triages');
    }
};
