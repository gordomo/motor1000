<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Concerns\ConBotonVolver;
use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    use ConBotonVolver;

    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->volverAction(),
            Actions\Action::make('pdf')
                ->label(__('Descargar PDF'))
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => route('invoices.pdf', $this->record))
                ->openUrlInNewTab(),
            Actions\EditAction::make(),
        ];
    }
}
