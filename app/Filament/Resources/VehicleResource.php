<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleResource\Pages;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Taller';
    protected static ?string $modelLabel = 'Vehículo';
    protected static ?string $pluralModelLabel = 'Vehículos';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('customer_id')
                    ->label('Propietario')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('license_plate')
                    ->label('Patente')
                    ->required()
                    ->maxLength(10)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                Forms\Components\TextInput::make('brand')->label('Marca')->required(),
                Forms\Components\TextInput::make('model')->label('Modelo')->required(),
                Forms\Components\TextInput::make('year')->label('Año')->numeric()->required(),
                Forms\Components\TextInput::make('color')->label('Color'),
                Forms\Components\TextInput::make('vin')->label('Chasis (VIN)'),
                Forms\Components\TextInput::make('mileage')->label('KM actual')->numeric()->default(0),
                Forms\Components\TextInput::make('engine')->label('Motor'),
                Forms\Components\Select::make('fuel_type')
                    ->label('Combustible')
                    ->options([
                        'gasoline' => 'Gasolina', 'ethanol' => 'Etanol', 'flex' => 'Flex',
                        'diesel'   => 'Diesel', 'electric' => 'Eléctrico', 'hybrid' => 'Híbrido',
                    ])->default('flex'),
                Forms\Components\Select::make('transmission')
                    ->label('Transmisión')
                    ->options(['manual' => 'Manual', 'automatic' => 'Automática', 'cvt' => 'CVT'])
                    ->default('manual'),
                Forms\Components\Textarea::make('notes')->label('Observaciones')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('license_plate')->label('Patente')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('brand')->label('Marca')->sortable(),
                Tables\Columns\TextColumn::make('model')->label('Modelo'),
                Tables\Columns\TextColumn::make('year')->label('Año'),
                Tables\Columns\TextColumn::make('customer.name')->label('Propietario')->searchable(),
                Tables\Columns\TextColumn::make('mileage')->label('KM')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('last_service_at')->label('Último servicio')->date('d/m/Y')->placeholder('Nunca'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('license_plate');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Perfil del vehículo')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('display_name')->label('Unidad')->weight('bold')->columnSpan(2),
                    Infolists\Components\TextEntry::make('customer.name')->label('Propietario'),
                    Infolists\Components\TextEntry::make('mileage')->label('Kilometraje')->numeric(),
                    Infolists\Components\TextEntry::make('fuel_type')->label('Combustible')->badge(),
                    Infolists\Components\TextEntry::make('transmission')->label('Transmisión')->badge(),
                    Infolists\Components\TextEntry::make('last_service_at')->label('Último servicio')->dateTime('d/m/Y')->placeholder('Sin registro'),
                    Infolists\Components\TextEntry::make('vin')->label('VIN')->columnSpan(2)->placeholder('No informado'),
                ]),
            Infolists\Components\Section::make('Historial operativo')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('work_orders_count')
                        ->label('Órdenes de servicio')
                        ->state(fn (Vehicle $record): int => $record->workOrders()->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('appointments_count')
                        ->label('Citas')
                        ->state(fn (Vehicle $record): int => $record->appointments()->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('pending_reminders_count')
                        ->label('Recordatorios')
                        ->state(fn (Vehicle $record): int => $record->reminders()->where('status', 'pending')->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('notes')->label('Notas técnicas')->columnSpan(3)->placeholder('Sin observaciones'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'view'   => Pages\ViewVehicle::route('/{record}'),
            'edit'   => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
