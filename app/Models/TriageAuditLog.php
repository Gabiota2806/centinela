<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TriagePriority;
use App\Exceptions\ImmutableModelException;
use Database\Factories\TriageAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'triage_id',
    'user_id',
    'original_priority',
    'overridden_priority',
    'override_reason',
])]
class TriageAuditLog extends Model
{
    /** @use HasFactory<TriageAuditLogFactory> */
    use HasFactory;

    /**
     * Inmutabilidad médico-legal: no existe columna updated_at.
     */
    public const UPDATED_AT = null;

    /**
     * Intercepción de eventos de mutación para garantizar inmutabilidad estricta.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ImmutableModelException::cannotUpdate();
        });

        static::deleting(function (): void {
            throw ImmutableModelException::cannotDelete();
        });
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'original_priority' => TriagePriority::class,
            'overridden_priority' => TriagePriority::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Triage, $this>
     */
    public function triage(): BelongsTo
    {
        return $this->belongsTo(Triage::class);
    }

    /**
     * Profesional médico que fundamentó el Clinical Override.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
