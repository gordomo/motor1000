<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de Servicio {{ $workOrder->number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
            margin: 24px;
        }

        .header {
            margin-bottom: 18px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 12px;
        }

        .header-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .header-grid td {
            vertical-align: top;
        }

        .logo {
            max-width: 120px;
            max-height: 60px;
            margin-bottom: 6px;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }

        .muted {
            color: #6b7280;
            font-size: 11px;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .grid td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            vertical-align: top;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            margin: 16px 0 8px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        table.items th,
        table.items td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
        }

        table.items th {
            background: #f9fafb;
            font-weight: 700;
        }

        .right {
            text-align: right;
        }

        .totals {
            width: 320px;
            margin-left: auto;
            margin-top: 12px;
            border-collapse: collapse;
        }

        .totals td {
            border: 1px solid #d1d5db;
            padding: 8px;
        }

        .total-row td {
            font-weight: 700;
            background: #f3f4f6;
        }

        .footer {
            margin-top: 30px;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-grid">
            <tr>
                <td>
                    @if(! empty($workOrder->tenant->logo))
                        <img class="logo" src="{{ $workOrder->tenant->logo }}" alt="Logo">
                    @endif
                    <p class="title">{{ $workOrder->tenant->name ?? 'Taller' }}</p>
                    <p class="muted">Orden de Servicio {{ $workOrder->number }} | Generada el {{ now()->format('d/m/Y H:i') }}</p>
                </td>
                <td class="right">
                    <strong>Información del taller</strong><br>
                    {{ $workOrder->tenant->email ?? '-' }}<br>
                    {{ $workOrder->tenant->phone ?? '-' }}<br>
                    {{ trim(($workOrder->tenant->address ?? '') . ', ' . ($workOrder->tenant->city ?? '') . ' - ' . ($workOrder->tenant->state ?? '')) ?: '-' }}<br>
                    {{ $workOrder->tenant->zip ?? '' }} {{ $workOrder->tenant->country ?? '' }}
                </td>
            </tr>
        </table>
    </div>

    <table class="grid">
        <tr>
            <td>
                <strong>Cliente</strong><br>
                {{ $workOrder->customer->name ?? '-' }}<br>
                {{ $workOrder->customer->phone ?? '-' }}
            </td>
            <td>
                <strong>Vehículo</strong><br>
                {{ $workOrder->vehicle->display_name ?? '-' }}<br>
                VIN: {{ $workOrder->vehicle->vin ?? '-' }}
            </td>
            <td>
                <strong>Mecánico</strong><br>
                {{ $workOrder->mechanic->name ?? 'No asignado' }}<br>
                Prioridad: {{ strtoupper((string) $workOrder->priority) }}
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <strong>Queja</strong><br>
                {{ $workOrder->complaint ?? '-' }}
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <strong>Diagnóstico</strong><br>
                {{ $workOrder->diagnosis ?? '-' }}
            </td>
        </tr>
    </table>

    <p class="section-title">Ítems</p>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 14%;">Tipo</th>
                <th>Descripción</th>
                <th style="width: 12%;" class="right">Cant.</th>
                <th style="width: 18%;" class="right">Unidad</th>
                <th style="width: 18%;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($workOrder->items as $item)
                <tr>
                    <td>{{ ucfirst((string) $item->type) }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                    <td class="right">$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                    <td class="right">$ {{ number_format((float) $item->total, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="right">Sin ítems</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Mano de obra</td>
            <td class="right">$ {{ number_format((float) $workOrder->labor_cost, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Piezas</td>
            <td class="right">$ {{ number_format((float) $workOrder->parts_cost, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Descuento</td>
            <td class="right">$ {{ number_format((float) $workOrder->discount, 2, ',', '.') }}</td>
        </tr>
        <tr class="total-row">
            <td>Total</td>
            <td class="right">$ {{ number_format((float) $workOrder->total, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        {{ $workOrder->tenant->name ?? 'Taller' }} | Orden de servicio generada por Motor1000
    </div>
</body>
</html>
