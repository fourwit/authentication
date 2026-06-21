<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>
    @stack('authentication-head')
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f3f4f6; color: #111827; }
        .page { min-height: 100vh; display: grid; place-items: center; padding: 24px; background:
            radial-gradient(circle at top, rgba(99,102,241,0.08), transparent 35%),
            linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
        .card { width: 100%; max-width: 448px; background: rgba(255,255,255,0.98); border: 1px solid #e5e7eb; border-radius: 16px; padding: 28px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.10); backdrop-filter: blur(8px); }
        .brand { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 14px; background: #4f46e5; color: #fff; font-weight: 700; letter-spacing: 0; margin-bottom: 18px; }
        h1 { margin: 0 0 8px; font-size: 1.75rem; line-height: 1.1; letter-spacing: 0; }
        p { margin: 0 0 18px; color: #4b5563; line-height: 1.6; }
        label { display: block; margin: 0 0 6px; font-weight: 600; color: #374151; }
        input, button, textarea { font: inherit; }
        .field { margin-bottom: 14px; }
        .input { width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; box-sizing: border-box; background: #fff; color: #111827; transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease; }
        .input::placeholder { color: #9ca3af; }
        .input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
        .input.is-invalid { border-color: #dc2626; background: #fff5f5; box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.10); }
        .input.is-invalid:focus { border-color: #dc2626; box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.14); }
        .btn { display: inline-flex; align-items: center; justify-content: center; width: 100%; padding: 12px 14px; border: 0; border-radius: 10px; background: #111827; color: #fff; cursor: pointer; text-decoration: none; font-weight: 600; transition: transform 120ms ease, background-color 120ms ease, box-shadow 120ms ease; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn:hover { background: #0f172a; transform: translateY(-1px); }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .stack { display: grid; gap: 12px; }
        .row { display: flex; gap: 12px; flex-wrap: wrap; }
        .row > * { flex: 1 1 0; }
        .alert { padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; border: 1px solid transparent; }
        .alert-success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .errors { margin: 0; padding-left: 18px; color: #991b1b; }
        .field-error { margin-top: 6px; color: #dc2626; font-size: 0.875rem; }
        .meta { margin-top: 16px; font-size: 0.95rem; color: #6b7280; }
        .links { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        .link-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 120ms ease, border-color 120ms ease, color 120ms ease, transform 120ms ease, box-shadow 120ms ease;
        }
        .link-chip:hover {
            background: #f9fafb;
            border-color: #cbd5e1;
            color: #111827;
            transform: translateY(-1px);
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .link-chip.primary {
            border-color: #c7d2fe;
            background: #eef2ff;
            color: #3730a3;
        }
        .link-chip.primary:hover {
            background: #e0e7ff;
            border-color: #a5b4fc;
        }
        .link-chip.ghost { background: transparent; }
        .footer-note { margin-top: 20px; font-size: 0.92rem; color: #6b7280; }
        a { color: inherit; text-decoration: none; }
    </style>
</head>
<body>
<main class="page">
    <section class="card">
        @php $homeUrl = \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/'); @endphp
        <a href="{{ $homeUrl }}" class="brand" style="text-decoration: none; color: #fff; cursor: pointer;">
            {{ strtoupper(substr(config('app.name', 'A'), 0, 1)) }}
        </a>
        {{ $slot }}
    </section>
</main>
@stack('authentication-scripts')
</body>
</html>
