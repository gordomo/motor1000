<?php

namespace App\Filament\Resources\MechanicResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\MechanicResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMechanic extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = MechanicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
