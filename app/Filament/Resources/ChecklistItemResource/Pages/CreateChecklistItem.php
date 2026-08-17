<?php

namespace App\Filament\Resources\ChecklistItemResource\Pages;

use App\Filament\Resources\ChecklistItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChecklistItem extends CreateRecord
{
    protected static string $resource = ChecklistItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = \App\Support\CurrentTenant::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
