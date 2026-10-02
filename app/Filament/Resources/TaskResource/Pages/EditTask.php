<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTask extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
