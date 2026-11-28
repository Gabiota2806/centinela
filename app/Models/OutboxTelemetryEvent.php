<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OutboxTelemetryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro Outbox de telemetría epidemiológica estrictamente disociada (Ley N° 25.326).
 * Referencia: docs/specs/00_contracts.md §2 y §4.1.
 */
#[Fillable([
    'event_uuid',
    'consultation_id',
    'payload',
    'status',
    'attempts',
    'last_error',
    'sent_at',
])]
class OutboxTelemetryEvent extends Model
{
    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => OutboxTelemetryStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Consultation, $this>
     */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /**
     * Filtra eventos pendientes de envío respetando orden FIFO mediante idx_outbox_pending_queue.
     *
     * @param  Builder<OutboxTelemetryEvent>  $query
     * @return Builder<OutboxTelemetryEvent>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->where('status', OutboxTelemetryStatus::PENDING)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }
}
