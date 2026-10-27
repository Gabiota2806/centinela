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
        Gate::define('admit-patient', fn (User $user) =>
            in_array($user->role, [UserRole::Admision, UserRole::Triage])
        );

        Gate::define('classify-triage', fn (User $user) =>
            in_array($user->role, [UserRole::Triage, UserRole::Medico])
        );

        Gate::define('clinical-override', fn (User $user) =>
            $user->role === UserRole::Medico
        );

        Gate::define('attend-patient', fn (User $user) =>
            $user->role === UserRole::Medico
        );

        Gate::define('view-epidemiology', fn (User $user) =>
            in_array($user->role, [UserRole::Ministerio, UserRole::Medico, UserRole::Triage])
        );
    }
}
