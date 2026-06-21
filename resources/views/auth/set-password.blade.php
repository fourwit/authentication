<x-authentication::layouts.master title="Set password">
    <h1>Set your password</h1>
    <p>Create a password for your new account. You can also skip this step for now if your host app allows it.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route(config('authentication.after_otp_registration.password_setup_route', 'auth.set-password') . '.store') }}" class="stack">
        @csrf
        <x-authentication::password-input
            name="password"
            id="password"
            label="Password"
            :required="true"
            autocomplete="new-password"
            :show-strength-meter="$showStrengthMeter ?? true"
        />
        <x-authentication::password-input
            name="password_confirmation"
            id="password_confirmation"
            label="Confirm password"
            :required="true"
            autocomplete="new-password"
        />
        <button class="btn" type="submit">Save password</button>
    </form>

    @if (! ($passwordRequired ?? false))
        <div class="links">
            <form method="POST" action="{{ route(config('authentication.after_otp_registration.password_setup_route', 'auth.set-password') . '.skip') }}">
                @csrf
                <button class="link-chip" type="submit" style="background:none;">Skip for now</button>
            </form>
        </div>
    @endif
</x-authentication::layouts.master>
