@php($phoneInput = \Modules\Authentication\Support\PhoneInputConfig::viewConfig())

<x-authentication::layouts.master title="Forgot password">
    <h1>Forgot password</h1>
    <p>Use your email address or phone number to start account recovery.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('authentication.password.email') }}" class="stack">
        @csrf
        <div class="field">
            <label for="email">Email</label>
            <input class="input @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email">
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
        @if ($phoneInput['enabled'])
            <x-authentication::phone-input
                name="phone"
                id="phone"
                label="Phone"
                :value="old('phone')"
                autocomplete="tel"
            />
        @endif
        <button class="btn" type="submit">Send reset link</button>
    </form>

    <div class="links">
        <a class="link-chip" href="{{ route('authentication.login') }}">Back to login</a>
        @if (\Illuminate\Support\Facades\Route::has('authentication.register'))
            <a class="link-chip primary" href="{{ route('authentication.register') }}">Create account</a>
        @endif
    </div>
</x-authentication::layouts.master>
