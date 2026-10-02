<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    use ConBotonVolver;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\EditAction::make(),
        ];
    }
}
