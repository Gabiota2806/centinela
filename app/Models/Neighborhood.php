<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NeighborhoodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'geometry', 'risk_level', 'risk_score', 'risk_updated_at'])]
class Neighborhood extends Model
{
    /** @use HasFactory<NeighborhoodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risk_score' => 'float',
            'risk_updated_at' => 'datetime',
        ];
    }

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
     * Filtra barrios por coincidencia parcial en el nombre (búsqueda insensible a mayúsculas/minúsculas).
     *
     * @param  Builder<Neighborhood>  $query
     * @return Builder<Neighborhood>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where('name', 'ILIKE', "%{$term}%");
    }

    /**
     * @return HasMany<Patient, $this>
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
