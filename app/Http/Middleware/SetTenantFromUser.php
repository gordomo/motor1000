<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            if ($user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);

                if ($tenant && $tenant->is_active) {
                    app()->instance('current.tenant', $tenant);

                    // Set timezone from tenant
                    config(['app.timezone' => $tenant->timezone]);
                    date_default_timezone_set($tenant->timezone);
                }
            }
        }

        return $next($request);
    }
}
