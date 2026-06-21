@php
    $labels = [
        'email' => 'Email',
        'phone' => 'Phone',
        'password' => 'Password',
        'remember' => 'Remember me',
    ];

    $inputTypes = [
        'email' => 'email',
        'password' => 'password',
    ];
@endphp

@if ($errors->has('auth_method'))
    <div class="alert alert-error">{{ $errors->first('auth_method') }}</div>
@endif

@foreach ($fieldDefinitions as $field => $metadata)
    @php
        $isRequired = (bool) data_get($metadata, 'required', false);
    @endphp

    @if ($field === 'phone')
        <x-authentication::phone-input
            :name="$field"
            :id="$field"
            :label="$labels[$field]"
            :required="$isRequired"
            :value="old($field)"
            autocomplete="tel"
        />
        @continue
    @endif

    <div class="field">
        <label for="{{ $field }}">
            {{ $labels[$field] ?? ucwords(str_replace('_', ' ', $field)) }}
            @if ($isRequired)
                <span style="color:#dc2626;">*</span>
            @endif
        </label>
        <input
            class="input @error($field) is-invalid @enderror"
            id="{{ $field }}"
            type="{{ $inputTypes[$field] ?? 'text' }}"
            name="{{ $field }}"
            value="{{ $field === 'password' ? '' : old($field) }}"
            autocomplete="{{ $field === 'password' ? 'current-password' : $field }}"
        >
        @error($field)
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>
@endforeach

@if (config('authentication.login.remember_me', true))
    <label style="display:flex; gap:8px; align-items:center; margin-bottom: 14px;">
        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
        <span>{{ $labels['remember'] }}</span>
    </label>
@endif
