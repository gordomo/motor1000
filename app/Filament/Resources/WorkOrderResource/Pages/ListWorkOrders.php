<?php

namespace App\Filament\Resources\WorkOrderResource\Pages;

use App\Enums\WorkOrderStatus;
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
                ->label(__('Ver tablero Kanban'))
                ->icon('heroicon-o-view-columns')
                ->color('gray')
                ->url(WorkOrdersBoard::getUrl()),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        // Una pestaña por estado del enum: antes estaban hardcodeadas y quedaban
        // pestañas vacías al retirar un estado del flujo.
        $tabs = ['all' => Tab::make(__('Todas'))];

        foreach (WorkOrderStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
