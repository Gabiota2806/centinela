<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Neighborhood;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento interno emitido cuando la sincronización predictiva detecta riesgo alto
 * de brote epidemiológico en un barrio (RF-GEO-09).
 */
class HighEpidemiologicalRiskDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Neighborhood $neighborhood,
        public readonly float $riskScore,
        public readonly ?string $previousRiskLevel,
        public readonly string $modelVersion,
        public readonly CarbonImmutable $predictedAt,
    ) {}
}
