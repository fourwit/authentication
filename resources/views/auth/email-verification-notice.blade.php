<x-authentication::layouts.master title="Verify email">
    <h1>Verify your email</h1>
    <p>Please check your inbox for a verification message.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="links">
        <a href="{{ route('authentication.verification.send') }}">Resend verification email</a>
        <a href="{{ route('authentication.login') }}">Back to login</a>
    </div>
</x-authentication::layouts.master>
