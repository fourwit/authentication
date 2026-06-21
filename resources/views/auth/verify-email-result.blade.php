<x-authentication::layouts.master title="Verification result">
    <h1>Email verification</h1>
    <p>{{ $message ?? 'Your email verification request has been processed.' }}</p>

    <div class="links">
        <a href="{{ route('authentication.login') }}">Back to login</a>
    </div>
</x-authentication::layouts.master>
