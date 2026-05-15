<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';
    protected static ?string $title = 'Vehículos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('license_plate')->label('Patente')->required(),
            Forms\Components\TextInput::make('brand')->label('Marca')->required(),
            Forms\Components\TextInput::make('model')->label('Modelo')->required(),
            Forms\Components\TextInput::make('year')->label('Año')->numeric()->required(),
            Forms\Components\TextInput::make('color')->label('Color'),
            Forms\Components\TextInput::make('vin')->label('Chasis'),
            Forms\Components\TextInput::make('mileage')->label('Kilometraje actual')->numeric(),
            Forms\Components\Select::make('fuel_type')
                ->label('Combustible')
                ->options([
                    'gasoline' => 'Gasolina',
                    'ethanol'  => 'Etanol',
                    'flex'     => 'Flex',
                    'diesel'   => 'Diésel',
                    'electric' => 'Eléctrico',
                    'hybrid'   => 'Híbrido',
                ])
                ->default('flex'),
            Forms\Components\Select::make('transmission')
                ->label('Transmisión')
                ->options([
                    'manual'    => 'Manual',
                    'automatic' => 'Automático',
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
                        $data['tenant_id'] = app('current.tenant')->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
