<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\InventoryItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
