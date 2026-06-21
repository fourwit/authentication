@php
    $labels = [
        'email' => 'Email',
        'phone' => 'Phone',
    ];

    $inputTypes = [
        'email' => 'email',
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
            value="{{ old($field) }}"
            autocomplete="{{ $field }}"
        >
        @error($field)
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>
@endforeach
