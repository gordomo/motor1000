<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
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
            'all'      => Tab::make('Todos'),
            'active'   => Tab::make('Activos')->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'active')),
            'vip'      => Tab::make('VIP')->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'vip')),
            'inactive' => Tab::make('Inactivos')
                ->modifyQueryUsing(fn(Builder $q) => $q
                    ->where(fn($q) => $q
                        ->whereNull('last_visit_at')
                        ->orWhere('last_visit_at', '<', now()->subMonths(6))
                    )
                )
                ->badge(fn() => \App\Models\Customer::where(
                    fn($q) => $q->whereNull('last_visit_at')
                        ->orWhere('last_visit_at', '<', now()->subMonths(6))
                )->count()),
        ];
    }
}
