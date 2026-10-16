<?php

namespace App\Filament\Resources\WorkOrderResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\WorkOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * discount y payment_status son NOT NULL: vaciar el campo para "volverlo a 0"
     * mandaba un NULL explícito y el guardado daba error 500 (igual que al crear).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['discount'] = $data['discount'] ?? 0;
        $data['payment_status'] = $data['payment_status'] ?? 'pending';

        return $data;
    }
}
