<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            // OJO: el parámetro del closure TIENE que llamarse $query. Filament lo
            // inyecta por nombre (Tab::modifyQuery pasa ['query' => $query]); con
            // cualquier otro nombre cae al resolutor por tipo y arma un Builder sin
            // modelo, que rompe la pestaña. Las pestañas de este archivo usaban $q.
            'all'      => Tab::make('Todos'),
            // Pedido 9: clientes reales (ya trajeron el auto) vs. solo cargados.
            'con_ordenes' => Tab::make('Con trabajo hecho')
                ->modifyQueryUsing(fn (Builder $query) => $query->has('workOrders'))
                ->badge(fn () => Customer::query()->has('workOrders')->count()),
            'sin_ordenes' => Tab::make('Nunca vinieron')
                ->modifyQueryUsing(fn (Builder $query) => $query->doesntHave('workOrders'))
                ->badge(fn () => Customer::query()->doesntHave('workOrders')->count()),
            'active'   => Tab::make('Activos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active')),
            'vip'      => Tab::make('VIP')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'vip')),
            'inactive' => Tab::make('Inactivos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $sub) => $sub
                    ->whereNull('last_visit_at')
                    ->orWhere('last_visit_at', '<', now()->subMonths(6))
                ))
                ->badge(fn () => Customer::query()->where(fn (Builder $sub) => $sub
                    ->whereNull('last_visit_at')
                    ->orWhere('last_visit_at', '<', now()->subMonths(6))
                )->count()),
        ];
    }
}
