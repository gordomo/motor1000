<?php

namespace App\Filament\Resources\ReminderResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\ReminderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReminder extends CreateRecord
{
    use ConBotonVolver;

    protected static string $resource = ReminderResource::class;

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
