<?php

namespace App\Filament\Resources;

use App\Enums\CustomerStatus;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
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
    protected static ?string $navigationGroup = 'CRM';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Información personal')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre Completo')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options(CustomerStatus::class)
                        ->default('active')
                        ->required(),
                    Forms\Components\TextInput::make('phone')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('whatsapp')
                        ->label('WhatsApp')
                        ->tel()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\DatePicker::make('birthday')
                        ->label('Cumpleaños'),
                    Forms\Components\TextInput::make('document')
                        ->label('Documento fiscal')
                        ->maxLength(20),
                    Forms\Components\Select::make('document_type')
                        ->label('Tipo de documento')
                        ->options(['cpf' => 'Documento personal', 'cnpj' => 'Documento fiscal'])
                        ->default('cpf'),
                ]),
            Forms\Components\Section::make('Dirección')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('address')
                        ->label('Dirección')
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('city')
                        ->label('Ciudad'),
                    Forms\Components\TextInput::make('state')
                        ->label('Estado')
                        ->maxLength(2),
                    Forms\Components\TextInput::make('zip')
                        ->label('Código postal')
                        ->maxLength(10),
                ]),
            Forms\Components\Section::make('Observaciones')
                ->collapsed()
                ->schema([
                    Forms\Components\TagsInput::make('tags')
                        ->label('Etiquetas')
                        ->placeholder('Agregar etiqueta'),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(4),
                    Forms\Components\Toggle::make('whatsapp_opted_in')
                        ->label('Acepta WhatsApp')
                        ->default(true),
                    Forms\Components\Toggle::make('email_opted_in')
                        ->label('Acepta correo')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicles_count')
                    ->label('Vehículos')
                    ->counts('vehicles')
                    ->badge()
                    ->color('info'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'success' => 'active',
                        'danger'  => 'inactive',
                        'warning' => 'vip',
                        'info'    => 'prospect',
                    ]),
                Tables\Columns\TextColumn::make('last_visit_at')
                    ->label('Última visita')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->placeholder('Nunca'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registro')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(CustomerStatus::class),
                Tables\Filters\Filter::make('inactive')
                    ->label('Inactivos (6+ meses)')
                    ->query(fn(Builder $q) => $q
                        ->where(fn($q) => $q
                            ->whereNull('last_visit_at')
                            ->orWhere('last_visit_at', '<', now()->subMonths(6))
                        )
                    ),
                Tables\Filters\Filter::make('birthday_today')
                    ->label('Cumpleañeros de hoy')
                    ->query(fn(Builder $q) => $q->whereRaw(
                        "DATE_FORMAT(birthday, '%m-%d') = ?",
                        [now()->format('m-d')]
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('new_work_order')
                    ->label('Nueva OS')
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
            Infolists\Components\Section::make('Resumen del cliente')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('vehicles_count')
                        ->label('Vehículos')
                        ->state(fn (Customer $record): int => $record->vehicles()->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('work_orders_count')
                        ->label('Órdenes de servicio')
                        ->state(fn (Customer $record): int => $record->workOrders()->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('pending_reminders_count')
                        ->label('Recordatorios pendientes')
                        ->state(fn (Customer $record): int => $record->reminders()->where('status', 'pending')->count())
                        ->badge(),
                    Infolists\Components\TextEntry::make('health_state')
                        ->label('Salud de relación')
                        ->badge()
                        ->state(function (Customer $record): string {
                            if (! $record->last_visit_at) {
                                return 'En riesgo';
                            }

                            return $record->last_visit_at->lt(now()->subMonths(6)) ? 'En riesgo' : 'Activa';
                        })
                        ->color(fn (string $state): string => $state === 'Activa' ? 'success' : 'danger'),
                ]),
            Infolists\Components\Section::make('Cliente')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('Nombre'),
                    Infolists\Components\TextEntry::make('phone')->label('Teléfono'),
                    Infolists\Components\TextEntry::make('email')->label('E-mail'),
                    Infolists\Components\TextEntry::make('status')
                        ->label('Estado')
                        ->badge(),
                    Infolists\Components\TextEntry::make('last_visit_at')
                        ->label('Última Visita')
                        ->dateTime('d/m/Y')
                        ->placeholder('Nunca'),
                    Infolists\Components\TextEntry::make('birthday')
                        ->label('Cumpleaños')
                        ->date('d/m'),
                ]),
        ]);
    }

    public static function getRelationManagers(): array
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
