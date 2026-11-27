<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EpidemiologySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parámetros operativos configurables de alerta epidemiológica (RF-GEO-03).
 */
#[Fillable([
    'rain_threshold_mm',
    'time_lag_days',
    'febrile_alert_threshold',
    'high_risk_probability',
    'updated_by',
])]
class EpidemiologySetting extends Model
{
    /** @use HasFactory<EpidemiologySettingFactory> */
    use HasFactory;

    public const DEFAULT_RAIN_THRESHOLD_MM = 40.00;

    public const DEFAULT_TIME_LAG_DAYS = 10;

    public const DEFAULT_FEBRILE_ALERT_THRESHOLD = 5;

    public const DEFAULT_HIGH_RISK_PROBABILITY = 0.70;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rain_threshold_mm' => 'float',
            'time_lag_days' => 'integer',
            'febrile_alert_threshold' => 'integer',
            'high_risk_probability' => 'float',
        ];
    }

    /**
     * Devuelve el registro vigente de parámetros o crea uno con los valores del contrato.
     */
    public static function current(): self
    {
        $existing = self::query()->with('updatedBy')->orderBy('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        return self::query()->create([
            'rain_threshold_mm' => self::DEFAULT_RAIN_THRESHOLD_MM,
            'time_lag_days' => self::DEFAULT_TIME_LAG_DAYS,
            'febrile_alert_threshold' => self::DEFAULT_FEBRILE_ALERT_THRESHOLD,
            'high_risk_probability' => self::DEFAULT_HIGH_RISK_PROBABILITY,
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
