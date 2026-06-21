<x-authentication::layouts.master title="Reset password">
    <h1>Reset password</h1>
    <p>Choose a new password for your account.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('authentication.password.update') }}" class="stack">
        @csrf
        <input type="hidden" name="auth_method" value="{{ old('auth_method', $authMethod ?? 'link') }}">
        <input type="hidden" name="token" value="{{ old('token', $token ?? request('token')) }}">
        <input type="hidden" name="email" value="{{ old('email', $email ?? request('email')) }}">
        <input type="hidden" name="phone" value="{{ old('phone', $phone ?? request('phone')) }}">
        <input type="hidden" name="reset_grant" value="{{ old('reset_grant', $resetGrant ?? '') }}">

        <x-authentication::password-input
            name="password"
            id="password"
            label="New password"
            :required="true"
            autocomplete="new-password"
            :show-meter="\Modules\Authentication\Support\PasswordPolicy::strengthMeterEnabled()"
        />
        <x-authentication::password-input
            name="password_confirmation"
            id="password_confirmation"
            label="Confirm new password"
            :required="true"
            autocomplete="new-password"
        />
        <button class="btn" type="submit">Reset password</button>
    </form>

    <div class="links">
        <a class="link-chip" href="{{ route('authentication.login') }}">Back to login</a>
    </div>
</x-authentication::layouts.master>
