<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Support\CurrentTenant;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * "Mi Taller": permite al administrador del taller ver y editar las preferencias
 * de SU propio taller (logo, colores, datos de contacto, dirección, horarios).
 * Solo accesible por el rol admin; scopeada al tenant actual.
 */
class WorkshopSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?int $navigationSort = 11;
    protected static string $view = 'filament.pages.workshop-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Configuraciones');
    }

    public static function getNavigationLabel(): string
    {
        return __('Mi Taller');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Mi Taller');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $tenant = CurrentTenant::get();
        abort_unless($tenant !== null, 404);

        $this->form->fill(array_merge(
            $tenant->attributesToArray(),
            ['booking' => $tenant->bookingConfig()],
        ));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Información del taller'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label(__('Correo electrónico'))
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label(__('Teléfono'))
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('whatsapp')
                            ->label(__('WhatsApp'))
                            ->tel()
                            ->maxLength(50),
                    ]),

                Forms\Components\Section::make(__('Marca y colores'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label(__('Logo del taller'))
                            ->image()
                            ->disk('public')
                            ->directory('logos')
                            ->maxSize(5120)
                            ->helperText(__('PNG, JPG o WebP. Max 5 MB. Recomendado: 300x300 px.')),
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label(__('Color principal'))
                            ->helperText(__('Usado en botones, links y acentos.')),
                        Forms\Components\ColorPicker::make('secondary_color')
                            ->label(__('Color secundario'))
                            ->helperText(__('Usado en fondos y superficies.')),
                    ]),

                Forms\Components\Section::make(__('Dirección'))
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('address')->label(__('Dirección'))->maxLength(255),
                        Forms\Components\TextInput::make('city')->label(__('Ciudad'))->maxLength(100),
                        Forms\Components\TextInput::make('state')->label(__('Provincia/Estado'))->maxLength(100),
                        Forms\Components\TextInput::make('zip')->label(__('Código postal'))->maxLength(20),
                    ]),

                Forms\Components\Section::make(__('Configuración'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('timezone')
                            ->label(__('Zona horaria'))
                            ->options([
                                'America/Argentina/Buenos_Aires' => __('Buenos Aires (UTC-3)'),
                                'America/Mexico_City'            => __('Ciudad de México (UTC-6)'),
                                'America/Bogota'                 => __('Bogotá (UTC-5)'),
                                'America/Santiago'               => __('Santiago (UTC-4)'),
                                'America/Lima'                   => __('Lima (UTC-5)'),
                                'Europe/Madrid'                  => __('Madrid (UTC+1/+2)'),
                            ]),
                        Forms\Components\Select::make('currency')
                            ->label(__('Moneda'))
                            ->options([
                                'ARS' => __('ARS — Peso argentino'),
                                'MXN' => __('MXN — Peso mexicano'),
                                'COP' => __('COP — Peso colombiano'),
                                'CLP' => __('CLP — Peso chileno'),
                                'PEN' => __('PEN — Sol peruano'),
                                'EUR' => __('EUR — Euro'),
                                'USD' => __('USD — Dólar'),
                            ]),
                    ]),

                Forms\Components\Section::make(__('Reservas online (turnero)'))
                    ->description(__('Cómo se generan y limitan los turnos que entran desde la web.'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('booking.slot_capacity')
                            ->label(__('Turnos por franja'))
                            ->helperText(__('Cuántos turnos simultáneos podés atender en cada horario.'))
                            ->numeric()->minValue(1)->default(1)->required(),
                        Forms\Components\Select::make('booking.slot_minutes')
                            ->label(__('Duración de la franja'))
                            ->options([15 => '15 min', 30 => '30 min', 45 => '45 min', 60 => '60 min'])
                            ->default(30)->required(),
                        Forms\Components\TextInput::make('booking.min_advance_hours')
                            ->label(__('Anticipación mínima (horas)'))
                            ->helperText(__('0 = se puede reservar el mismo día.'))
                            ->numeric()->minValue(0)->default(0),
                        Forms\Components\TextInput::make('booking.max_advance_days')
                            ->label(__('Días máximos a futuro'))
                            ->numeric()->minValue(1)->default(60),

                        Forms\Components\Fieldset::make(__('Horarios de atención'))
                            ->columns(1)
                            ->schema(
                                collect([
                                    'mon' => __('Lunes'), 'tue' => __('Martes'), 'wed' => __('Miércoles'),
                                    'thu' => __('Jueves'), 'fri' => __('Viernes'), 'sat' => __('Sábado'),
                                    'sun' => __('Domingo'),
                                ])->map(fn (string $label, string $key) =>
                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\Toggle::make("booking.hours.{$key}.open")
                                            ->label($label)->inline(false),
                                        Forms\Components\TimePicker::make("booking.hours.{$key}.from")
                                            ->label(__('Desde'))->seconds(false)->format('H:i'),
                                        Forms\Components\TimePicker::make("booking.hours.{$key}.to")
                                            ->label(__('Hasta'))->seconds(false)->format('H:i'),
                                    ])
                                )->values()->all()
                            ),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $tenant = CurrentTenant::get();
        abort_unless($tenant !== null, 404);

        $state = $this->form->getState();

        // Solo campos editables por el taller (no slug, ni suscripción, ni is_active).
        $data = collect($state)
            ->only([
                'name', 'email', 'phone', 'whatsapp', 'logo_path',
                'primary_color', 'secondary_color',
                'address', 'city', 'state', 'zip', 'timezone', 'currency',
            ])
            ->all();

        // Config de reservas del turnero.
        $data['booking_settings'] = $state['booking'] ?? null;

        $tenant->update($data);

        Notification::make()
            ->title(__('Preferencias actualizadas'))
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Guardar cambios'))
                ->submit('save'),
        ];
    }
}
