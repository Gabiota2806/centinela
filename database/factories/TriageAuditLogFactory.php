<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TriagePriority;
use App\Models\Triage;
use App\Models\TriageAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TriageAuditLog>
 */
class TriageAuditLogFactory extends Factory
{
    protected $model = TriageAuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'triage_id' => Triage::factory(),
            'user_id' => User::factory()->doctor(),
            'original_priority' => TriagePriority::GREEN,
            'overridden_priority' => TriagePriority::ORANGE,
            'override_reason' => 'Paciente presenta signos de dificultad respiratoria progresiva no capturados inicialmente.',
        ];
    }
}
