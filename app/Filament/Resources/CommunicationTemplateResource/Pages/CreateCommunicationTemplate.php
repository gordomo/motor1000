<?php

namespace App\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Filament\Resources\CommunicationTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunicationTemplate extends CreateRecord
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = app('current.tenant')->id;

        return $data;
    }
}
