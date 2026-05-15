<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';
    protected static ?string $title = 'Órdenes de Servicio';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('Número')->weight('bold'),
                Tables\Columns\TextColumn::make('vehicle.license_plate')->label('Vehículo'),
                Tables\Columns\BadgeColumn::make('status')->label('Estado'),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('ARS'),
                Tables\Columns\TextColumn::make('created_at')->label('Data')->date('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver OS')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => \App\Filament\Resources\WorkOrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
