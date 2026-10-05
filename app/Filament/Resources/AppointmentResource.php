<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use App\Filament\Concerns\HiddenFromMechanics;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    use HiddenFromMechanics;

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

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Turnos agendados para hoy');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Appointment::where('status', 'scheduled')
            ->whereDate('scheduled_at', today())
            ->count();
    }

    /**
     * Fecha y hora con que arranca el formulario: la del turno al editar; al
     * crear, la que viene del calendario (clic en un horario) o la próxima
     * franja desde ahora.
     */
    private static function horarioInicial(?Appointment $record): \Carbon\Carbon
    {
        if ($record?->scheduled_at) {
            return $record->scheduled_at->copy();
        }

        if ($desdeCalendario = request()->query('scheduled_at')) {
            return \Carbon\Carbon::parse($desdeCalendario);
        }

        $minutos = self::minutosPorTurno();
        $ahora = now()->second(0);

        return $ahora->minute((int) (ceil($ahora->minute / $minutos) * $minutos));
    }

    /** Cada cuántos minutos van los turnos (Mi Taller → Reservas). */
    private static function minutosPorTurno(): int
    {
        return max(5, (int) (\App\Support\CurrentTenant::get()?->bookingConfig()['slot_minutes'] ?? 30));
    }

    /**
     * Horarios de 07:00 a 21:00 (el rango del calendario), cada
     * minutosPorTurno(). Si el turno tiene una hora fuera de la grilla
     * (10:15 con franjas de 30), se conserva.
     *
     * @return array<string, string>
     */
    public static function opcionesDeHora(?string $actual = null): array
    {
        $horas = [];
        for ($m = 7 * 60; $m <= 21 * 60; $m += self::minutosPorTurno()) {
            $h = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            $horas[$h] = $h;
        }

        if ($actual && ! isset($horas[$actual])) {
            $horas[$actual] = $actual;
            ksort($horas);
        }

        return $horas;
    }

    /**
     * Lugares libres por horario para un turno de $duracion minutos ese día,
     * según cuántos autos se atienden a la vez (Mi Taller → capacidad).
     *
     * @return array<string, int> [hora => lugares libres]
     */
    public static function lugaresLibres(?string $fecha, int $duracion, ?int $exceptoId, ?string $actual = null): array
    {
        $tenant = \App\Support\CurrentTenant::get();

        if (! $tenant || ! $fecha) {
            return [];
        }

        // Se pide una vez por horario al dibujar la lista: se calcula una sola vez.
        static $memo = [];
        $clave = implode('|', [$tenant->id, $fecha, $duracion, $exceptoId, $actual]);
        if (isset($memo[$clave])) {
            return $memo[$clave];
        }

        $servicio = app(\App\Services\Booking\SlotAvailability::class);
        $dia = \Carbon\Carbon::parse($fecha);
        $turnos = $servicio->turnosDelDia($tenant, $dia, $exceptoId);
        $capacidad = \App\Services\Booking\SlotAvailability::capacidad($tenant);

        $libres = [];
        foreach (array_keys(self::opcionesDeHora($actual)) as $hora) {
            $desde = $dia->copy()->setTimeFromTimeString($hora);
            $libres[$hora] = max(0, $capacidad - $servicio->ocupacion($turnos, $desde, max(1, $duracion), self::minutosPorTurno()));
        }

        return $memo[$clave] = $libres;
    }

    private static function etiquetaDeHora(string $hora, ?int $libres): string
    {
        return match (true) {
            $libres === null => $hora,
            $libres === 0    => $hora . ' · ' . __('completo'),
            default          => $hora . ' · ' . trans_choice('{1} queda 1 lugar|[2,*] quedan :n lugares', $libres, ['n' => $libres]),
        };
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('customer_id')
                    ->label(__('Cliente'))
                    ->relationship('customer', 'name')
                    ->searchable()->preload()->required()
                    ->default(fn (): ?int => request()->integer('customer_id') ?: null)
                    ->reactive()
                    ->afterStateUpdated(fn($set) => $set('vehicle_id', null))
                    // Buscar rápido y, si no existe, crear un cliente mínimo al vuelo.
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label(__('Nombre'))->required()->maxLength(255),
                        Forms\Components\TextInput::make('phone')->label(__('Teléfono'))->tel()->required()->maxLength(20),
                        Forms\Components\TextInput::make('whatsapp')->label('WhatsApp')->tel()->maxLength(20),
                    ])
                    ->createOptionModalHeading(__('Nuevo cliente')),
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
                    ->placeholder(__('Ej: Service, revisión...'))
                    ->default(fn (): string => request()->query('title', 'Turno')),
                // Fecha y hora por separado, con clics. El selector nativo de
                // fecha/hora cambiaba con la ruedita del mouse en algunos
                // navegadores y se agendaban turnos a otra hora sin querer.
                Forms\Components\DatePicker::make('fecha')
                    ->label(__('Fecha'))
                    ->live()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->required()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (Forms\Components\DatePicker $component, ?Appointment $record) => $component->state(
                        self::horarioInicial($record)->format('Y-m-d')
                    )),
                Forms\Components\Select::make('hora')
                    ->label(__('Hora'))
                    // Cada horario dice cuántos lugares quedan (autos a la vez,
                    // Mi Taller) para un turno de esta duración; los llenos no se
                    // pueden elegir, salvo la hora que ya tiene el turno.
                    ->options(function (Get $get, ?Appointment $record): array {
                        $actual = self::horarioInicial($record)->format('H:i');
                        $libres = self::lugaresLibres($get('fecha'), (int) ($get('duration_minutes') ?: 60), $record?->id, $actual);

                        return collect(self::opcionesDeHora($actual))
                            ->mapWithKeys(fn (string $h): array => [$h => self::etiquetaDeHora($h, $libres[$h] ?? null)])
                            ->all();
                    })
                    ->disableOptionWhen(function (string $value, Get $get, ?Appointment $record): bool {
                        if ($record?->scheduled_at && $record->scheduled_at->format('Y-m-d H:i') === \Carbon\Carbon::parse($get('fecha'))->format('Y-m-d') . ' ' . $value) {
                            return false;
                        }

                        $libres = self::lugaresLibres($get('fecha'), (int) ($get('duration_minutes') ?: 60), $record?->id, $value);

                        return ($libres[$value] ?? 1) === 0;
                    })
                    // La misma regla al guardar (no alcanza con deshabilitar en la lista).
                    ->rule(fn (Get $get, ?Appointment $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record): void {
                        if (! $value || ! $get('fecha')) {
                            return;
                        }

                        $mismoHorario = $record?->scheduled_at
                            && $record->scheduled_at->format('Y-m-d H:i') === \Carbon\Carbon::parse($get('fecha'))->format('Y-m-d') . ' ' . $value
                            && (int) $record->duration_minutes === (int) ($get('duration_minutes') ?: 60);

                        $libres = self::lugaresLibres($get('fecha'), (int) ($get('duration_minutes') ?: 60), $record?->id, $value);

                        if (! $mismoHorario && ($libres[$value] ?? 1) === 0) {
                            $fail(__('A esa hora el taller ya está completo (autos a la vez configurados en Mi Taller). Elegí otro horario o acortá la duración.'));
                        }
                    })
                    // Lista con buscador (no el desplegable del navegador, que en
                    // Windows también cambia con la ruedita): escribir "14" filtra.
                    ->searchable()
                    ->required()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (Forms\Components\Select $component, ?Appointment $record) => $component->state(
                        self::horarioInicial($record)->format('H:i')
                    )),
                Forms\Components\Hidden::make('scheduled_at')
                    ->dehydrateStateUsing(fn (Get $get): ?string => filled($get('fecha')) && filled($get('hora'))
                        ? \Carbon\Carbon::parse($get('fecha'))->format('Y-m-d') . ' ' . $get('hora') . ':00'
                        : null),
                Forms\Components\TextInput::make('duration_minutes')
                    ->label(__('Duración (min)'))
                    ->numeric()
                    ->live(onBlur: true)
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
                Tables\Columns\BadgeColumn::make('status')->label(__('Estado'))->formatStateUsing(fn (?string $state) => \App\Support\Etiquetas::estadoCita($state)),
                Tables\Columns\IconColumn::make('client_confirmed_at')
                    ->label(__('Confirmó cliente'))
                    ->boolean()
                    ->tooltip(fn ($record): ?string => $record->client_confirmed_at?->format('d/m/Y H:i')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('Estado'))
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
