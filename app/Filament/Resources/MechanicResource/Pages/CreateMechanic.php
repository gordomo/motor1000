<?php

namespace App\Filament\Resources\MechanicResource\Pages;

use App\Filament\Resources\MechanicResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMechanic extends CreateRecord
{
    protected static string $resource = MechanicResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = \App\Support\CurrentTenant::id();

        return $data;
    }
}
