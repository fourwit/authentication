@php
    $useHostLayout = (bool) config('authentication.use_host_layout', false);
    $hostLayout = config('authentication.host_layout', 'layouts.app');
    $codeLength = (int) config('authentication.otp.length', 6);
    $user = auth()->user();
    $verificationChannel = $verificationChannel ?? 'email';
    $verificationDestination = $verificationDestination ?? ($user?->email ?? 'your email');
    $verificationLabel = $verificationChannel === 'phone' ? 'phone number' : 'email';
@endphp

@if($useHostLayout && view()->exists($hostLayout))
    @extends($hostLayout)
    @section('content')
        <div class="max-w-md mx-auto py-10">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-semibold tracking-tight">Verify your account</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Enter the {{ $codeLength }}-digit code sent to your {{ $verificationLabel }} at <span class="font-medium text-slate-900 dark:text-slate-100">{{ $verificationDestination }}</span>.
                </p>
            </div>

            @if (session('status'))
                <div class="mb-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            <form id="code-form" method="POST" action="{{ route('authentication.verify-email.code') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="channel" value="{{ $verificationChannel }}">
                <input type="hidden" name="code" id="hidden-code" value="">

                <div class="flex justify-center gap-2 sm:gap-3">
                    @php $hasCodeError = $errors->has('code'); @endphp
                    @for ($i = 0; $i < $codeLength; $i++)
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            autocomplete="one-time-code"
                            class="verification-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-2xl font-semibold border-2 rounded-2xl outline-none transition dark:bg-slate-950 dark:text-white {{ $hasCodeError ? 'border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-200 dark:border-red-500' : 'border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-slate-700' }}"
                            data-index="{{ $i }}"
                        >
                    @endfor
                </div>

                @if ($errors->has('code'))
                    <div class="mt-3 text-center text-sm text-red-600 dark:text-red-400">
                        {{ $errors->first('code') }}
                    </div>
                @endif
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
                    <input type="hidden" name="channel" value="{{ $verificationChannel }}">
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

                // Clear red error styling on any user interaction (input or key) after an invalid code error.
                // This runs only if the page rendered with a code error (boxes are red + empty).
                @if ($errors->has('code'))
                const clearErrorStyles = () => {
                    digits.forEach(d => {
                        d.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-200', 'dark:border-red-500');
                        d.classList.add('border-slate-300', 'focus:border-indigo-500', 'focus:ring-indigo-200', 'dark:border-slate-700');
                    });
                };
                digits.forEach(input => {
                    input.addEventListener('input', clearErrorStyles, { once: true });
                    input.addEventListener('keydown', clearErrorStyles, { once: true });
                });
                @endif

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
                        setTimeout(() => form.submit(), 60);
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

                setTimeout(() => { if (digits[0]) digits[0].focus(); }, 80);

                // Resend cooldown countdown (if element present)
                document.querySelectorAll('.resend-countdown').forEach(function(el) {
                    var until = el.getAttribute('data-until');
                    if (!until) return;
                    var target = new Date(until).getTime();
                    var iv = setInterval(function() {
                        var now = Date.now();
                        var secs = Math.max(0, Math.ceil((target - now) / 1000));
                        el.textContent = secs;
                        if (secs <= 0) {
                            clearInterval(iv);
                            var form = el.closest('form') || document.getElementById('resend-form');
                            if (form) {
                                var btn = form.querySelector('button');
                                if (btn) btn.disabled = false;
                            }
                            // hide the countdown note (support both old parent and .resend-countdown-note)
                            var note = el.parentElement;
                            if (note && note.classList.contains('resend-countdown-note')) {
                                note.style.display = 'none';
                            } else if (note) {
                                note.style.display = 'none';
                            }
                            // also try by class in case
                            document.querySelectorAll('.resend-countdown-note').forEach(function(n) { n.style.display = 'none'; });
                        }
                    }, 1000);
                });
            })();
        </script>
    @endsection
@else
    <x-authentication::layouts.master title="Verify your account">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-semibold tracking-tight">Verify your account</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                Enter the {{ $codeLength }}-digit code sent to your {{ $verificationLabel }} at <span class="font-medium text-slate-900 dark:text-slate-100">{{ $verificationDestination }}</span>.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <form id="code-form" method="POST" action="{{ route('authentication.verify-email.code') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="channel" value="{{ $verificationChannel }}">
            <input type="hidden" name="code" id="hidden-code" value="">

            <div class="flex justify-center gap-2 sm:gap-3">
                @php $hasCodeError = $errors->has('code'); @endphp
                @for ($i = 0; $i < $codeLength; $i++)
                    <input
                        type="text"
                        inputmode="numeric"
                        maxlength="1"
                        autocomplete="one-time-code"
                        class="verification-digit w-11 h-12 sm:w-12 sm:h-14 text-center text-2xl font-semibold border-2 rounded-2xl outline-none transition dark:bg-slate-950 dark:text-white {{ $hasCodeError ? 'border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-200 dark:border-red-500' : 'border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-slate-700' }}"
                        data-index="{{ $i }}"
                    >
                @endfor
            </div>
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
                <input type="hidden" name="channel" value="{{ $verificationChannel }}">
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

        <script>
            (function() {
                const form = document.getElementById('code-form');
                const hiddenCode = document.getElementById('hidden-code');
                const digits = Array.from(document.querySelectorAll('.verification-digit'));
                const codeLength = {{ $codeLength }};

                if (!form || digits.length === 0) return;

                // Clear red error styling on any user interaction (input or key) after an invalid code error.
                // This runs only if the page rendered with a code error (boxes are red + empty).
                @if ($errors->has('code'))
                const clearErrorStyles = () => {
                    digits.forEach(d => {
                        d.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-200', 'dark:border-red-500');
                        d.classList.add('border-slate-300', 'focus:border-indigo-500', 'focus:ring-indigo-200', 'dark:border-slate-700');
                    });
                };
                digits.forEach(input => {
                    input.addEventListener('input', clearErrorStyles, { once: true });
                    input.addEventListener('keydown', clearErrorStyles, { once: true });
                });
                @endif

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
                        setTimeout(() => form.submit(), 60);
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

                setTimeout(() => { if (digits[0]) digits[0].focus(); }, 80);

                // Resend cooldown countdown (if element present)
                document.querySelectorAll('.resend-countdown').forEach(function(el) {
                    var until = el.getAttribute('data-until');
                    if (!until) return;
                    var target = new Date(until).getTime();
                    var iv = setInterval(function() {
                        var now = Date.now();
                        var secs = Math.max(0, Math.ceil((target - now) / 1000));
                        el.textContent = secs;
                        if (secs <= 0) {
                            clearInterval(iv);
                            var form = el.closest('form') || document.getElementById('resend-form');
                            if (form) {
                                var btn = form.querySelector('button');
                                if (btn) btn.disabled = false;
                            }
                            // hide the countdown note (support both old parent and .resend-countdown-note)
                            var note = el.parentElement;
                            if (note && note.classList.contains('resend-countdown-note')) {
                                note.style.display = 'none';
                            } else if (note) {
                                note.style.display = 'none';
                            }
                            // also try by class in case
                            document.querySelectorAll('.resend-countdown-note').forEach(function(n) { n.style.display = 'none'; });
                        }
                    }, 1000);
                });
            })();
        </script>
    </x-authentication::layouts.master>
@endif
