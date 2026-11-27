<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Acceso a módulos por rol (CEN-15 · docs/specs/auth/spec.md §4).
        Gate::define('access-admission', fn (User $user) => in_array($user->role, [UserRole::ADMISSION, UserRole::DIRECTOR], true)
        );

        Gate::define('access-triage', fn (User $user) => in_array($user->role, [UserRole::TRIAGE_NURSE, UserRole::DOCTOR, UserRole::DIRECTOR], true)
        );

        Gate::define('access-doctor', fn (User $user) => in_array($user->role, [UserRole::DOCTOR, UserRole::DIRECTOR], true)
        );

        Gate::define('access-analytics', fn (User $user) => $user->role === UserRole::DIRECTOR
        );

        Gate::define('access-epidemiology', fn (User $user) => $user->role === UserRole::DIRECTOR
        );

        // Reglas de dominio previas: se revisan en los Sprints 3 y 4.
        Gate::define('admit-patient', fn (User $user) => in_array($user->role, [UserRole::ADMISSION, UserRole::TRIAGE_NURSE], true)
        );

        Gate::define('classify-triage', fn (User $user) => in_array($user->role, [UserRole::TRIAGE_NURSE, UserRole::DOCTOR], true)
        );

        Gate::define('clinical-override', fn (User $user) => $user->role === UserRole::DOCTOR
        );

        Gate::define('attend-patient', fn (User $user) => $user->role === UserRole::DOCTOR
        );

        Gate::define('view-epidemiology', fn (User $user) => in_array($user->role, [UserRole::DIRECTOR, UserRole::DOCTOR, UserRole::TRIAGE_NURSE], true)
        );
    }
}
