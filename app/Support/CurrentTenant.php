<?php

namespace App\Support;

use App\Models\Tenant;

class CurrentTenant
{
    public static function get(): ?Tenant
    {
        if (app()->bound('current.tenant')) {
            return app('current.tenant');
        }

        $tenant = auth()->user()?->tenant;

        if (! $tenant || ! $tenant->is_active) {
            return null;
        }

        app()->instance('current.tenant', $tenant);

        return $tenant;
    }

    public static function id(): ?int
    {
        return self::get()?->id;
    }
}
