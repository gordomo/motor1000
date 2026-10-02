<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\VehicleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    use ConBotonVolver;

    protected static string $resource = VehicleResource::class;

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
