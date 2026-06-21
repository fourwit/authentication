@props([
    'name' => 'phone',
    'id' => null,
    'value' => null,
    'label' => 'Phone',
    'required' => false,
    'optionalLabel' => 'Optional',
    'errorBag' => 'default',
    'autocomplete' => 'tel',
    'placeholder' => null,
])

@php
    $config = \Modules\Authentication\Support\PhoneInputConfig::viewConfig();
    $inputId = $id ?: $name;
    $currentValue = old($name, $value);
    $showEnhanced = $config['enabled'] && $config['library'] === 'intl-tel-input';
    $effectiveLibrary = $showEnhanced ? 'intl-tel-input' : 'none';
    $hiddenName = $name . '_normalized';
    $errorClass = $errors->{$errorBag}->has($name) ? 'is-invalid' : '';
    $assetVersion = rawurlencode($config['version']);
    $utilsUrl = $showEnhanced && $config['cdn'] ? "https://cdn.jsdelivr.net/npm/intl-tel-input@{$assetVersion}/build/js/utils.js" : null;
    $cssUrl = $showEnhanced && $config['cdn'] ? "https://cdn.jsdelivr.net/npm/intl-tel-input@{$assetVersion}/build/css/intlTelInput.css" : null;
    $jsUrl = $showEnhanced && $config['cdn'] ? "https://cdn.jsdelivr.net/npm/intl-tel-input@{$assetVersion}/build/js/intlTelInput.min.js" : null;
@endphp

<div class="field">
    <label for="{{ $inputId }}">
        {{ $label }}
        @if ($required)
            <span style="color:#dc2626;">*</span>
        @else
            <span style="color:#6b7280; font-weight:400;">{{ $optionalLabel }}</span>
        @endif
    </label>

    <input
        class="input {{ $errorClass }}"
        id="{{ $inputId }}"
        type="tel"
        name="{{ $name }}"
        value="{{ $currentValue }}"
        autocomplete="{{ $autocomplete }}"
        inputmode="tel"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        data-phone-input="true"
        data-phone-library="{{ $effectiveLibrary }}"
        data-phone-store-format="{{ $config['store_format'] }}"
        data-phone-default-country="{{ strtolower($config['default_country']) }}"
        data-phone-preferred-countries='@json(array_map("strtolower", $config["preferred_countries"]))'
        data-phone-only-countries='@json(array_map("strtolower", $config["only_countries"]))'
        data-phone-separate-dial-code="{{ $config['separate_dial_code'] ? 'true' : 'false' }}"
    >

    <input
        type="hidden"
        name="{{ $hiddenName }}"
        value=""
        data-phone-normalized-for="{{ $inputId }}"
    >

    @error($name, $errorBag)
        <div class="field-error">{{ $message }}</div>
    @enderror
</div>

@if ($showEnhanced && $config['cdn'])
    @pushOnce('authentication-head')
        <link rel="stylesheet" href="{{ $cssUrl }}">
        <style>
            .field .iti {
                width: 100%;
                display: block;
            }

            .field .iti__tel-input,
            .field .iti input[type="tel"] {
                width: 100%;
            }
        </style>
    @endPushOnce
@endif

@pushOnce('authentication-scripts')
    <script>
        window.AuthenticationPhoneInput = window.AuthenticationPhoneInput || (function () {
            let intlTelInputReady = false;
            let intlTelInputLoader = null;

            function loadIntlTelInputAssets() {
                if (intlTelInputReady) {
                    return Promise.resolve(window.intlTelInput || null);
                }

                if (intlTelInputLoader) {
                    return intlTelInputLoader;
                }

                const candidates = Array.from(document.querySelectorAll('[data-phone-input="true"]'));
                const first = candidates[0];

                if (!first || first.dataset.phoneLibrary !== 'intl-tel-input') {
                    intlTelInputReady = true;
                    return Promise.resolve(window.intlTelInput || null);
                }

                if (window.intlTelInput) {
                    intlTelInputReady = true;
                    return Promise.resolve(window.intlTelInput);
                }

                const usesCdn = {{ $config['cdn'] ? 'true' : 'false' }};
                if (!usesCdn) {
                    intlTelInputReady = true;
                    return Promise.resolve(window.intlTelInput || null);
                }

                intlTelInputLoader = new Promise((resolve) => {
                    const script = document.createElement('script');
                    script.src = @json($jsUrl);
                    script.async = true;
                    script.onload = function () {
                        intlTelInputReady = true;
                        resolve(window.intlTelInput || null);
                    };
                    script.onerror = function () {
                        intlTelInputReady = true;
                        resolve(null);
                    };
                    document.head.appendChild(script);
                });

                return intlTelInputLoader;
            }

            function formatNumber(instance, input) {
                if (!instance || !window.intlTelInputUtils) {
                    return input.value;
                }

                const storeFormat = input.dataset.phoneStoreFormat || 'e164';
                const number = instance.getNumber();

                if (!number) {
                    return input.value;
                }

                const formatMap = {
                    e164: window.intlTelInputUtils.numberFormat.E164,
                    international: window.intlTelInputUtils.numberFormat.INTERNATIONAL,
                    national: window.intlTelInputUtils.numberFormat.NATIONAL,
                };

                const selectedFormat = formatMap[storeFormat] || formatMap.e164;
                return instance.getNumber(selectedFormat) || number;
            }

            function bindInput(input) {
                if (!input || input.dataset.phoneInputBound === 'true') {
                    return;
                }

                input.dataset.phoneInputBound = 'true';

                const hidden = document.querySelector('[data-phone-normalized-for="' + input.id + '"]');
                const syncFallback = function () {
                    if (hidden) {
                        hidden.value = input.value || '';
                    }
                };

                syncFallback();
                input.addEventListener('input', syncFallback);
                input.addEventListener('change', syncFallback);

                if (input.dataset.phoneLibrary !== 'intl-tel-input' || !window.intlTelInput) {
                    return;
                }

                try {
                    const iti = window.intlTelInput(input, {
                        initialCountry: input.dataset.phoneDefaultCountry || 'in',
                        preferredCountries: JSON.parse(input.dataset.phonePreferredCountries || '[]'),
                        onlyCountries: JSON.parse(input.dataset.phoneOnlyCountries || '[]'),
                        separateDialCode: input.dataset.phoneSeparateDialCode === 'true',
                        nationalMode: input.dataset.phoneStoreFormat === 'national',
                        utilsScript: @json($utilsUrl),
                    });

                    const syncEnhanced = function () {
                        if (hidden) {
                            hidden.value = formatNumber(iti, input) || input.value || '';
                        }
                    };

                    syncEnhanced();
                    input.addEventListener('input', syncEnhanced);
                    input.addEventListener('change', syncEnhanced);
                    input.form && input.form.addEventListener('submit', syncEnhanced);
                } catch (error) {
                    syncFallback();
                }
            }

            function init() {
                loadIntlTelInputAssets().then(function () {
                    document.querySelectorAll('[data-phone-input="true"]').forEach(bindInput);
                });
            }

            return { init: init };
        })();

        document.addEventListener('DOMContentLoaded', function () {
            if (window.AuthenticationPhoneInput && typeof window.AuthenticationPhoneInput.init === 'function') {
                window.AuthenticationPhoneInput.init();
            }
        });
    </script>
@endPushOnce
