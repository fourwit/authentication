@php
    $useHostLayout = (bool) config('authentication.use_host_layout', false);
    $hostLayout = config('authentication.host_layout', 'layouts.app');
    $codeLength = (int) config('authentication.otp.length', 6);
    $user = auth()->user();
    $email = $user?->email ?? 'your email';
@endphp

@if($useHostLayout && view()->exists($hostLayout))
    @extends($hostLayout)
    @section('content')
        <div class="max-w-md mx-auto py-10">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-semibold tracking-tight">Verify your email</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Enter the {{ $codeLength }}-digit code sent to <span class="font-medium text-slate-900 dark:text-slate-100">{{ $email }}</span>.
                </p>
            </div>

            @if (session('status'))
                <div class="mb-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            <form id="code-form" method="POST" action="{{ route('authentication.verify-email.code') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="channel" value="email">
                <input type="hidden" name="code" id="hidden-code" value="">

                <div class="flex justify-center gap-2 sm:gap-3">
                    @for ($i = 0; $i < $codeLength; $i++)
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            autocomplete="one-time-code"
                            class="verification-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-2xl font-semibold border-2 border-slate-300 rounded-2xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition dark:bg-slate-950 dark:border-slate-700 dark:text-white"
                            data-index="{{ $i }}"
                        >
                    @endfor
                </div>

                {{-- No submit button - auto-submits when all digits are filled --}}
            </form>

            <div class="mt-6 text-center">
                @php
                    $allowedAt = $resendAllowedAt ?? null;
                    $cdSecs = $cooldownSeconds ?? 60;
                    $isCarbon = $allowedAt instanceof \Illuminate\Support\Carbon;
                    $allowedCarbon = $isCarbon ? $allowedAt : ($allowedAt ? \Illuminate\Support\Carbon::parse($allowedAt) : null);
                    $disabled = $allowedCarbon && now()->lt($allowedCarbon);
                    $untilIso = $allowedCarbon ? $allowedCarbon->toIso8601String() : null;
                    $initialSecs = $disabled && $allowedCarbon ? max(0, (int) now()->diffInSeconds($allowedCarbon)) : $cdSecs;
                @endphp
                <form method="POST" action="{{ route('authentication.verify-email.resend') }}" class="inline" id="resend-form">
                    @csrf
                    <input type="hidden" name="channel" value="email">
                    <button type="submit" {{ $disabled ? 'disabled' : '' }}
                            class="text-sm text-indigo-600 hover:text-indigo-500 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:no-underline">
                        Resend code
                    </button>
                </form>
                @if($disabled)
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 resend-countdown-note">
                        You can resend in <span class="resend-countdown" data-until="{{ $untilIso }}">{{ $initialSecs }}</span>s
                    </div>
                @endif
            </div>
        </div>

        <script>
            (function() {
                const form = document.getElementById('code-form');
                const hiddenCode = document.getElementById('hidden-code');
                const digits = Array.from(document.querySelectorAll('.verification-digit'));
                const codeLength = {{ $codeLength }};

                if (!form || digits.length === 0) return;

                function getCode() {
                    return digits.map(d => (d.value || '').trim()).join('');
                }

                function updateHidden() {
                    if (hiddenCode) hiddenCode.value = getCode();
                }

                function submitIfComplete() {
                    updateHidden();
                    const code = getCode();
                    if (code.length === codeLength) {
                        setTimeout(() => {
                            form.submit();
                        }, 60);
                    }
                }

                digits.forEach((input, index) => {
                    // Sanitize to single digit
                    input.addEventListener('input', () => {
                        input.value = input.value.replace(/[^0-9]/g, '').slice(0, 1);
                        if (input.value && index < digits.length - 1) {
                            digits[index + 1].focus();
                            digits[index + 1].select();
                        }
                        submitIfComplete();
                    });

                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Backspace') {
                            if (!input.value && index > 0) {
                                e.preventDefault();
                                digits[index - 1].focus();
                                digits[index - 1].select();
                            }
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

                    // Paste support (e.g. user pastes the full code)
                    input.addEventListener('paste', (e) => {
                        const text = (e.clipboardData || window.clipboardData).getData('text') || '';
                        if (!text) return;
                        e.preventDefault();

                        const chars = text.replace(/\D/g, '').split('').slice(0, codeLength);
                        chars.forEach((ch, i) => {
                            const target = digits[index + i];
                            if (target) target.value = ch;
                        });

                        const next = Math.min(index + chars.length, digits.length - 1);
                        if (digits[next]) {
                            digits[next].focus();
                            digits[next].select();
                        }
                        submitIfComplete();
                    });
                });

                // Focus first box on load
                setTimeout(() => {
                    if (digits[0]) digits[0].focus();
                }, 80);
            })();
        </script>
    @endsection
@else
    <x-authentication::layouts.master title="Verify your email">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-semibold tracking-tight">Verify your email</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                Enter the {{ $codeLength }}-digit code sent to <span class="font-medium text-slate-900 dark:text-slate-100">{{ $email }}</span>.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <form id="code-form" method="POST" action="{{ route('authentication.verify-email.code') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="channel" value="email">
            <input type="hidden" name="code" id="hidden-code" value="">

            <div class="flex justify-center gap-2 sm:gap-3">
                @for ($i = 0; $i < $codeLength; $i++)
                    <input
                        type="text"
                        inputmode="numeric"
                        maxlength="1"
                        autocomplete="one-time-code"
                        class="verification-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-2xl font-semibold border-2 border-slate-300 rounded-2xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition dark:bg-slate-950 dark:border-slate-700 dark:text-white"
                        data-index="{{ $i }}"
                    >
                @endfor
            </div>

            {{-- No submit button - auto-submits when all digits are filled --}}
        </form>

        <div class="mt-6 text-center">
            <form method="POST" action="{{ route('authentication.verify-email.resend') }}" class="inline" id="resend-form">
                @csrf
                <input type="hidden" name="channel" value="email">
                <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-500 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300">
                    Resend code
                </button>
            </form>
        </div>

        <script>
            (function() {
                const form = document.getElementById('code-form');
                const hiddenCode = document.getElementById('hidden-code');
                const digits = Array.from(document.querySelectorAll('.verification-digit'));
                const codeLength = {{ $codeLength }};

                if (!form || digits.length === 0) return;

                function getCode() {
                    return digits.map(d => (d.value || '').trim()).join('');
                }

                function updateHidden() {
                    if (hiddenCode) hiddenCode.value = getCode();
                }

                function submitIfComplete() {
                    updateHidden();
                    const code = getCode();
                    if (code.length === codeLength) {
                        setTimeout(() => {
                            form.submit();
                        }, 60);
                    }
                }

                digits.forEach((input, index) => {
                    input.addEventListener('input', () => {
                        input.value = input.value.replace(/[^0-9]/g, '').slice(0, 1);
                        if (input.value && index < digits.length - 1) {
                            digits[index + 1].focus();
                            digits[index + 1].select();
                        }
                        submitIfComplete();
                    });

                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Backspace') {
                            if (!input.value && index > 0) {
                                e.preventDefault();
                                digits[index - 1].focus();
                                digits[index - 1].select();
                            }
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

                    input.addEventListener('paste', (e) => {
                        const text = (e.clipboardData || window.clipboardData).getData('text') || '';
                        if (!text) return;
                        e.preventDefault();

                        const chars = text.replace(/\D/g, '').split('').slice(0, codeLength);
                        chars.forEach((ch, i) => {
                            const target = digits[index + i];
                            if (target) target.value = ch;
                        });

                        const next = Math.min(index + chars.length, digits.length - 1);
                        if (digits[next]) {
                            digits[next].focus();
                            digits[next].select();
                        }
                        submitIfComplete();
                    });
                });

                setTimeout(() => {
                    if (digits[0]) digits[0].focus();
                }, 80);
            })();
        </script>
    </x-authentication::layouts.master>
@endif
