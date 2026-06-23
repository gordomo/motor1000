<?php

namespace App\Support;

use App\Models\Tenant;

class CurrentTenant
{
    /**
     * Flag de reentrancia: resolver auth()->user() dispara una consulta sobre el
     * modelo User (que tiene TenantScope) y vuelve a llamar a get(). Sin este
     * freno la resolución recursa infinitamente y agota la memoria — sobre todo
     * para super-admins, que al no tener tenant nunca dejan el resultado fijado.
     */
    protected static bool $resolving = false;

    public static function get(): ?Tenant
    {
        if (app()->bound('current.tenant')) {
            return app('current.tenant');
        }

        // Llamada reentrante durante la resolución del usuario: cortamos acá.
        // (No se puede usar un sentinel null en el container porque bound() usa
        // isset(), que da false para null.)
        if (self::$resolving) {
            return null;
        }

        self::$resolving = true;

        try {
            $tenant = auth()->user()?->tenant;
        } finally {
            self::$resolving = false;
        }

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
