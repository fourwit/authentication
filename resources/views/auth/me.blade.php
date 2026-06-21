<x-authentication::layouts.master title="My account">
    <h1>My account</h1>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-authentication-password-reminder />

    <p>Name: {{ $user->name ?? '' }}</p>
    <p>Email: {{ $user->email ?? '' }}</p>
    <div class="links">
        @if (\Illuminate\Support\Facades\Route::has(config('authentication.after_otp_registration.password_setup_route', 'auth.set-password')))
            <a class="link-chip primary" href="{{ route(config('authentication.after_otp_registration.password_setup_route', 'auth.set-password')) }}">
                {{ ($passwordMissing ?? false) ? 'Set password' : 'Change password' }}
            </a>
        @endif
        <a href="{{ route('authentication.login') }}">Back to login</a>
    </div>
</x-authentication::layouts.master>
