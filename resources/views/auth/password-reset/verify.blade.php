@php
    $codeLength = (int) config('authentication.otp.length', 6);
    $verificationChannel = $verificationChannel ?? 'email';
    $verificationDestination = $verificationDestination ?? 'your account';
    $verificationLabel = $verificationChannel === 'phone' ? 'phone number' : 'email';
@endphp

<x-authentication::layouts.master title="Verify recovery code">
    <div style="text-align:center; margin-bottom: 24px;">
        <h1 style="margin-bottom: 8px;">Verify your recovery code</h1>
        <p style="margin-bottom: 0;">
            Enter the {{ $codeLength }}-digit code sent to your {{ $verificationLabel }} at
            <strong>{{ $verificationDestination }}</strong>.
        </p>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <form id="code-form" method="POST" action="{{ route('authentication.password.verify.store') }}" class="stack">
        @csrf
        <input type="hidden" name="code" id="hidden-code" value="">

        <div style="display:flex; justify-content:center; gap:12px; margin-bottom: 4px;">
            @php $hasCodeError = $errors->has('code'); @endphp
            @for ($i = 0; $i < $codeLength; $i++)
                <input
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    autocomplete="one-time-code"
                    data-index="{{ $i }}"
                    class="verification-digit"
                    style="
                        width: 48px;
                        height: 56px;
                        text-align: center;
                        font-size: 1.5rem;
                        font-weight: 600;
                        border: 2px solid {{ $hasCodeError ? '#dc2626' : '#d1d5db' }};
                        border-radius: 14px;
                        outline: none;
                        box-sizing: border-box;
                        transition: border-color 120ms ease, box-shadow 120ms ease;
                    "
                >
            @endfor
        </div>

        @if ($errors->has('code'))
            <div class="field-error" style="text-align:center;">{{ $errors->first('code') }}</div>
        @endif
    </form>

    <div style="margin-top: 20px; text-align:center;">
        @php
            $allowedAt = $resendAllowedAt ?? null;
            $cdSecs = $cooldownSeconds ?? 60;
            $isCarbon = $allowedAt instanceof \Illuminate\Support\Carbon;
            $allowedCarbon = $isCarbon ? $allowedAt : ($allowedAt ? \Illuminate\Support\Carbon::parse($allowedAt) : null);
            $disabled = $allowedCarbon && now()->lt($allowedCarbon);
            $untilIso = $allowedCarbon ? $allowedCarbon->toIso8601String() : null;
            $initialSecs = $disabled && $allowedCarbon ? max(0, (int) now()->diffInSeconds($allowedCarbon)) : $cdSecs;
        @endphp
        <form method="POST" action="{{ route('authentication.password.verify.resend') }}" style="display:inline;" id="resend-form">
            @csrf
            <button
                type="submit"
                {{ $disabled ? 'disabled' : '' }}
                style="
                    background: none;
                    border: 0;
                    color: #4f46e5;
                    font-size: 0.95rem;
                    cursor: {{ $disabled ? 'not-allowed' : 'pointer' }};
                    opacity: {{ $disabled ? '0.6' : '1' }};
                "
            >
                Resend code
            </button>
        </form>
        @if($disabled)
            <div class="meta resend-countdown-note" style="margin-top: 6px;">
                You can resend in <span class="resend-countdown" data-until="{{ $untilIso }}">{{ $initialSecs }}</span>s
            </div>
        @endif
    </div>

    <div style="margin-top: 20px; text-align:center;">
        <a href="{{ route('authentication.password.request') }}" style="color:#6b7280; font-size:0.95rem;">
            Back to recovery options
        </a>
    </div>

    <script>
        (function() {
            const form = document.getElementById('code-form');
            const hiddenCode = document.getElementById('hidden-code');
            const digits = Array.from(document.querySelectorAll('.verification-digit'));
            const codeLength = {{ $codeLength }};

            if (!form || digits.length === 0) return;

            function applyFocusStyle(input) {
                input.style.borderColor = '#6366f1';
                input.style.boxShadow = '0 0 0 4px rgba(99, 102, 241, 0.12)';
            }

            function clearFocusStyle(input) {
                const hasError = {{ $errors->has('code') ? 'true' : 'false' }};
                input.style.borderColor = hasError ? '#dc2626' : '#d1d5db';
                input.style.boxShadow = 'none';
            }

            function clearErrorStyles() {
                digits.forEach(function(input) {
                    input.style.borderColor = '#d1d5db';
                    input.style.boxShadow = 'none';
                });
            }

            function getCode() {
                return digits.map(function(input) {
                    return (input.value || '').trim();
                }).join('');
            }

            function updateHidden() {
                if (hiddenCode) {
                    hiddenCode.value = getCode();
                }
            }

            function submitIfComplete() {
                updateHidden();
                if (getCode().length === codeLength) {
                    setTimeout(function() {
                        form.submit();
                    }, 60);
                }
            }

            digits.forEach(function(input, index) {
                input.addEventListener('focus', function() {
                    applyFocusStyle(input);
                });

                input.addEventListener('blur', function() {
                    clearFocusStyle(input);
                });

                input.addEventListener('input', function() {
                    input.value = input.value.replace(/[^0-9]/g, '').slice(0, 1);
                    clearErrorStyles();

                    if (input.value && index < digits.length - 1) {
                        digits[index + 1].focus();
                        digits[index + 1].select();
                    }

                    submitIfComplete();
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !input.value && index > 0) {
                        e.preventDefault();
                        digits[index - 1].focus();
                        digits[index - 1].select();
                    }

                    if (e.key === 'ArrowLeft' && index > 0) {
                        e.preventDefault();
                        digits[index - 1].focus();
                    }

                    if (e.key === 'ArrowRight' && index < digits.length - 1) {
                        e.preventDefault();
                        digits[index + 1].focus();
                    }
                });

                input.addEventListener('paste', function(e) {
                    const text = (e.clipboardData || window.clipboardData).getData('text') || '';
                    if (!text) return;

                    e.preventDefault();
                    clearErrorStyles();

                    const chars = text.replace(/\D/g, '').split('').slice(0, codeLength);
                    chars.forEach(function(ch, offset) {
                        const target = digits[index + offset];
                        if (target) {
                            target.value = ch;
                        }
                    });

                    const next = Math.min(index + chars.length, digits.length - 1);
                    if (digits[next]) {
                        digits[next].focus();
                        digits[next].select();
                    }

                    submitIfComplete();
                });
            });

            setTimeout(function() {
                if (digits[0]) {
                    digits[0].focus();
                }
            }, 80);

            document.querySelectorAll('.resend-countdown').forEach(function(el) {
                const until = el.getAttribute('data-until');
                if (!until) return;

                const target = new Date(until).getTime();
                const interval = setInterval(function() {
                    const seconds = Math.max(0, Math.ceil((target - Date.now()) / 1000));
                    el.textContent = seconds;

                    if (seconds <= 0) {
                        clearInterval(interval);
                        const form = document.getElementById('resend-form');
                        if (form) {
                            const button = form.querySelector('button');
                            if (button) {
                                button.disabled = false;
                                button.style.cursor = 'pointer';
                                button.style.opacity = '1';
                            }
                        }

                        const note = el.parentElement;
                        if (note) {
                            note.style.display = 'none';
                        }
                    }
                }, 1000);
            });
        })();
    </script>
</x-authentication::layouts.master>
