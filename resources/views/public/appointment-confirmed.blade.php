<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turno confirmado</title>
    <style>
        body { margin:0; background:#f4f4f5; font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif; color:#1f2937; }
        .wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; max-width:460px; width:100%; padding:36px 32px; text-align:center; box-shadow:0 1px 4px rgba(0,0,0,.05); }
        .check { width:64px; height:64px; border-radius:50%; background:#ecfdf5; color:#15803d; display:flex; align-items:center; justify-content:center; font-size:34px; margin:0 auto 16px; }
        h1 { font-size:22px; margin:0 0 8px; color:#111827; }
        p { font-size:15px; line-height:1.6; color:#4b5563; margin:0 0 6px; }
        .muted { color:#9ca3af; font-size:13px; margin-top:18px; }
        .brand { font-weight:700; color:#1e3a5f; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="check">✓</div>
            <h1>¡Turno confirmado!</h1>
            <p>Gracias. Tu turno quedó confirmado.</p>
            @if($appt->scheduled_at)
            <p><strong>{{ $appt->scheduled_at->format('d/m/Y') }} a las {{ $appt->scheduled_at->format('H:i') }} hs</strong></p>
            @endif
            <p class="muted">
                Te esperamos en <span class="brand">{{ $appt->tenant->name ?? 'el taller' }}</span>.
                @if($appt->tenant?->address) {{ $appt->tenant->address }}@endif
            </p>
        </div>
    </div>
</body>
</html>
