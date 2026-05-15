<?php

namespace App\Filament\Resources\MechanicResource\Pages;

use App\Filament\Resources\MechanicResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMechanic extends CreateRecord
{
    protected static string $resource = MechanicResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = app('current.tenant')->id;

        return $data;
    }
}
