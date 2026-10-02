<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    use ConBotonVolver;

    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = \App\Support\CurrentTenant::id();
        $data['created_by'] = auth()->id();

        return $data;
    }
}
