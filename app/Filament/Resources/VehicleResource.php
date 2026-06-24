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
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

    public static function getModelLabel(): string
    {
        return __('Vehículo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Vehículos');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('customer_id')
                    ->label(__('Propietario'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('license_plate')
                    ->label(__('Patente'))
                    ->required()
                    ->maxLength(10)
                    // Falla #F: evitar patentes duplicadas dentro del mismo taller.
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : $state)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule) =>
                            $rule->where('tenant_id', \App\Support\CurrentTenant::id()),
                    )
                    ->validationMessages(['unique' => __('Ya existe un vehículo con esta patente en el taller.')])
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                Forms\Components\TextInput::make('brand')->label(__('Marca'))->required(),
                Forms\Components\TextInput::make('model')->label(__('Modelo'))->required(),
                Forms\Components\TextInput::make('year')->label(__('Año'))->numeric()->required(),
                Forms\Components\TextInput::make('color')->label(__('Color')),
                Forms\Components\TextInput::make('vin')->label(__('Chasis (VIN)')),
                Forms\Components\TextInput::make('mileage')->label(__('KM actual'))->numeric()->default(0),
                Forms\Components\TextInput::make('engine')->label(__('Motor')),
                Forms\Components\Select::make('fuel_type')
                    ->label(__('Combustible'))
                    ->options([
                        'gasoline' => __('Gasolina'), 'ethanol' => __('Etanol'), 'flex' => __('Flex'),
                        'diesel'   => __('Diesel'), 'electric' => __('Eléctrico'), 'hybrid' => __('Híbrido'),
                    ])->default('flex'),
                Forms\Components\Select::make('transmission')
                    ->label(__('Transmisión'))
                    ->options(['manual' => __('Manual'), 'automatic' => __('Automática'), 'cvt' => __('CVT')])
                    ->default('manual'),
                Forms\Components\Textarea::make('notes')->label(__('Observaciones'))->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('license_plate')->label(__('Patente'))->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('brand')->label(__('Marca'))->sortable(),
                Tables\Columns\TextColumn::make('model')->label(__('Modelo')),
                Tables\Columns\TextColumn::make('year')->label(__('Año')),
                Tables\Columns\TextColumn::make('customer.name')->label(__('Propietario'))->searchable(),
                Tables\Columns\TextColumn::make('mileage')->label(__('KM'))->numeric()->sortable(),
                Tables\Columns\TextColumn::make('last_service_at')->label(__('Último servicio'))->date('d/m/Y')->placeholder(__('Nunca')),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('qr_card')
                    ->label(__('Ficha QR'))
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->url(fn (Vehicle $record): string => route('vehicles.qr-card', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('license_plate');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Perfil del vehículo'))
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('display_name')->label(__('Unidad'))->weight('bold')->columnSpan(2),
                    Infolists\Components\TextEntry::make('customer.name')->label(__('Propietario')),
                    Infolists\Components\TextEntry::make('mileage')->label(__('Kilometraje'))->numeric(),
                    Infolists\Components\TextEntry::make('fuel_type')->label(__('Combustible'))->badge(),
                    Infolists\Components\TextEntry::make('transmission')->label(__('Transmisión'))->badge(),
                    Infolists\Components\TextEntry::make('last_service_at')->label(__('Último servicio'))->dateTime('d/m/Y')->placeholder(__('Sin registro')),
                    Infolists\Components\TextEntry::make('vin')->label(__('VIN'))->columnSpan(2)->placeholder(__('No informado')),
                ]),
            Infolists\Components\Section::make(__('Historial operativo'))
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('work_orders_count')
                        ->label(__('Órdenes de servicio'))
                        ->state(fn (Vehicle $record): int => $record->workOrders()->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('appointments_count')
                        ->label(__('Citas'))
                        ->state(fn (Vehicle $record): int => $record->appointments()->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('pending_reminders_count')
                        ->label(__('Recordatorios'))
                        ->state(fn (Vehicle $record): int => $record->reminders()->where('status', 'pending')->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('notes')->label(__('Notas técnicas'))->columnSpan(3)->placeholder(__('Sin observaciones')),
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
