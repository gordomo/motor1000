<?php

namespace App\Filament\Resources;

use App\Enums\ReminderType;
use App\Filament\Resources\ReminderResource\Pages;
use App\Models\Reminder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReminderResource extends Resource
{
    protected static ?string $model = Reminder::class;
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?string $modelLabel = 'Recordatorio';
    protected static ?string $pluralModelLabel = 'Recordatorios';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return (string) Reminder::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return Reminder::where('status', 'pending')
            ->where('due_at', '<', now())
            ->exists()
            ? 'danger'
            : 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'name')
                    ->searchable()->preload()->required(),
                Forms\Components\Select::make('vehicle_id')
                    ->label('Vehículo')
                    ->relationship('vehicle', 'license_plate')
                    ->searchable()->preload(),
                Forms\Components\Select::make('type')
                    ->label('Tipo')
                    ->options(ReminderType::class)
                    ->required(),
                Forms\Components\Select::make('trigger_type')
                    ->label('Tipo de disparador')
                    ->options(['date' => 'Data', 'mileage' => 'KM', 'months_since_last' => 'Meses desde el último servicio'])
                    ->default('date')->required()->reactive(),
                Forms\Components\TextInput::make('title')->label('Título')->required(),
                Forms\Components\DateTimePicker::make('due_at')
                    ->label('Fecha de vencimiento')
                    ->visible(fn(Forms\Get $get) => $get('trigger_type') === 'date'),
                Forms\Components\TextInput::make('due_mileage')
                    ->label('KM de Vencimiento')
                    ->numeric()
                    ->visible(fn(Forms\Get $get) => $get('trigger_type') === 'mileage'),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options(['pending' => 'Pendiente', 'sent' => 'Enviado', 'dismissed' => 'Descartado', 'completed' => 'Completado'])
                    ->default('pending'),
                Forms\Components\Textarea::make('description')->label('Descripción')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('vehicle.license_plate')->label('Vehículo')->placeholder('—'),
                Tables\Columns\BadgeColumn::make('type')->label('Tipo'),
                Tables\Columns\TextColumn::make('title')->label('Recordatorio'),
                Tables\Columns\TextColumn::make('due_at')->label('Vencimiento')->dateTime('d/m/Y')->sortable(),
                Tables\Columns\BadgeColumn::make('status')->label('Estado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pendiente', 'sent' => 'Enviado', 'dismissed' => 'Descartado', 'completed' => 'Completado']),
                Tables\Filters\SelectFilter::make('type')->options(ReminderType::class),
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidos')
                    ->query(fn($q) => $q->where('due_at', '<', now())->where('status', 'pending')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('due_at');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReminders::route('/'),
            'create' => Pages\CreateReminder::route('/create'),
            'edit'   => Pages\EditReminder::route('/{record}/edit'),
        ];
    }
}
