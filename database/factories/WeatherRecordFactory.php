<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WeatherRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeatherRecord>
 */
class WeatherRecordFactory extends Factory
{
    protected $model = WeatherRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = fake()->randomFloat(2, 16, 25);
        $max = fake()->randomFloat(2, $min + 4, 39);

        return [
            'recorded_date' => fake()->unique()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'latitude' => -26.1800,
            'longitude' => -58.1700,
            'temperature_max' => $max,
            'temperature_min' => $min,
            'precipitation_sum' => fake()->randomFloat(2, 0, 75),
            'relative_humidity_mean' => fake()->randomFloat(2, 48, 95),
            'source' => 'open-meteo',
            'raw_payload' => null,
        ];
    }
}
