<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Taller';
    protected static ?string $modelLabel = 'Cita';
    protected static ?string $pluralModelLabel = 'Citas';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return (string) Appointment::where('status', 'scheduled')
            ->whereDate('scheduled_at', today())
            ->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'name')
                    ->searchable()->preload()->required()
                    ->reactive()
                    ->afterStateUpdated(fn($set) => $set('vehicle_id', null)),
                Forms\Components\Select::make('vehicle_id')
                    ->label('Vehículo')
                    ->options(function (Forms\Get $get): array {
                        $customerId = $get('customer_id');

                        if (! $customerId) {
                            return [];
                        }

                        return \App\Models\Vehicle::query()
                            ->where('customer_id', $customerId)
                            ->orderBy('license_plate')
                            ->get()
                            ->mapWithKeys(fn ($vehicle) => [
                                $vehicle->id => $vehicle->display_name,
                            ])
                            ->toArray();
                    })
                    ->disabled(fn (Forms\Get $get): bool => blank($get('customer_id')))
                    ->helperText(fn (Forms\Get $get): ?string => blank($get('customer_id')) ? 'Selecciona primero un cliente.' : null)
                    ->searchable(),
                Forms\Components\Select::make('mechanic_id')
                    ->label('Mecánico')
                    ->relationship('mechanic', 'name')
                    ->searchable()->preload(),
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->default(fn (): string => request()->query('title', 'Servicio agendado')),
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->label('Fecha/Hora')
                    ->required()
                    ->default(fn (): ?string => request()->query('scheduled_at')),
                Forms\Components\TextInput::make('duration_minutes')
                    ->label('Duración (min)')
                    ->numeric()
                    ->default(fn (): int => max(15, (int) request()->query('duration_minutes', 60))),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'scheduled'   => 'Programada',
                        'confirmed'   => 'Confirmado',
                        'in_progress' => 'En progreso',
                        'completed'   => 'Completado',
                        'cancelled'   => 'Cancelado',
                        'no_show'     => 'No asistió',
                    ])->default('scheduled'),
                Forms\Components\Textarea::make('description')->label('Descripción')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')->label('Fecha/Hora')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('vehicle.license_plate')->label('Vehículo')->placeholder('—'),
                Tables\Columns\TextColumn::make('mechanic.name')->label('Mecánico')->placeholder('—'),
                Tables\Columns\TextColumn::make('title')->label('Servicio'),
                Tables\Columns\BadgeColumn::make('status')->label('Estado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['scheduled' => 'Programada', 'confirmed' => 'Confirmado', 'completed' => 'Completado', 'cancelled' => 'Cancelado']),
                Tables\Filters\Filter::make('today')
                    ->label('Hoy')
                    ->query(fn($q) => $q->whereDate('scheduled_at', today())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('scheduled_at');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit'   => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
