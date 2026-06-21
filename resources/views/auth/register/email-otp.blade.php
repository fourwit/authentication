<x-authentication::layouts.master title="Create account">
    <h1>Create account</h1>
    <p>Start registration with your email and finish verification with a one-time code.</p>

    <form method="POST" action="{{ route('authentication.register.store') }}" class="stack">
        @csrf
        <input type="hidden" name="auth_method" value="{{ $authMethod }}">
        @include('authentication::auth.register._form-fields', ['fieldDefinitions' => $fieldDefinitions, 'requiredFields' => $requiredFields])
        <button class="btn" type="submit">Register with email OTP</button>
    </form>

    <div class="links">
        <a class="link-chip" href="{{ route('authentication.login') }}">Back to login</a>
    </div>
</x-authentication::layouts.master>
