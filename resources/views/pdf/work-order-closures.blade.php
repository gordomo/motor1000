<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Órdenes cerradas</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 20px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 16px 0 6px; color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b7280; font-size: 10px; }
        .kpis td { padding: 8px 10px; background: #f9fafb; border: 1px solid #e5e7eb; text-align: center; }
        .kpi-value { font-size: 20px; font-weight: bold; }
        .data-table th { background: #1e3a5f; color: #fff; padding: 5px 8px; font-size: 10px; text-align: left; }
        .data-table td { padding: 4px 8px; border-bottom: 1px solid #f3f4f6; }
        .data-table tr:nth-child(even) { background: #f9fafb; }
        .text-right { text-align: right; }
        .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

<h1>{{ $tenant?->name ?? 'Taller' }}</h1>
<div class="muted">
    Órdenes de trabajo cerradas · {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
</div>

<h2>Resumen del período</h2>
<table class="kpis">
    <tr>
        <td>
            <div class="muted">Total cerradas</div>
            <div class="kpi-value">{{ $resumen['total'] }}</div>
        </td>
        <td>
            <div class="muted">Promedio por día</div>
            <div class="kpi-value">{{ $resumen['por_dia'] }}</div>
        </td>
        <td>
            <div class="muted">Promedio por semana</div>
            <div class="kpi-value">{{ $resumen['por_semana'] }}</div>
        </td>
        <td>
            <div class="muted">Promedio por mes</div>
            <div class="kpi-value">{{ $resumen['por_mes'] }}</div>
        </td>
    </tr>
</table>

@if(count($porMes))
<h2>Por mes</h2>
<table class="data-table">
    <thead><tr><th>Mes</th><th class="text-right" style="width:20%">Órdenes cerradas</th></tr></thead>
    <tbody>
        @foreach($porMes as $fila)
        <tr>
            <td>{{ $fila['mes'] }}</td>
            <td class="text-right">{{ $fila['total'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@if(count($porDia))
<h2>Por día</h2>
<table class="data-table">
    <thead><tr><th>Día</th><th class="text-right" style="width:20%">Órdenes cerradas</th></tr></thead>
    <tbody>
        @foreach($porDia as $fila)
        <tr>
            <td>{{ $fila['dia']->format('d/m/Y') }}</td>
            <td class="text-right">{{ $fila['total'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p class="muted">No se cerraron órdenes en el período seleccionado.</p>
@endif

<div class="footer">
    {{ $tenant?->name ?? 'Taller' }} · Informe generado el {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
