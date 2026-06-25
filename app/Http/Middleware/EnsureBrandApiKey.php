<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida la API key pública de la marca (header X-Api-Key) y deja en el request
 * las sucursales bookeables de esa marca. Tenant = sucursal; la marca agrupa por
 * brand_slug. La key vive en una sucursal "primaria" de la marca.
 */
class EnsureBrandApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('X-Api-Key'));

        if ($key === '') {
            return response()->json(['ok' => false, 'message' => 'API key requerida.'], 401);
        }

        $owner = Tenant::query()->where('public_api_key', $key)->first();

        if (! $owner || ! $owner->brand_slug) {
            return response()->json(['ok' => false, 'message' => 'API key inválida.'], 401);
        }

        $branches = Tenant::query()
            ->where('brand_slug', $owner->brand_slug)
            ->where('accepts_online_booking', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $request->attributes->set('brand_slug', $owner->brand_slug);
        $request->attributes->set('brand_branches', $branches);

        return $next($request);
    }
}
