<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NeighborhoodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'geometry', 'risk_level'])]
class Neighborhood extends Model
{
    /** @use HasFactory<NeighborhoodFactory> */
    use HasFactory;

    /**
     * Filtra el barrio cuyo polígono (SRID 4326) contiene el punto geográfico (latitud, longitud).
     *
     * @param  Builder<Neighborhood>  $query
     * @return Builder<Neighborhood>
     */
    public function scopeContainingPoint(Builder $query, float $latitude, float $longitude): Builder
    {
        return $query->whereRaw(
            'ST_Contains(geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326))',
            [$longitude, $latitude]
        );
    }

    /**
     * @return HasMany<Patient, $this>
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
