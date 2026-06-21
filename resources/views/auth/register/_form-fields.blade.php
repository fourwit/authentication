@php
    $labels = [
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'username' => 'Username',
        'phone' => 'Phone',
        'first_name' => 'First name',
        'last_name' => 'Last name',
    ];

    $inputTypes = [
        'email' => 'email',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => 'text',
    ];
@endphp

@foreach ($fieldDefinitions as $field => $metadata)
    @php
        $isRequired = (bool) data_get($metadata, 'required', false);
    @endphp
    @if ($field === 'phone')
        <x-authentication::phone-input
            :name="$field"
            :id="$field"
            :label="$labels[$field] ?? ucwords(str_replace('_', ' ', $field))"
            :required="$isRequired"
            :value="old($field)"
            autocomplete="tel"
        />
        @continue
    @endif

    @if ($field === 'password')
        <x-authentication::password-input
            :name="$field"
            :id="$field"
            :label="$labels[$field] ?? ucwords(str_replace('_', ' ', $field))"
            :required="$isRequired"
            autocomplete="new-password"
            :show-meter="(bool) config('authentication.registration.show_password_strength_meter') && \Modules\Authentication\Support\PasswordPolicy::strengthMeterEnabled()"
        />
        @continue
    @endif

    <div class="field">
        <label for="{{ $field }}">
            {{ $labels[$field] ?? ucwords(str_replace('_', ' ', $field)) }}
            @if ($isRequired)
                <span style="color:#dc2626;">*</span>
            @else
                <span style="color:#6b7280; font-weight:400;">Optional</span>
            @endif
        </label>
        <input
            class="input @error($field) is-invalid @enderror"
            id="{{ $field }}"
            type="{{ $inputTypes[$field] ?? 'text' }}"
            name="{{ $field }}"
            value="{{ in_array($field, ['password', 'password_confirmation'], true) ? '' : old($field) }}"
            autocomplete="{{ $field }}"
        >
        @error($field)
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>
@endforeach

@if (in_array('password', $requiredFields, true) && ! in_array('password_confirmation', $requiredFields, true))
    <x-authentication::password-input
        name="password_confirmation"
        id="password_confirmation"
        label="Confirm password"
        :required="true"
        autocomplete="new-password"
    />
@endif
