<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TriagePriority;
use App\Enums\TriageStatus;
use Database\Factories\TriageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    /**
     * Ordena los registros de triage según jerarquía clínica Manchester (MTS)
     * de mayor a menor gravedad (Rojo a Azul) y por orden de llegada (FIFO).
     *
     * @param  Builder<Triage>  $query
     * @return Builder<Triage>
     */
    public function scopeOrderedByPriority(Builder $query): Builder
    {
        return $query->orderByRaw(
            "CASE final_priority
                WHEN 'red' THEN 1
                WHEN 'orange' THEN 2
                WHEN 'yellow' THEN 3
                WHEN 'green' THEN 4
                WHEN 'blue' THEN 5
                ELSE 6
            END ASC"
        )->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Filtra la cola médica activa (pacientes en espera de consulta) ordenada por prioridad MTS.
     *
     * @param  Builder<Triage>  $query
     * @return Builder<Triage>
     */
    public function scopeMedicalQueue(Builder $query): Builder
    {
        return $query
            ->where('status', TriageStatus::PENDING_CONSULTATION)
            ->orderedByPriority();
    }

    /**
     * Aplica un Clinical Override manual sobre la prioridad del paciente,
     * actualizando final_priority, activando is_override = true, recalculando
     * target_wait_minutes y persistiendo el registro inmutable en triage_audit_logs.
     *
     * @throws InvalidArgumentException Si la justificación está vacía en un override no diferido.
     */
    public function overridePriority(
        TriagePriority $newPriority,
        string $reason,
        User $user,
        bool $deferred = false
    ): TriageAuditLog {
        $trimmedReason = trim($reason);

        if (! $deferred && $trimmedReason === '') {
            throw new InvalidArgumentException(
                'Debe proporcionar una justificación clínica válida para el Clinical Override.'
            );
        }

        $effectiveReason = $trimmedReason !== ''
            ? ($deferred ? "[JUSTIFICACIÓN DIFERIDA] {$trimmedReason}" : $trimmedReason)
            : '[JUSTIFICACIÓN DIFERIDA] Pendiente de redacción por emergencia clínica.';

        return DB::transaction(function () use ($newPriority, $effectiveReason, $user): TriageAuditLog {
            $originalPriority = $this->final_priority ?? $this->algorithmic_priority ?? $newPriority;

            $this->final_priority = $newPriority;
            $this->is_override = true;
            $this->target_wait_minutes = $newPriority->targetWaitMinutes();
            $this->save();

            return $this->auditLogs()->create([
                'user_id' => $user->id,
                'original_priority' => $originalPriority,
                'overridden_priority' => $newPriority,
                'override_reason' => $effectiveReason,
            ]);
        });
    }
}
