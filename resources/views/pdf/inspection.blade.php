<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Revisión {{ $inspection->code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 20px; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 13px; margin: 14px 0 6px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        .header-grid td { vertical-align: top; }

        .info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; margin-bottom: 10px; }
        .info-box td { padding: 2px 8px 2px 0; }
        .label { color: #6b7280; font-size: 10px; font-weight: bold; text-transform: uppercase; }

        .checklist-table th { background: #1e3a5f; color: #fff; padding: 5px 8px; font-size: 10px; text-align: left; }
        .checklist-table td { padding: 4px 8px; border-bottom: 1px solid #f3f4f6; }
        .checklist-table tr:nth-child(even) { background: #f9fafb; }
        .estado-BIEN    { color: #065f46; font-weight: bold; }
        .estado-REGULAR { color: #92400e; font-weight: bold; }
        .estado-MAL     { color: #991b1b; font-weight: bold; }
        .aclaracion-cell { font-style: italic; color: #374151; font-size: 10px; }

        .notes-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; }
        .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

{{-- ── Encabezado ─────────────────────────────────────────────────────────── --}}
<table class="header-grid" style="margin-bottom:16px; border-bottom:2px solid #1e3a5f; padding-bottom:12px;">
    <tr>
        <td style="width:65%">
            @php
                $logoFile = $inspection->tenant?->logo_path ? public_path('storage/' . $inspection->tenant->logo_path) : null;
            @endphp
            @if($logoFile && file_exists($logoFile))
                <img src="{{ $logoFile }}" alt="{{ $inspection->tenant->name }}"
                     style="max-height:60px; max-width:160px; margin-bottom:6px;">
            @else
                <h1>{{ $inspection->tenant?->name ?? 'Taller' }}</h1>
            @endif
            <div style="color:#6b7280; font-size:10px;">
                {{ $inspection->tenant?->phone ?? '' }}
                @if($inspection->tenant?->email) &nbsp;·&nbsp; {{ $inspection->tenant->email }} @endif
            </div>
        </td>
        <td style="text-align:right;">
            <div class="label">Revisión</div>
            <div style="font-size:18px; font-weight:bold;">{{ $inspection->code }}</div>
            <div style="color:#6b7280; font-size:10px; margin-top:4px;">
                {{ $inspection->created_at?->format('d/m/Y') }}
            </div>
        </td>
    </tr>
</table>

{{-- ── Cliente y Vehículo ─────────────────────────────────────────────────── --}}
<table style="margin-bottom:10px;">
    <tr>
        <td style="width:50%; padding-right:10px;">
            <div class="info-box">
                <div class="label" style="margin-bottom:6px;">Cliente</div>
                <table>
                    {{-- ?-> porque Customer usa SoftDeletes y la relación puede ser null --}}
                    <tr><td class="label">Nombre</td><td>{{ $inspection->customer?->name ?? '—' }}</td></tr>
                    @if($inspection->customer?->phone)
                    <tr><td class="label">Teléfono</td><td>{{ $inspection->customer->phone }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
        <td style="width:50%;">
            <div class="info-box">
                <div class="label" style="margin-bottom:6px;">Vehículo</div>
                <table>
                    @if($inspection->vehicle)
                    <tr><td class="label">Patente</td><td><strong>{{ $inspection->vehicle->license_plate }}</strong></td></tr>
                    <tr><td class="label">Marca/Modelo</td><td>{{ $inspection->vehicle->brand }} {{ $inspection->vehicle->model }}</td></tr>
                    <tr><td class="label">Año</td><td>{{ $inspection->vehicle->year }}</td></tr>
                    <tr><td class="label">Kilometraje</td><td>{{ number_format((int) ($inspection->mileage ?: $inspection->vehicle->mileage), 0, ',', '.') }} km</td></tr>
                    @else
                    <tr><td colspan="2">Sin vehículo asociado</td></tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ── Checklist ──────────────────────────────────────────────────────────── --}}
@php
    // Se imprimen todos los puntos, revisados o no: la revisión en blanco sirve
    // como planilla para completar a mano en el taller.
    $puntos = collect($inspection->checklist ?? []);
@endphp
@if($puntos->isNotEmpty())
<h2>Check List de Revisión</h2>
<table class="checklist-table" style="margin-bottom:14px;">
    <thead>
        <tr>
            <th style="width:40%">Categoría / Ítem</th>
            <th style="width:14%; text-align:center;">Estado</th>
            <th>Observación</th>
        </tr>
    </thead>
    <tbody>
        @foreach($puntos as $punto)
        <tr>
            <td>
                <span style="color:#6b7280; font-size:10px;">{{ $punto['categoria'] ?? '' }}</span><br>
                {{ $punto['nombre_item'] ?? '' }}
            </td>
            <td style="text-align:center;" class="estado-{{ $punto['estado'] ?? '' }}">
                {{ $punto['estado'] ?? '—' }}
            </td>
            <td class="aclaracion-cell">
                {{ $punto['aclaracion'] ?? '' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── Notas ──────────────────────────────────────────────────────────────── --}}
@if(filled($inspection->notes))
<h2>Notas</h2>
<div class="notes-box">{!! nl2br(e($inspection->notes)) !!}</div>
@endif

{{-- ── Firmas ─────────────────────────────────────────────────────────────── --}}
<table style="margin-top:36px;">
    <tr>
        <td style="width:45%; border-top:1px solid #9ca3af; padding-top:4px; text-align:center; font-size:10px; color:#6b7280;">
            Firma del responsable
        </td>
        <td style="width:10%"></td>
        <td style="width:45%; border-top:1px solid #9ca3af; padding-top:4px; text-align:center; font-size:10px; color:#6b7280;">
            Firma del cliente
        </td>
    </tr>
</table>

<div class="footer">
    {{ $inspection->tenant?->name ?? 'Taller' }}
    @if($inspection->tenant?->phone) &nbsp;·&nbsp; {{ $inspection->tenant->phone }} @endif
    &nbsp;·&nbsp; Revisión {{ $inspection->code }} · {{ $inspection->created_at?->format('d/m/Y') }}
</div>

</body>
</html>
