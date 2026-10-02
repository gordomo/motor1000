<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Pantallas que ve solo el administrador.
 *
 * El cliente definió (2026-10) que el comercial ve solo Órdenes, Citas y
 * calendario, Clientes y vehículos, Presupuestos, Plantillas, Para hoy y los
 * números del taller. El mecánico, solo su tablero. El resto (Revisiones,
 * Inventario, Facturas, Recordatorios, Tareas, Mecánicos) queda para el admin.
 *
 * Saca el recurso del menú y niega el acceso directo por URL.
 */
trait SoloAdministrador
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
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

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
