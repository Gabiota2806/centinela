<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Neighborhood;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Neighborhood>
 */
class NeighborhoodFactory extends Factory
{
    protected $model = Neighborhood::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Centro',
            'geometry' => DB::raw("ST_SetSRID(ST_GeomFromText('POLYGON((-58.18 -26.18, -58.17 -26.18, -58.17 -26.19, -58.18 -26.19, -58.18 -26.18))'), 4326)"),
            'risk_level' => 'normal',
        ];
    }
}
