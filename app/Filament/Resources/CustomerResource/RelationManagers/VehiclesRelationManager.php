<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Vehículos');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('license_plate')->label(__('Patente'))->required(),
            Forms\Components\TextInput::make('brand')->label(__('Marca'))->required(),
            Forms\Components\TextInput::make('model')->label(__('Modelo'))->required(),
            Forms\Components\TextInput::make('year')->label(__('Año'))->numeric()->required(),
            Forms\Components\TextInput::make('color')->label(__('Color')),
            Forms\Components\TextInput::make('vin')->label(__('Chasis')),
            Forms\Components\TextInput::make('mileage')->label(__('Kilometraje actual'))->numeric(),
            Forms\Components\Select::make('fuel_type')
                ->label(__('Combustible'))
                ->options([
                    'gasoline' => __('Gasolina'),
                    'ethanol'  => __('Etanol'),
                    'flex'     => 'Flex',
                    'diesel'   => __('Diésel'),
                    'electric' => __('Eléctrico'),
                    'hybrid'   => __('Híbrido'),
                ])
                ->default('flex'),
            Forms\Components\Select::make('transmission')
                ->label(__('Transmisión'))
                ->options([
                    'manual'    => 'Manual',
                    'automatic' => __('Automático'),
                    'cvt'       => 'CVT',
                ])
                ->default('manual'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('license_plate')->label('Patente')->weight('bold'),
                Tables\Columns\TextColumn::make('brand')->label('Marca'),
                Tables\Columns\TextColumn::make('model')->label('Modelo'),
                Tables\Columns\TextColumn::make('year')->label('Año'),
                Tables\Columns\TextColumn::make('mileage')->label('Kilometraje')->numeric(),
                Tables\Columns\TextColumn::make('last_service_at')->label('Último servicio')->date('d/m/Y')->placeholder('Nunca'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = \App\Support\CurrentTenant::id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
