<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\Triage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'triage_id' => Triage::factory(),
            'doctor_id' => User::factory()->doctor(),
            'clinical_notes' => fake()->paragraph(),
            'diagnosis' => fake()->sentence(),
            'prescription' => fake()->sentence(),
            'status' => ConsultationStatus::IN_PROGRESS,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
