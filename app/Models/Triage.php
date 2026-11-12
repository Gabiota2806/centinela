<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use Database\Factories\TriageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'patient_id',
    'user_id',
    'symptoms',
    'vital_signs',
    'algorithmic_priority',
    'final_priority',
    'is_override',
    'target_wait_minutes',
    'status',
])]
class Triage extends Model
{
    /** @use HasFactory<TriageFactory> */
    use HasFactory;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'vital_signs' => 'array',
            'algorithmic_priority' => TriagePriority::class,
            'final_priority' => TriagePriority::class,
            'status' => TriageStatus::class,
            'is_override' => 'boolean',
            'target_wait_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Profesional que realizó la categorización de triage.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TriageAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(TriageAuditLog::class);
    }

    /**
     * @return HasOne<Consultation, $this>
     */
    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }
}
