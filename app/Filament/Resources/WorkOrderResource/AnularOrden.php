<?php

namespace App\Filament\Resources\WorkOrderResource;

use App\Actions\WorkOrder\CancelWorkOrderAction;
use App\Filament\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\MountableAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnularOrden
{
    public static function configure(MountableAction $action): MountableAction
    {
        return $action
            ->label(__('Anular'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (WorkOrder $record): bool => Gate::allows('cancel', $record))
            ->modalHeading(__('Anular orden de trabajo'))
            ->modalDescription(__('La orden y su número se conservan. Si tiene cobros o stock consumido, primero hay que revisar esos movimientos.'))
            ->form([
                Textarea::make('reason')->label(__('Motivo de anulación'))->required()->maxLength(2000),
            ])
            ->action(function (WorkOrder $record, array $data, $livewire, MountableAction $action): void {
                try {
                    app(CancelWorkOrderAction::class)->execute($record, $data['reason']);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    $action->halt();
                }

                Notification::make()->success()->title(__('Orden anulada'))->send();
                $livewire->redirect(WorkOrderResource::getUrl('view', ['record' => $record]));
            });
    }
}
