<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\QuoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewQuote extends ViewRecord
{
    use ConBotonVolver;

    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\EditAction::make(),
        ];
    }
}
