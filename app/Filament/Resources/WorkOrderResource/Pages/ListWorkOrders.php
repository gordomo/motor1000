<?php

namespace App\Filament\Resources\WorkOrderResource\Pages;

use App\Filament\Pages\WorkOrdersBoard;
use App\Filament\Resources\WorkOrderResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kanban')
                ->label('Ver tablero Kanban')
                ->icon('heroicon-o-view-columns')
                ->color('gray')
                ->url(WorkOrdersBoard::getUrl()),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas'),
            'received' => Tab::make('Recibido')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'received')),
            'diagnosis' => Tab::make('Diagnóstico')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'diagnosis')),
            'waiting_parts' => Tab::make('Esperando piezas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'waiting_parts')),
            'repairing' => Tab::make('En reparación')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'repairing')),
            'completed' => Tab::make('Completado')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed')),
            'delivered' => Tab::make('Entregado')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'delivered')),
        ];
    }
}
