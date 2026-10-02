<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\AppointmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppointment extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
