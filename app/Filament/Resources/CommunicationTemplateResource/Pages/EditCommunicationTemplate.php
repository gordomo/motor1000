<?php

namespace App\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\CommunicationTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommunicationTemplate extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = CommunicationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
