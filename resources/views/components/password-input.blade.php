@props([
    'name' => 'password',
    'id' => null,
    'label' => 'Password',
    'required' => false,
    'autocomplete' => 'new-password',
    'value' => '',
    'showMeter' => false,
    'showStrengthMeter' => null,
])

@php
    $inputId = $id ?? $name;
    $policy = \Modules\Authentication\Support\PasswordPolicy::frontendConfig();
    $shouldShowMeter = (bool) $showMeter || (bool) $showStrengthMeter;
@endphp

<div class="field">
    <label for="{{ $inputId }}">
        {{ $label }}
        @if ($required)
            <span style="color:#dc2626;">*</span>
        @else
            <span style="color:#6b7280; font-weight:400;">Optional</span>
        @endif
    </label>
    <input
        class="input @error($name) is-invalid @enderror"
        id="{{ $inputId }}"
        type="password"
        name="{{ $name }}"
        value="{{ $value }}"
        autocomplete="{{ $autocomplete }}"
    >
    @error($name)
        <div class="field-error">{{ $message }}</div>
    @enderror

    @if ($shouldShowMeter)
        <x-authentication::password-strength-meter :field-id="$inputId" :policy="$policy" />
    @endif
</div>

@once
    @push('authentication-head')
        <style>
            .password-meter { margin-top: 10px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f8fafc; }
            .password-meter__header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
            .password-meter__label { font-size: 0.9rem; font-weight: 600; color: #334155; }
            .password-meter__status { font-size: 0.85rem; font-weight: 600; color: #dc2626; }
            .password-meter__status:empty { display: none; }
            .password-meter__track { width: 100%; height: 8px; border-radius: 999px; background: #e5e7eb; overflow: hidden; }
            .password-meter__bar { display: block; height: 100%; width: 0; background: #dc2626; transition: width 120ms ease, background-color 120ms ease; }
            .password-meter__hints { margin: 10px 0 0; padding-left: 18px; color: #64748b; font-size: 0.88rem; }
            .password-meter__hints li { margin: 4px 0; }
            .password-meter__hints li.is-met { color: #15803d; }
            .password-meter--weak .password-meter__status { color: #dc2626; }
            .password-meter--fair .password-meter__status { color: #d97706; }
            .password-meter--good .password-meter__status { color: #2563eb; }
            .password-meter--strong .password-meter__status { color: #15803d; }
        </style>
    @endpush

    @push('authentication-scripts')
        <script>
            (function () {
                if (window.__authenticationPasswordMeterLoaded) {
                    return;
                }

                window.__authenticationPasswordMeterLoaded = true;

                function evaluatePassword(value, policy) {
                    if (!value) {
                        return {
                            empty: true,
                            checks: {
                                length: false,
                                mixed_case: false,
                                numbers: false,
                                symbols: false
                            },
                            score: 0
                        };
                    }

                    var checks = {
                        length: value.length >= Number(policy.min_length || 8),
                        mixed_case: !policy.require_mixed_case || (/[a-z]/.test(value) && /[A-Z]/.test(value)),
                        numbers: !policy.require_numbers || /\d/.test(value),
                        symbols: !policy.require_symbols || /[^A-Za-z0-9]/.test(value)
                    };

                    var score = 0;
                    Object.keys(checks).forEach(function (key) {
                        if (checks[key]) {
                            score += 1;
                        }
                    });

                    return { checks: checks, score: score };
                }

                function stateFor(score) {
                    if (score <= 1) {
                        return { label: 'Too weak', css: 'weak', color: '#dc2626', width: '25%' };
                    }

                    if (score === 2) {
                        return { label: 'Fair', css: 'fair', color: '#d97706', width: '50%' };
                    }

                    if (score === 3) {
                        return { label: 'Good', css: 'good', color: '#2563eb', width: '75%' };
                    }

                    return { label: 'Strong', css: 'strong', color: '#15803d', width: '100%' };
                }

                function mountMeter(node) {
                    var targetId = node.getAttribute('data-target');
                    var input = document.getElementById(targetId);

                    if (!input) {
                        return;
                    }

                    var policy;

                    try {
                        policy = JSON.parse(node.getAttribute('data-policy') || '{}');
                    } catch (error) {
                        policy = {};
                    }

                    var status = node.querySelector('[data-password-meter-status]');
                    var bar = node.querySelector('[data-password-meter-bar]');
                    var hints = node.querySelector('[data-password-meter-hints]');
                    var minScore = Number((policy.strength_meter || {}).min_score || 3);

                    function render() {
                        var result = evaluatePassword(input.value || '', policy);
                        var state = stateFor(result.score);

                        node.classList.remove('password-meter--weak', 'password-meter--fair', 'password-meter--good', 'password-meter--strong');

                        if (result.empty) {
                            if (status) {
                                status.textContent = '';
                            }

                            if (bar) {
                                bar.style.width = '0';
                                bar.style.backgroundColor = '#dc2626';
                            }

                            if (hints) {
                                hints.querySelectorAll('[data-hint]').forEach(function (hintNode) {
                                    hintNode.classList.remove('is-met');
                                });
                            }

                            return;
                        }

                        node.classList.add('password-meter--' + state.css);

                        if (status) {
                            status.textContent = result.score >= minScore ? state.label : 'Too weak';
                        }

                        if (bar) {
                            bar.style.width = state.width;
                            bar.style.backgroundColor = result.score >= minScore ? state.color : '#dc2626';
                        }

                        if (hints) {
                            hints.querySelectorAll('[data-hint]').forEach(function (hintNode) {
                                var key = hintNode.getAttribute('data-hint');
                                hintNode.classList.toggle('is-met', Boolean(result.checks[key]));
                            });
                        }
                    }

                    input.addEventListener('input', render);
                    input.addEventListener('blur', render);
                    render();
                }

                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('[data-password-meter]').forEach(mountMeter);
                });
            }());
        </script>
    @endpush
@endonce
