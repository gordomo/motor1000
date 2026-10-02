<?php

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Filament\Admin\Resources\TenantResource;
use App\Filament\Concerns\ConBotonVolver;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    use ConBotonVolver;

    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
