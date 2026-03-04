<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <div class="input-group-styled position-relative">
                <span class="input-icon"><i class="bi bi-envelope"></i></span>
                <x-text-input id="email" class="form-control" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="Email address" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="mb-3">
            <div class="input-group-styled position-relative">
                <span class="input-icon"><i class="bi bi-lock"></i></span>
                <x-text-input id="password" class="form-control" type="password" name="password" required autocomplete="current-password" placeholder="Password" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Remember Me -->
        <div class="mb-4 form-check">
            <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
            <label class="form-check-label" for="remember_me">{{ __('Remember me') }}</label>
        </div>

        <button type="submit" class="btn btn-login mb-3">
            <i class="bi bi-box-arrow-in-right me-1"></i> {{ __('Sign In') }}
        </button>

        @if (Route::has('password.request'))
            <div class="text-center">
                <a class="small" href="{{ route('password.request') }}" style="color:var(--text-muted);">
                    {{ __('Forgot your password?') }}
                </a>
            </div>
        @endif
    </form>
</x-guest-layout>
