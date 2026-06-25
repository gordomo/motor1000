<?php

namespace App\Filament\Resources;

use App\Enums\CustomerStatus;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('CRM');
    }

    public static function getModelLabel(): string
    {
        return __('Cliente');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Clientes');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Información personal'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Nombre Completo'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->label(__('Estado'))
                        ->options(CustomerStatus::class)
                        ->default('active')
                        ->required(),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('Teléfono'))
                        ->tel()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('whatsapp')
                        ->label(__('WhatsApp'))
                        ->tel()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('email')
                        ->label(__('E-mail'))
                        ->email()
                        ->maxLength(255),
                    Forms\Components\DatePicker::make('birthday')
                        ->label(__('Cumpleaños')),
                    Forms\Components\TextInput::make('document')
                        ->label(__('Documento fiscal'))
                        ->maxLength(20),
                    Forms\Components\Select::make('document_type')
                        ->label(__('Tipo de documento'))
                        ->options(['cpf' => __('Documento personal'), 'cnpj' => __('Documento fiscal')])
                        ->default('cpf'),
                ]),
            Forms\Components\Section::make(__('Dirección'))
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('address')
                        ->label(__('Dirección'))
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('city')
                        ->label(__('Ciudad')),
                    Forms\Components\TextInput::make('state')
                        ->label(__('Estado'))
                        ->maxLength(2),
                    Forms\Components\TextInput::make('zip')
                        ->label(__('Código postal'))
                        ->maxLength(10),
                ]),
            Forms\Components\Section::make(__('Observaciones'))
                ->collapsed()
                ->schema([
                    Forms\Components\TagsInput::make('tags')
                        ->label(__('Etiquetas'))
                        ->placeholder(__('Agregar etiqueta')),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('Notas'))
                        ->rows(4),
                    Forms\Components\Toggle::make('whatsapp_opted_in')
                        ->label(__('Acepta WhatsApp'))
                        ->default(true),
                    Forms\Components\Toggle::make('email_opted_in')
                        ->label(__('Acepta correo'))
                        ->default(true),
                ]),

            Forms\Components\Section::make(__('Vehículo asociado (carga rápida)'))
                ->description(__('Opcional: se crea junto con el cliente en una sola operación.'))
                ->visibleOn('create')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('vehicle.license_plate')
                        ->label(__('Patente / Matrícula'))
                        ->maxLength(10)
                        ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                    Forms\Components\TextInput::make('vehicle.brand')
                        ->label(__('Marca')),
                    Forms\Components\TextInput::make('vehicle.model')
                        ->label(__('Modelo')),
                    Forms\Components\TextInput::make('vehicle.year')
                        ->label(__('Año'))
                        ->numeric(),
                    Forms\Components\TextInput::make('vehicle.vin')
                        ->label(__('Número de chasis (VIN)')),
                    Forms\Components\TextInput::make('vehicle.mileage')
                        ->label(__('Kilometraje actual'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Select::make('vehicle.fuel_type')
                        ->label(__('Combustible'))
                        ->options([
                            'gasoline' => __('Gasolina'),
                            'ethanol' => __('Etanol'),
                            'flex' => 'Flex',
                            'diesel' => 'Diesel',
                            'electric' => __('Eléctrico'),
                            'hybrid' => __('Híbrido'),
                        ])
                        ->default('flex'),
                    Forms\Components\Select::make('vehicle.transmission')
                        ->label(__('Transmisión'))
                        ->options([
                            'manual' => 'Manual',
                            'automatic' => __('Automática'),
                            'cvt' => 'CVT',
                        ])
                        ->default('manual'),
                    Forms\Components\Textarea::make('vehicle.notes')
                        ->label(__('Observaciones técnicas'))
                        ->rows(2)
                        ->columnSpan(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('Teléfono'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicles_count')
                    ->label(__('Vehículos'))
                    ->counts('vehicles')
                    ->badge()
                    ->color('info'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('Estado'))
                    ->colors([
                        'success' => 'active',
                        'danger'  => 'inactive',
                        'warning' => 'vip',
                        'info'    => 'prospect',
                    ]),
                Tables\Columns\TextColumn::make('last_visit_at')
                    ->label(__('Última visita'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->placeholder(__('Nunca')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Registro'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Estado'))
                    ->options(CustomerStatus::class),
                Tables\Filters\Filter::make('inactive')
                    ->label(__('Inactivos (6+ meses)'))
                    ->query(fn(Builder $q) => $q
                        ->where(fn($q) => $q
                            ->whereNull('last_visit_at')
                            ->orWhere('last_visit_at', '<', now()->subMonths(6))
                        )
                    ),
                Tables\Filters\Filter::make('birthday_today')
                    ->label(__('Cumpleañeros de hoy'))
                    ->query(fn(Builder $q) => $q->whereRaw(
                        "DATE_FORMAT(birthday, '%m-%d') = ?",
                        [now()->format('m-d')]
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('new_work_order')
                    ->label(__('Nueva OS'))
                    ->icon('heroicon-o-wrench')
                    ->color('warning')
                    ->url(fn(Customer $record) => WorkOrderResource::getUrl('create', ['customer_id' => $record->id])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Resumen del cliente'))
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('vehicles_count')
                        ->label(__('Vehículos'))
                        ->state(fn (Customer $record): int => $record->vehicles()->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('work_orders_count')
                        ->label(__('Órdenes de servicio'))
                        ->state(fn (Customer $record): int => $record->workOrders()->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('pending_reminders_count')
                        ->label(__('Recordatorios pendientes'))
                        ->state(fn (Customer $record): int => $record->reminders()->where('status', 'pending')->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('health_state')
                        ->label(__('Salud de relación'))
                        ->badge()
                        ->state(function (Customer $record): string {
                            if (! $record->last_visit_at) {
                                return 'En riesgo';
                            }

                            return $record->last_visit_at->lt(now()->subMonths(6)) ? 'En riesgo' : 'Activa';
                        })
                        ->color(fn (string $state): string => $state === 'Activa' ? 'success' : 'danger'),
                ]),
            Infolists\Components\Section::make(__('Cliente'))
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label(__('Nombre')),
                    Infolists\Components\TextEntry::make('phone')->label(__('Teléfono')),
                    Infolists\Components\TextEntry::make('email')->label(__('E-mail')),
                    Infolists\Components\TextEntry::make('status')
                        ->label(__('Estado'))
                        ->badge(),
                    Infolists\Components\TextEntry::make('last_visit_at')
                        ->label(__('Última Visita'))
                        ->dateTime('d/m/Y')
                        ->placeholder(__('Nunca')),
                    Infolists\Components\TextEntry::make('birthday')
                        ->label(__('Cumpleaños'))
                        ->date('d/m'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VehiclesRelationManager::class,
            RelationManagers\WorkOrdersRelationManager::class,
            RelationManagers\RemindersRelationManager::class,
            RelationManagers\CommunicationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view'   => Pages\ViewCustomer::route('/{record}'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
