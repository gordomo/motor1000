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
            QuoteResource\AccionesAprobar::parcial(Actions\Action::make('aprobar_parcial')),
            QuoteResource\AccionesAprobar::total(Actions\Action::make('aprobar_total')),
            Actions\EditAction::make(),
        ];
    }
}
