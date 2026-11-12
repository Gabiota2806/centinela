<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmission(): bool
    {
        return $this->role === UserRole::ADMISSION;
    }

    public function isTriageNurse(): bool
    {
        return $this->role === UserRole::TRIAGE_NURSE;
    }

    public function isDoctor(): bool
    {
        return $this->role === UserRole::DOCTOR;
    }

    public function isDirector(): bool
    {
        return $this->role === UserRole::DIRECTOR;
    }

    /**
     * Ruta de inicio según el rol (docs/specs/auth/spec.md §4).
     */
    public function homeRoute(): string
    {
        return ($this->role ?? UserRole::ADMISSION)->homeRoute();
    }

    /**
     * @return HasMany<Triage, $this>
     */
    public function triages(): HasMany
    {
        return $this->hasMany(Triage::class);
    }

    /**
     * @return HasMany<Consultation, $this>
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'doctor_id');
    }

    /**
     * @return HasMany<TriageAuditLog, $this>
     */
    public function triageAuditLogs(): HasMany
    {
        return $this->hasMany(TriageAuditLog::class);
    }
}
