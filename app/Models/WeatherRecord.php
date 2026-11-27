<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WeatherRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Serie climática diaria de Formosa Capital ingerida desde Open-Meteo (RF-GEO-01).
 */
#[Fillable([
    'recorded_date',
    'latitude',
    'longitude',
    'temperature_max',
    'temperature_min',
    'precipitation_sum',
    'relative_humidity_mean',
    'source',
    'raw_payload',
])]
class WeatherRecord extends Model
{
    /** @use HasFactory<WeatherRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'temperature_max' => 'float',
            'temperature_min' => 'float',
            'precipitation_sum' => 'float',
            'relative_humidity_mean' => 'float',
            'raw_payload' => 'array',
        ];
    }

    /**
     * Retorna los últimos N días disponibles ordenados desde el más reciente.
     *
     * @param  Builder<WeatherRecord>  $query
     * @return Builder<WeatherRecord>
     */
    public function scopeRecentDays(Builder $query, int $days = 7): Builder
    {
        return $query
            ->orderByDesc('recorded_date')
            ->limit(max(1, $days));
    }
}
