<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\QuoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuote extends EditRecord
{
    use ConBotonVolver;

    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
