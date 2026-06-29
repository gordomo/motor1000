<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Cita');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Citas');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

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
                    ->label(__('Cliente'))
                    ->relationship('customer', 'name')
                    ->searchable()->preload()->required()
                    ->reactive()
                    ->afterStateUpdated(fn($set) => $set('vehicle_id', null)),
                Forms\Components\Select::make('vehicle_id')
                    ->label(__('Vehículo'))
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
                    ->helperText(fn (Forms\Get $get): ?string => blank($get('customer_id')) ? __('Selecciona primero un cliente.') : null)
                    ->searchable(),
                Forms\Components\Select::make('mechanic_id')
                    ->label(__('Mecánico'))
                    ->relationship('mechanic', 'name')
                    ->searchable()->preload(),
                Forms\Components\TextInput::make('title')
                    ->label(__('Título'))
                    ->required()
                    ->default(fn (): string => request()->query('title', 'Servicio agendado')),
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->label(__('Fecha/Hora'))
                    ->required()
                    ->default(fn (): ?string => request()->query('scheduled_at')),
                Forms\Components\TextInput::make('duration_minutes')
                    ->label(__('Duración (min)'))
                    ->numeric()
                    ->default(fn (): int => max(15, (int) request()->query('duration_minutes', 60))),
                Forms\Components\Select::make('status')
                    ->label(__('Estado'))
                    ->options([
                        'scheduled'   => __('Programada'),
                        'confirmed'   => __('Confirmado'),
                        'in_progress' => __('En progreso'),
                        'completed'   => __('Completado'),
                        'cancelled'   => __('Cancelado'),
                        'no_show'     => __('No asistió'),
                    ])->default('scheduled'),
                Forms\Components\Textarea::make('description')->label(__('Descripción'))->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')->label(__('Fecha/Hora'))->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label(__('Cliente'))->searchable(),
                Tables\Columns\TextColumn::make('vehicle.license_plate')->label(__('Vehículo'))->placeholder('—'),
                Tables\Columns\TextColumn::make('mechanic.name')->label(__('Mecánico'))->placeholder('—'),
                Tables\Columns\TextColumn::make('title')->label(__('Servicio')),
                Tables\Columns\BadgeColumn::make('status')->label(__('Estado')),
                Tables\Columns\IconColumn::make('client_confirmed_at')
                    ->label(__('Confirmó cliente'))
                    ->boolean()
                    ->tooltip(fn ($record): ?string => $record->client_confirmed_at?->format('d/m/Y H:i')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['scheduled' => __('Programada'), 'confirmed' => __('Confirmado'), 'completed' => __('Completado'), 'cancelled' => __('Cancelado')]),
                Tables\Filters\Filter::make('today')
                    ->label(__('Hoy'))
                    ->query(fn($q) => $q->whereDate('scheduled_at', today())),
            ])
            ->actions([
                Tables\Actions\Action::make('reenviar_confirmacion')
                    ->label(__('Reenviar confirmación'))
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn (Appointment $record): bool => $record->customer !== null)
                    ->modalHeading(__('Reenviar email de confirmación'))
                    ->modalSubmitActionLabel(__('Reenviar'))
                    ->fillForm(fn (Appointment $record): array => ['email' => $record->customer?->email])
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label(__('Enviar a este email'))
                            ->email()
                            ->required()
                            ->helperText(__('Verificá el email. Si lo corregís, también se actualiza en la ficha del cliente.')),
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        try {
                            // Si corrigieron el email, lo guardamos en el cliente.
                            if ($record->customer && $data['email'] !== $record->customer->email) {
                                $record->customer->update(['email' => $data['email']]);
                            }

                            Mail::to($data['email'])->queue(new AppointmentConfirmationMail($record));

                            Notification::make()
                                ->title(__('Confirmación reenviada'))
                                ->body(__('Email encolado a ') . $data['email'])
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('No se pudo reenviar'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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
