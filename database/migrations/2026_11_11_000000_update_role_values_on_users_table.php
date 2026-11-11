<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CEN-15: alinea la columna users.role con el Enum UserRole del backlog.
 */
return new class extends Migration
{
    /**
     * Valores previos → valores del backlog.
     *
     * @var array<string, string>
     */
    private array $map = [
        'admision' => 'admission',
        'triage' => 'triage_nurse',
        'medico' => 'doctor',
        'ministerio' => 'director',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admission')->change();
        });

        foreach ($this->map as $old => $new) {
            DB::table('users')->where('role', $old)->update(['role' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('users')->where('role', $new)->update(['role' => $old]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admision')->change();
        });
    }
};
