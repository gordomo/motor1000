<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\View\View;

class VehicleQrCardController extends Controller
{
    public function __invoke(Vehicle $vehicle): View
    {
        abort_unless(auth()->check(), 401);
        abort_unless($vehicle->tenant_id === auth()->user()->tenant_id, 403);

        $vehicle->load(['customer', 'tenant']);

        $publicUrl = route('vehicle.public', ['token' => $vehicle->public_token]);

        // Generamos el QR como SVG en base64 para embeber en el HTML
        $qrSvg = null;
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            $qrSvg = base64_encode(
                \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                    ->size(200)
                    ->generate($publicUrl)
            );
        }

        return view('vehicles.qr-card', compact('vehicle', 'publicUrl', 'qrSvg'));
    }
}
