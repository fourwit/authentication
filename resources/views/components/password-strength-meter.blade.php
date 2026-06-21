@props([
    'fieldId' => 'password',
    'policy' => [],
])

@php
    $policyJson = json_encode($policy, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $showHints = (bool) data_get($policy, 'strength_meter.show_hints', true);
@endphp

<div
    class="password-meter"
    data-password-meter
    data-target="{{ $fieldId }}"
    data-policy='{{ $policyJson }}'
>
    <div class="password-meter__header">
        <span class="password-meter__label">Password strength</span>
        <span class="password-meter__status" data-password-meter-status></span>
    </div>
    <div class="password-meter__track" aria-hidden="true">
        <span class="password-meter__bar" data-password-meter-bar></span>
    </div>

    @if ($showHints)
        <ul class="password-meter__hints" data-password-meter-hints>
            <li data-hint="length">At least <span data-password-min-length>{{ data_get($policy, 'min_length', 8) }}</span> characters</li>
            @if (data_get($policy, 'require_mixed_case', false))
                <li data-hint="mixed_case">Uppercase and lowercase letters</li>
            @endif
            @if (data_get($policy, 'require_numbers', false))
                <li data-hint="numbers">At least one number</li>
            @endif
            @if (data_get($policy, 'require_symbols', false))
                <li data-hint="symbols">At least one symbol</li>
            @endif
        </ul>
    @endif
</div>
