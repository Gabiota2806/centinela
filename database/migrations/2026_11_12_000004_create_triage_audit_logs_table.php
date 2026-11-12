<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CEN-14: Bitácora inmutable de auditoría para decisiones clínicas de Clinical Override (MTS).
 * Sin updated_at para garantizar inmutabilidad médico-legal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triage_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triage_id')->constrained('triages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('original_priority', 20);
            $table->string('overridden_priority', 20);
            $table->text('override_reason');
            $table->timestamp('created_at')->useCurrent();

            $table->index('triage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_audit_logs');
    }
};
