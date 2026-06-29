<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Presupuesto {{ $quote->code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 20px; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 13px; margin: 14px 0 6px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        .header-grid td { vertical-align: top; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-draft    { background: #e5e7eb; color: #374151; }
        .badge-sent     { background: #fef3c7; color: #92400e; }
        .badge-accepted { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }

        /* Sección de datos */
        .info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; margin-bottom: 10px; }
        .info-box td { padding: 2px 8px 2px 0; }
        .label { color: #6b7280; font-size: 10px; font-weight: bold; text-transform: uppercase; }

        /* Checklist */
        .checklist-table th { background: #1e3a5f; color: #fff; padding: 5px 8px; font-size: 10px; text-align: left; }
        .checklist-table td { padding: 4px 8px; border-bottom: 1px solid #f3f4f6; }
        .checklist-table tr:nth-child(even) { background: #f9fafb; }
        .estado-BIEN    { color: #065f46; font-weight: bold; }
        .estado-REGULAR { color: #92400e; font-weight: bold; }
        .estado-MAL     { color: #991b1b; font-weight: bold; }
        .aclaracion-cell { font-style: italic; color: #374151; font-size: 10px; }

        /* Items */
        .items-table th { background: #1e3a5f; color: #fff; padding: 5px 8px; font-size: 10px; text-align: left; }
        .items-table td { padding: 4px 8px; border-bottom: 1px solid #f3f4f6; }
        .items-table tr:nth-child(even) { background: #f9fafb; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; font-size: 13px; background: #f3f4f6; padding: 6px 8px; }

        /* Footer */
        .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

{{-- ── Encabezado ─────────────────────────────────────────────────────────── --}}
<table class="header-grid" style="margin-bottom:16px; border-bottom:2px solid #1e3a5f; padding-bottom:12px;">
    <tr>
        <td style="width:65%">
            @php
                $logoFile = $quote->tenant->logo_path ? public_path('storage/' . $quote->tenant->logo_path) : null;
            @endphp
            @if($logoFile && file_exists($logoFile))
                <img src="{{ $logoFile }}"
                     alt="{{ $quote->tenant->name }}" style="max-height:60px; max-width:160px; margin-bottom:6px;">
            @else
                <h1>{{ $quote->tenant->name }}</h1>
            @endif
            <div style="color:#6b7280; font-size:10px;">
                {{ $quote->tenant->phone ?? '' }}
                @if($quote->tenant->email) &nbsp;·&nbsp; {{ $quote->tenant->email }} @endif
            </div>
        </td>
        <td style="text-align:right;">
            <div style="font-size:18px; font-weight:bold; color:#1e3a5f;">PRESUPUESTO</div>
            <div style="font-size:22px; font-weight:bold;">{{ $quote->code }}</div>
            <div style="color:#6b7280; font-size:10px; margin-top:4px;">
                Fecha: {{ $quote->created_at->format('d/m/Y') }}
            </div>
            <div style="margin-top:6px;">
                <span class="badge badge-{{ $quote->status->value }}">
                    {{ $quote->status->getLabel() }}
                </span>
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
                    <tr><td class="label">Nombre</td><td>{{ $quote->customer->name }}</td></tr>
                    @if($quote->customer->phone)
                    <tr><td class="label">Teléfono</td><td>{{ $quote->customer->phone }}</td></tr>
                    @endif
                    @if($quote->customer->tax_id)
                    <tr><td class="label">{{ strtoupper($quote->customer->tax_id_type ?? 'DNI') }}</td><td>{{ $quote->customer->tax_id }}</td></tr>
                    @endif
                    @if($quote->customer->address)
                    <tr><td class="label">Dirección</td><td>{{ $quote->customer->address }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
        <td style="width:50%;">
            <div class="info-box">
                <div class="label" style="margin-bottom:6px;">Vehículo</div>
                <table>
                    @if($quote->vehicle)
                    <tr><td class="label">Patente</td><td><strong>{{ $quote->vehicle->license_plate }}</strong></td></tr>
                    <tr><td class="label">Marca/Modelo</td><td>{{ $quote->vehicle->brand }} {{ $quote->vehicle->model }}</td></tr>
                    <tr><td class="label">Año</td><td>{{ $quote->vehicle->year }}</td></tr>
                    @if($quote->vehicle->vin)
                    <tr><td class="label">VIN/Chasis</td><td>{{ $quote->vehicle->vin }}</td></tr>
                    @endif
                    <tr><td class="label">Kilometraje</td><td>{{ number_format((int) $quote->vehicle->mileage, 0, ',', '.') }} km</td></tr>
                    @else
                    <tr><td colspan="2">Sin vehículo asociado</td></tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ── Falla Detectada ─────────────────────────────────────────────────────── --}}
@if($quote->detected_fault)
<h2>Falla Detectada / Motivo de Ingreso</h2>
<div class="info-box" style="margin-bottom:14px;">
    {{ $quote->detected_fault }}
</div>
@endif

{{-- ── Checklist 20 puntos ─────────────────────────────────────────────────── --}}
@php
    $checklist = collect($quote->checklist ?? []);
    $checklistFilled = $checklist->filter(fn($p) => ! empty($p['estado']));
@endphp
@if($checklistFilled->isNotEmpty())
<h2>Check List de Inspección Visual</h2>
<table class="checklist-table" style="margin-bottom:14px;">
    <thead>
        <tr>
            <th style="width:30%">Categoría / Ítem</th>
            <th style="width:12%; text-align:center;">Estado</th>
            <th>Observación</th>
        </tr>
    </thead>
    <tbody>
        @foreach($checklistFilled as $punto)
        <tr>
            <td>
                <span style="color:#6b7280; font-size:10px;">{{ $punto['categoria'] }}</span><br>
                {{ $punto['nombre_item'] }}
            </td>
            <td style="text-align:center;" class="estado-{{ $punto['estado'] }}">
                {{ $punto['estado'] }}
            </td>
            <td class="aclaracion-cell">
                {{ $punto['aclaracion'] ?? '' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── Items / Cotización ───────────────────────────────────────────────────── --}}
@php $items = collect($quote->items ?? []); @endphp
@if($items->isNotEmpty())
<h2>Detalle de Cotización</h2>
<table class="items-table" style="margin-bottom:8px;">
    <thead>
        <tr>
            <th>Tipo</th>
            <th>Descripción</th>
            <th class="text-right" style="width:8%">Cant.</th>
            <th class="text-right" style="width:14%">P. Unitario</th>
            <th class="text-right" style="width:14%">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            <td>{{ match($item['tipo'] ?? '') { 'repuesto' => 'Repuesto', 'mano_de_obra' => 'Mano de obra', default => 'Otro' } }}</td>
            <td>{{ $item['descripcion'] ?? '' }}</td>
            <td class="text-right">{{ $item['cantidad'] ?? 1 }}</td>
            <td class="text-right">$ {{ number_format($item['precio_unitario'] ?? 0, 2, ',', '.') }}</td>
            <td class="text-right">$ {{ number_format($item['total'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        @if($quote->subtotal != $quote->total)
        <tr>
            <td colspan="4" class="text-right" style="padding:4px 8px; color:#6b7280;">Subtotal</td>
            <td class="text-right" style="padding:4px 8px;">$ {{ number_format($quote->subtotal, 2, ',', '.') }}</td>
        </tr>
        @if($quote->tax > 0)
        <tr>
            <td colspan="4" class="text-right" style="padding:4px 8px; color:#6b7280;">Impuestos</td>
            <td class="text-right" style="padding:4px 8px;">$ {{ number_format($quote->tax, 2, ',', '.') }}</td>
        </tr>
        @endif
        @if($quote->discount > 0)
        <tr>
            <td colspan="4" class="text-right" style="padding:4px 8px; color:#6b7280;">Descuento</td>
            <td class="text-right" style="padding:4px 8px;">- $ {{ number_format($quote->discount, 2, ',', '.') }}</td>
        </tr>
        @endif
        @endif
        <tr class="total-row">
            <td colspan="4" class="text-right">TOTAL</td>
            <td class="text-right">$ {{ number_format($quote->total, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endif

{{-- ── Notas ───────────────────────────────────────────────────────────────── --}}
@if($quote->notes)
<h2>Notas</h2>
<div class="info-box">{{ $quote->notes }}</div>
@endif

{{-- ── Footer ──────────────────────────────────────────────────────────────── --}}
<div class="footer">
    {{ $quote->tenant->name }}
    @if($quote->tenant->phone) &nbsp;·&nbsp; {{ $quote->tenant->phone }} @endif
    @if($quote->tenant->email) &nbsp;·&nbsp; {{ $quote->tenant->email }} @endif
    &nbsp;·&nbsp; Presupuesto válido por 30 días desde la fecha de emisión.
</div>

</body>
</html>
