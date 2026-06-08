<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $vehicle->display_name }} — {{ $vehicle->tenant->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111827; }

        .header { background: #1e3a5f; color: #fff; padding: 20px 16px; text-align: center; }
        .header img { max-height: 60px; margin-bottom: 8px; }
        .header h1 { font-size: 20px; font-weight: 700; }
        .header p  { font-size: 13px; opacity: .8; margin-top: 4px; }

        .container { max-width: 640px; margin: 0 auto; padding: 16px; }

        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 16px; margin-bottom: 14px; }
        .card-title { font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .info-item .lbl { font-size: 11px; color: #9ca3af; }
        .info-item .val { font-size: 14px; font-weight: 600; }

        .plate { display: inline-block; background: #1e3a5f; color: #fff; font-size: 22px; font-weight: 800;
                 letter-spacing: .1em; padding: 6px 18px; border-radius: 6px; margin-bottom: 12px; }

        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-gray    { background:#e5e7eb; color:#374151; }
        .badge-warning { background:#fef3c7; color:#92400e; }
        .badge-success { background:#d1fae5; color:#065f46; }
        .badge-danger  { background:#fee2e2; color:#991b1b; }
        .badge-info    { background:#dbeafe; color:#1e40af; }

        .list-item { padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .list-item:last-child { border-bottom: none; }
        .list-item .date { font-size: 11px; color: #9ca3af; }
        .list-item .title { font-size: 13px; font-weight: 600; }
        .list-item .desc { font-size: 12px; color: #6b7280; margin-top: 2px; }

        .empty { text-align: center; color: #9ca3af; font-size: 13px; padding: 16px 0; }

        .footer { text-align: center; color: #9ca3af; font-size: 12px; padding: 20px 0 40px; }
    </style>
</head>
<body>

{{-- ── Header ─────────────────────────────────────────────────────────────── --}}
<div class="header">
    @if($vehicle->tenant->logo_path)
        <img src="{{ asset('storage/' . $vehicle->tenant->logo_path) }}" alt="{{ $vehicle->tenant->name }}">
    @else
        <h1>{{ $vehicle->tenant->name }}</h1>
    @endif
    <p>Historial del vehículo</p>
</div>

<div class="container">

    {{-- ── Datos del vehículo ─────────────────────────────────────────────── --}}
    <div class="card" style="text-align:center;">
        <div class="plate">{{ $vehicle->license_plate }}</div>
        <div class="info-grid" style="text-align:left;">
            <div class="info-item"><div class="lbl">Marca</div><div class="val">{{ $vehicle->brand }}</div></div>
            <div class="info-item"><div class="lbl">Modelo</div><div class="val">{{ $vehicle->model }}</div></div>
            <div class="info-item"><div class="lbl">Año</div><div class="val">{{ $vehicle->year }}</div></div>
            <div class="info-item"><div class="lbl">Kilometraje</div><div class="val">{{ number_format($vehicle->mileage, 0, ',', '.') }} km</div></div>
            @if($vehicle->vin)
            <div class="info-item" style="grid-column:span 2"><div class="lbl">VIN / Chasis</div><div class="val">{{ $vehicle->vin }}</div></div>
            @endif
        </div>
    </div>

    {{-- ── Propietario ─────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-title">Propietario</div>
        <div class="info-grid">
            <div class="info-item"><div class="lbl">Nombre</div><div class="val">{{ $vehicle->customer->name }}</div></div>
            @if($vehicle->customer->phone)
            <div class="info-item"><div class="lbl">Teléfono</div><div class="val">{{ $vehicle->customer->phone }}</div></div>
            @endif
        </div>
    </div>

    {{-- ── Recordatorios pendientes ────────────────────────────────────────── --}}
    @if($vehicle->reminders->isNotEmpty())
    <div class="card">
        <div class="card-title">⚠️ Servicios Recomendados</div>
        @foreach($vehicle->reminders as $r)
        <div class="list-item">
            <div class="title">{{ $r->title }}</div>
            <div class="desc">Vence: {{ $r->due_at ? $r->due_at->format('d/m/Y') : '—' }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Presupuestos ────────────────────────────────────────────────────── --}}
    @if($vehicle->quotes->isNotEmpty())
    <div class="card">
        <div class="card-title">Presupuestos</div>
        @foreach($vehicle->quotes as $q)
        <div class="list-item">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div class="title">{{ $q->code }}</div>
                    <div class="date">{{ $q->created_at->format('d/m/Y') }}</div>
                    @if($q->detected_fault)
                    <div class="desc">{{ $q->detected_fault }}</div>
                    @endif
                </div>
                <div>
                    @php
                        $color = match($q->status->value) {
                            'draft'    => 'gray',
                            'sent'     => 'warning',
                            'accepted' => 'success',
                            'rejected' => 'danger',
                            default    => 'gray',
                        };
                    @endphp
                    <span class="badge badge-{{ $color }}">{{ $q->status->getLabel() }}</span>
                    @if($q->total > 0)
                    <div style="font-size:13px; font-weight:700; text-align:right; margin-top:4px;">
                        $ {{ number_format($q->total, 2, ',', '.') }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Historial de OTs ────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-title">Historial de Servicios</div>
        @forelse($vehicle->workOrders as $wo)
        <div class="list-item">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div class="title">{{ $wo->number }}</div>
                    <div class="date">{{ $wo->created_at->format('d/m/Y') }}</div>
                    @if($wo->complaint)
                    <div class="desc">{{ $wo->complaint }}</div>
                    @endif
                </div>
                @php
                    $woColor = match($wo->status->value) {
                        'received'      => 'gray',
                        'diagnosis'     => 'warning',
                        'waiting_parts' => 'warning',
                        'repairing'     => 'info',
                        'completed'     => 'success',
                        'delivered'     => 'success',
                        default         => 'gray',
                    };
                @endphp
                <span class="badge badge-{{ $woColor }}">{{ $wo->status->getLabel() }}</span>
            </div>
        </div>
        @empty
        <div class="empty">Sin historial de servicios aún.</div>
        @endforelse
    </div>

</div>

<div class="footer">
    {{ $vehicle->tenant->name }}
    @if($vehicle->tenant->phone) · {{ $vehicle->tenant->phone }} @endif
</div>

</body>
</html>
