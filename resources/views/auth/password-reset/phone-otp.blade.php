<x-authentication::layouts.master title="Forgot password">
    <h1>Forgot password</h1>
    <p>Enter your phone number and we’ll send you a recovery code.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('authentication.password.email') }}" class="stack">
        @csrf
        <input type="hidden" name="auth_method" value="{{ $authMethod }}">
        @include('authentication::auth.password-reset._form-fields', ['fieldDefinitions' => $fieldDefinitions, 'requiredFields' => $requiredFields])
        <button class="btn" type="submit">Send recovery code</button>
    </form>

    <div class="links">
        <a class="link-chip" href="{{ route('authentication.login') }}">Back to login</a>
        @if (\Illuminate\Support\Facades\Route::has('authentication.register'))
            <a class="link-chip primary" href="{{ route('authentication.register') }}">Create account</a>
        @endif
    </div>
</x-authentication::layouts.master>
