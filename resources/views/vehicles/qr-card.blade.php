<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ficha QR — {{ $vehicle->license_plate }}</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
            .card { page-break-inside: avoid; }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 20px; }
        .page { max-width: 400px; width: 100%; }

        /* Botones */
        .actions { display: flex; gap: 10px; margin-bottom: 16px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
        .btn-print { background: #1e3a5f; color: #fff; }
        .btn-back  { background: #e5e7eb; color: #374151; }

        /* Tarjeta */
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,.12); padding: 28px; text-align: center; }
        .card-header { border-bottom: 2px solid #f3f4f6; padding-bottom: 16px; margin-bottom: 20px; }
        .logo { max-height: 50px; margin-bottom: 8px; }
        .taller-name { font-size: 15px; font-weight: 700; color: #1e3a5f; }
        .taller-sub  { font-size: 11px; color: #9ca3af; margin-top: 2px; }

        .plate { display: inline-block; background: #1e3a5f; color: #fff; font-size: 28px; font-weight: 800; letter-spacing: .12em; padding: 8px 24px; border-radius: 8px; margin-bottom: 8px; }
        .vehicle-name { font-size: 15px; font-weight: 600; color: #374151; margin-bottom: 4px; }
        .vehicle-detail { font-size: 12px; color: #9ca3af; margin-bottom: 20px; }

        /* QR */
        .qr-container { margin: 0 auto 16px; }
        .qr-container img { width: 180px; height: 180px; }
        .qr-placeholder { width: 180px; height: 180px; margin: 0 auto; background: #f3f4f6; border: 2px dashed #d1d5db; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .qr-placeholder span { font-size: 11px; color: #9ca3af; padding: 8px; text-align: center; }

        .qr-label { font-size: 12px; color: #6b7280; margin-bottom: 16px; }
        .url-box { background: #f3f4f6; border-radius: 6px; padding: 8px 12px; font-size: 10px; color: #6b7280; word-break: break-all; }

        .owner { border-top: 1px solid #f3f4f6; margin-top: 16px; padding-top: 12px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
<div class="page">

    <div class="actions no-print">
        <a href="javascript:window.print()" class="btn btn-print">
            🖨️ Imprimir ficha
        </a>
        <a href="javascript:history.back()" class="btn btn-back">
            ← Volver
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            @if($vehicle->tenant->logo_path)
                <img src="{{ asset('storage/' . $vehicle->tenant->logo_path) }}"
                     alt="{{ $vehicle->tenant->name }}" class="logo">
            @else
                <div class="taller-name">{{ $vehicle->tenant->name }}</div>
            @endif
            <div class="taller-sub">
                @if($vehicle->tenant->phone) {{ $vehicle->tenant->phone }} @endif
            </div>
        </div>

        <div class="plate">{{ $vehicle->license_plate }}</div>
        <div class="vehicle-name">{{ $vehicle->brand }} {{ $vehicle->model }}</div>
        <div class="vehicle-detail">Año {{ $vehicle->year }} · {{ number_format($vehicle->mileage, 0, ',', '.') }} km</div>

        <div class="qr-container">
            @if($qrSvg)
                <img src="data:image/svg+xml;base64,{{ $qrSvg }}" alt="QR Code">
            @else
                <div class="qr-placeholder">
                    <span>QR disponible al instalar simplesoftwareio/simple-qrcode</span>
                </div>
            @endif
        </div>

        <div class="qr-label">Escaneá el código QR para ver el historial del vehículo</div>
        <div class="url-box">{{ $publicUrl }}</div>

        <div class="owner">
            Propietario: <strong>{{ $vehicle->customer->name }}</strong>
        </div>
    </div>

</div>
</body>
</html>
