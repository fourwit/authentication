<x-authentication::layouts.master title="Account locked">
    <h1>Account locked</h1>
    <p>Your account has been temporarily locked after too many failed login attempts.</p>

    <div class="links">
        @if (\Illuminate\Support\Facades\Route::has('authentication.password.request'))
            <a href="{{ route('authentication.password.request') }}">Reset password</a>
        @endif
        <a href="{{ route('authentication.login') }}">Back to login</a>
    </div>
</x-authentication::layouts.master>
