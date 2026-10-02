<?php

namespace App\Filament\Resources\ReminderResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\ReminderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReminder extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = ReminderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
