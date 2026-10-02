<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\InventoryItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    use ConBotonVolver;

    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = \App\Support\CurrentTenant::id();

        return $data;
    }
}
