<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $invoice->number }}</title>
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

        .box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .box td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            vertical-align: top;
        }

        .totals {
            width: 360px;
            margin-left: auto;
            margin-top: 12px;
            border-collapse: collapse;
        }

        .totals td {
            border: 1px solid #d1d5db;
            padding: 8px;
        }

        .right {
            text-align: right;
        }

        .total-row td {
            font-weight: 700;
            background: #f3f4f6;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            font-size: 11px;
            text-transform: uppercase;
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
                    @if(! empty($invoice->tenant->logo))
                        <img class="logo" src="{{ $invoice->tenant->logo }}" alt="Logo">
                    @endif
                    <p class="title">{{ $invoice->tenant->name ?? 'Taller' }}</p>
                    <p class="muted">Factura {{ $invoice->number }} | Generada el {{ now()->format('d/m/Y H:i') }}</p>
                </td>
                <td class="right">
                    <strong>Información de facturación</strong><br>
                    {{ $invoice->tenant->email ?? '-' }}<br>
                    {{ $invoice->tenant->phone ?? '-' }}<br>
                    {{ trim(($invoice->tenant->address ?? '') . ', ' . ($invoice->tenant->city ?? '') . ' - ' . ($invoice->tenant->state ?? '')) ?: '-' }}<br>
                    {{ $invoice->tenant->zip ?? '' }} {{ $invoice->tenant->country ?? '' }}
                </td>
            </tr>
        </table>
    </div>

    <table class="box">
        <tr>
            <td>
                <strong>Cliente</strong><br>
                {{ $invoice->customer->name ?? '-' }}<br>
                {{ $invoice->customer->email ?? '-' }}<br>
                {{ $invoice->customer->phone ?? '-' }}
            </td>
            <td>
                <strong>Orden de servicio vinculada</strong><br>
                {{ $invoice->workOrder?->number ?? '-' }}
            </td>
            <td>
                <strong>Estado</strong><br>
                <span class="badge">{{ strtoupper((string) $invoice->status) }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <strong>Fecha de vencimiento</strong><br>
                {{ $invoice->due_at?->format('d/m/Y H:i') ?? '-' }}
            </td>
            <td>
                <strong>Pagada el</strong><br>
                {{ $invoice->paid_at?->format('d/m/Y H:i') ?? '-' }}
            </td>
            <td>
                <strong>Método de pago</strong><br>
                {{ $invoice->payment_method ?? '-' }}
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <strong>Notas</strong><br>
                {{ $invoice->notes ?: '-' }}
            </td>
        </tr>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="right">$ {{ number_format((float) $invoice->subtotal, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Impuestos</td>
            <td class="right">$ {{ number_format((float) $invoice->tax, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Descuento</td>
            <td class="right">$ {{ number_format((float) $invoice->discount, 2, ',', '.') }}</td>
        </tr>
        <tr class="total-row">
            <td>Total</td>
            <td class="right">$ {{ number_format((float) $invoice->total, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        {{ $invoice->tenant->name ?? 'Taller' }} | Factura generada por Motor1000
    </div>
</body>
</html>
