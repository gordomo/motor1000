<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Punto 7 del pedido del cliente: "usuario mecánico → solo ve tablero de órdenes".
 *
 * El mecánico entra al taller a trabajar, no a administrar: no necesita ver
 * facturación, presupuestos, inventario ni la agenda. Este trait saca el recurso
 * de su menú y le niega el acceso directo por URL.
 *
 * No toca a los demás roles: administrador y recepción siguen viendo todo lo que
 * veían. Las restricciones del rol comercial son otra discusión, pendiente de que
 * el cliente defina ese rol.
 */
trait HiddenFromMechanics
{
    public static function canViewAny(): bool
    {
        return ! static::esSoloMecanico();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /** Mecánico y nada más: si además es admin o recepción, ve todo. */
    protected static function esSoloMecanico(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('mechanic') && ! $user->hasAnyRole(['admin', 'receptionist']);
    }
}
