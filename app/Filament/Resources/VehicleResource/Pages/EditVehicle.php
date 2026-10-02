<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\VehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
