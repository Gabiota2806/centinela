<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Neighborhood;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dni' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-1 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['Masculino', 'Femenino', 'Otro']),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'neighborhood_id' => Neighborhood::factory(),
        ];
    }
}
