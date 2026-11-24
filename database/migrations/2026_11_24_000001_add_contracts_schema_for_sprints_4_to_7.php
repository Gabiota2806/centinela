<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema base compartido de contratos para Cola Médica, Clima, Telemetría Outbox y Riesgo Barrial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triages', function (Blueprint $table) {
            $table->index(['status', 'final_priority', 'created_at'], 'idx_triages_active_queue');
        });

        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->decimal('risk_score', 5, 4)->nullable()->after('risk_level');
            $table->timestamp('risk_updated_at')->nullable()->after('risk_score');
        });

        Schema::create('weather_records', function (Blueprint $table) {
            $table->id();
            $table->date('recorded_date')->unique();
            $table->decimal('latitude', 7, 4)->default(-26.1800);
            $table->decimal('longitude', 7, 4)->default(-58.1700);
            $table->decimal('temperature_max', 5, 2)->nullable();
            $table->decimal('temperature_min', 5, 2)->nullable();
            $table->decimal('precipitation_sum', 6, 2)->default(0);
            $table->decimal('relative_humidity_mean', 5, 2)->nullable();
            $table->string('source', 50)->default('open-meteo');
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('epidemiology_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('rain_threshold_mm', 6, 2)->default(40.00);
            $table->unsignedSmallInteger('time_lag_days')->default(10);
            $table->unsignedSmallInteger('febrile_alert_threshold')->default(5);
            $table->decimal('high_risk_probability', 4, 2)->default(0.70);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('outbox_telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->jsonb('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_outbox_pending_queue');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_telemetry_events');
        Schema::dropIfExists('epidemiology_settings');
        Schema::dropIfExists('weather_records');

        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->dropColumn(['risk_score', 'risk_updated_at']);
        });

        Schema::table('triages', function (Blueprint $table) {
            $table->dropIndex('idx_triages_active_queue');
        });
    }
};
