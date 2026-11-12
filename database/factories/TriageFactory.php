<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use App\Models\Patient;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Triage>
 */
class TriageFactory extends Factory
{
    protected $model = Triage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $priority = fake()->randomElement(TriagePriority::cases());

        return [
            'patient_id' => Patient::factory(),
            'user_id' => User::factory()->triageNurse(),
            'symptoms' => fake()->sentence(8),
            'vital_signs' => [
                'temperature' => fake()->randomFloat(1, 36.0, 39.5),
                'systolic_bp' => fake()->numberBetween(90, 160),
                'diastolic_bp' => fake()->numberBetween(60, 100),
                'heart_rate' => fake()->numberBetween(60, 120),
                'respiratory_rate' => fake()->numberBetween(12, 28),
                'spo2' => fake()->numberBetween(92, 100),
            ],
            'algorithmic_priority' => $priority,
            'final_priority' => $priority,
            'is_override' => false,
            'target_wait_minutes' => $priority->targetWaitMinutes(),
            'status' => TriageStatus::PENDING_CONSULTATION,
        ];
    }
}
