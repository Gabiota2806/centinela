<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-ink">Ingresá al sistema</h2>
        <p class="mt-1 text-sm text-ink-muted">{{ config('centinela.node_name') }}</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />

            <x-text-input id="password" class="block mt-1.5 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-line text-brand-petroleo shadow-sm focus:ring-brand-turquesa" name="remember">
                <span class="ms-2 text-sm text-ink-muted">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-semibold text-brand-turquesa hover:text-brand-petroleo rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <x-primary-button class="w-full mt-6 h-12 text-base">
            Ingresar
        </x-primary-button>
    </form>
</x-guest-layout>
