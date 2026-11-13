<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Scopes\TenantScope;
use Illuminate\Http\Response;
use Illuminate\View\View;

class VehiclePublicController extends Controller
{
    public function __invoke(string $token): View|Response
    {
        // El token da acceso al historial público incluso si el navegador tiene
        // sesión de otro taller. El borrado lógico sigue aplicando.
        $vehicle = Vehicle::withoutGlobalScopes([TenantScope::class])
            ->select(['id', 'tenant_id', 'license_plate', 'brand', 'model', 'year'])
            ->where('public_token', $token)
            ->firstOrFail();

        // Lista explícita de datos públicos: no cargar al propietario, precios,
        // presupuestos, queja ni notas internas para esta respuesta.
        $vehicle->load([
            'tenant:id,name,logo_path,phone',
            'workOrders' => fn ($q) => $q->withoutGlobalScopes([TenantScope::class])
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('status', '!=', 'cancelled')
                ->select(['id', 'tenant_id', 'vehicle_id', 'created_at', 'work_performed'])
                ->orderByDesc('created_at')->orderByDesc('id'),
        ]);

        return view('public.vehicle', compact('vehicle'));
    }
}
