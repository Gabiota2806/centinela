<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EpidemiologySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EpidemiologySetting>
 */
class EpidemiologySettingFactory extends Factory
{
    protected $model = EpidemiologySetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rain_threshold_mm' => EpidemiologySetting::DEFAULT_RAIN_THRESHOLD_MM,
            'time_lag_days' => EpidemiologySetting::DEFAULT_TIME_LAG_DAYS,
            'febrile_alert_threshold' => EpidemiologySetting::DEFAULT_FEBRILE_ALERT_THRESHOLD,
            'high_risk_probability' => EpidemiologySetting::DEFAULT_HIGH_RISK_PROBABILITY,
            'updated_by' => null,
        ];
    }
}
