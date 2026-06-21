@php
    $reminder = $reminder ?? ($authenticationPasswordReminder ?? []);
    $show = (bool) data_get($reminder, 'show', false);
    $message = data_get($reminder, 'message', 'Your account does not have a password yet.');
    $actionLabel = data_get($reminder, 'action_label', 'Set password');
    $actionRoute = data_get($reminder, 'action_route');
@endphp

@if ($show)
    <div style="margin-bottom:16px; padding:12px 14px; border:1px solid #fecaca; border-radius:10px; background:#fef2f2; color:#991b1b; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <span>{{ $message }}</span>
        @if ($actionRoute)
            <a href="{{ $actionRoute }}" style="display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:0 14px; border-radius:999px; background:#fff; border:1px solid #fecaca; color:#991b1b; font-weight:600; text-decoration:none;">
                {{ $actionLabel }}
            </a>
        @endif
    </div>
@endif
