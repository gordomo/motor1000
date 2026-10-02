<?php

namespace App\Filament\Resources\MechanicResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\MechanicResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMechanic extends CreateRecord
{
    use ConBotonVolver;

    protected static string $resource = MechanicResource::class;

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
