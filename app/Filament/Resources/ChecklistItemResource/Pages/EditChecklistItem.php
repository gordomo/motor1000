<?php

namespace App\Filament\Resources\ChecklistItemResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\ChecklistItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChecklistItem extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = ChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
