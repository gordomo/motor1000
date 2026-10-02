<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\DeleteAction::make()->label(__('Eliminar')),
        ];
    }
}
