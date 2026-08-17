<?php

namespace App\Filament\Resources\InspectionResource\Pages;

use App\Filament\Resources\InspectionResource;
use App\Models\Inspection;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInspection extends EditRecord
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label(__('Descargar PDF'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (Inspection $record): string => route('inspections.pdf', $record))
                ->openUrlInNewTab(),
            Actions\Action::make('imprimir')
                ->label(__('Imprimir'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (Inspection $record): string => route('inspections.pdf.stream', $record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
