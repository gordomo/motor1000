<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MechanicResource\Pages;
use App\Models\Mechanic;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MechanicResource extends Resource
{
    protected static ?string $model = Mechanic::class;

    protected static ?string $navigationIcon   = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup  = 'Configuraciones';
    protected static ?string $navigationLabel  = 'Mecánicos';
    protected static ?string $modelLabel       = 'Mecánico';
    protected static ?string $pluralModelLabel = 'Mecánicos';
    protected static ?int    $navigationSort   = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del mecánico')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->label('Nombre'),

                    Forms\Components\TextInput::make('specialty')
                        ->maxLength(255)
                        ->label('Especialidad'),

                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(20)
                        ->label('Teléfono'),

                    Forms\Components\TextInput::make('document')
                        ->maxLength(20)
                        ->label('Documento'),

                    Forms\Components\TextInput::make('hourly_rate')
                        ->numeric()
                        ->prefix('$')
                        ->label('Tarifa/hora'),

                    Forms\Components\Select::make('user_id')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->label('Usuario del sistema'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('Activo'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre'),

                Tables\Columns\TextColumn::make('specialty')
                    ->placeholder('—')
                    ->label('Especialidad'),

                Tables\Columns\TextColumn::make('phone')
                    ->placeholder('—')
                    ->label('Teléfono'),

                Tables\Columns\TextColumn::make('hourly_rate')
                    ->money('ARS')
                    ->placeholder('—')
                    ->label('Tarifa/hora'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Activo'),

                Tables\Columns\TextColumn::make('workOrders_count')
                    ->counts('workOrders')
                    ->label('OS totales'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMechanics::route('/'),
            'create' => Pages\CreateMechanic::route('/create'),
            'edit'   => Pages\EditMechanic::route('/{record}/edit'),
        ];
    }
}
