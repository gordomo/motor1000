<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PublicBranchController extends Controller
{
    /**
     * Lista las sucursales bookeables de la marca (resueltas por el middleware
     * a partir de la API key). El front muestra el selector de sucursal (o lo
     * saltea si hay una sola).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Collection<int, Tenant> $branches */
        $branches = $request->attributes->get('brand_branches', collect());

        return response()->json([
            'ok' => true,
            'branches' => $branches->map(fn (Tenant $t) => [
                'id'        => $t->id,
                'nombre'    => $t->name,
                'direccion' => $t->address,
                'ciudad'    => $t->city,
                'whatsapp'  => $t->whatsapp,
                'timezone'  => $t->timezone,
            ])->values(),
        ]);
    }
}
