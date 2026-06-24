<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Órdenes de Servicio');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label(__('Número'))->weight('bold'),
                Tables\Columns\TextColumn::make('vehicle.license_plate')->label(__('Vehículo')),
                Tables\Columns\BadgeColumn::make('status')->label(__('Estado')),
                Tables\Columns\TextColumn::make('total')->label(__('Total'))->money('ARS'),
                Tables\Columns\TextColumn::make('created_at')->label(__('Fecha'))->date('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('Ver OS'))
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => \App\Filament\Resources\WorkOrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
