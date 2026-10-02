<?php

namespace App\Filament\Resources\InspectionResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\InspectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInspection extends CreateRecord
{
    use ConBotonVolver;

    protected static string $resource = InspectionResource::class;

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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
