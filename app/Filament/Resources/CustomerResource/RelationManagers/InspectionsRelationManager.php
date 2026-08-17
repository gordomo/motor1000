<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Inspection;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de revisiones del cliente (pedido 17: "que me quede guardado en el
 * historial como lo demás"). Solo lectura: las revisiones se cargan y editan
 * desde su propio módulo.
 */
class InspectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'inspections';

    protected static ?string $title = 'Revisiones';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Número'))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Fecha'))
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('vehicle.license_plate')
                    ->label(__('Patente'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('mileage')
                    ->label(__('KM'))
                    ->numeric()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('checked_count')
                    ->label(__('Puntos revisados'))
                    ->badge()
                    ->color('info')
                    ->state(fn (Inspection $record): int => count($record->checkedItems())),
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Inspection $record): string => route('inspections.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading(__('Sin revisiones'));
    }
}
