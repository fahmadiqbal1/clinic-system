<section>
    <p class="small mb-3" style="color:var(--text-muted);">
        {{ __('Ensure your account is using a long, random password to stay secure.') }}
    </p>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="mb-3">
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <div class="input-group mt-1">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <x-text-input id="update_password_current_password" name="current_password" type="password" class="form-control" autocomplete="current-password" placeholder="Current password" />
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1" />
        </div>

        <div class="mb-3">
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <div class="input-group mt-1">
                <span class="input-group-text"><i class="bi bi-key"></i></span>
                <x-text-input id="update_password_password" name="password" type="password" class="form-control" autocomplete="new-password" placeholder="New password" />
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1" />
        </div>

        <div class="mb-3">
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <div class="input-group mt-1">
                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" placeholder="Confirm new password" />
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1" />
        </div>

        <div class="d-flex align-items-center gap-3">
            <x-primary-button><i class="bi bi-check-lg me-1"></i>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <span class="small" style="color:var(--accent-success);"><i class="bi bi-check-circle me-1"></i>{{ __('Saved.') }}</span>
            @endif
        </div>
    </form>
</section>
