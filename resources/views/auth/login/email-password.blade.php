<x-authentication::layouts.master title="Sign in">
    <h1>Sign in</h1>
    <p>Access your account with your email or phone and password.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('authentication.login') }}" class="stack">
        @csrf
        <input type="hidden" name="auth_method" value="{{ $authMethod }}">
        @include('authentication::auth.login._form-fields', ['fieldDefinitions' => $fieldDefinitions, 'requiredFields' => $requiredFields])
        <button class="btn" type="submit">Sign in with email</button>
    </form>

    @if (! empty($switchLinks ?? []))
        <div class="links" style="margin-top:14px;">
            @foreach ($switchLinks as $switchLink)
                <a class="link-chip" href="{{ route('login', ['auth_method' => $switchLink['method']]) }}">{{ $switchLink['label'] }}</a>
            @endforeach
        </div>
    @endif

    <div class="links">
        @if (\Illuminate\Support\Facades\Route::has('authentication.register'))
            <a class="link-chip primary" href="{{ route('authentication.register') }}">Create account</a>
        @endif
        @if (\Illuminate\Support\Facades\Route::has('authentication.password.request'))
            <a class="link-chip" href="{{ route('authentication.password.request') }}">Forgot password?</a>
        @endif
    </div>
</x-authentication::layouts.master>
