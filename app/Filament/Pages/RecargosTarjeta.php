<?php

namespace App\Filament\Pages;

use App\Models\Payment;
use App\Support\CurrentTenant;
use App\Support\Recargos;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Los porcentajes de recargo por forma de pago (ver App\Support\Recargos).
 * Los configuran el administrador y el comercial; el presupuesto y el cobro
 * los usan para calcular el monto solos.
 */
class RecargosTarjeta extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'recargos';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.workshop-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'receptionist']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Configuraciones');
    }

    public static function getNavigationLabel(): string
    {
        return __('Recargos con tarjeta');
    }

    public function getTitle(): string
    {
        return __('Recargos con tarjeta');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(Recargos::config());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Costo de facturación'))
                    ->description(__('Se suma cuando se paga con tarjeta (o los medios que elijas).'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('facturacion')
                            ->label(__('Porcentaje'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->live(onBlur: true),
                        Forms\Components\CheckboxList::make('metodos_con_facturacion')
                            ->label(__('Se aplica a'))
                            ->options(collect(Payment::METHODS)
                                ->except(['efectivo', 'transferencia'])
                                ->map(fn (string $m): string => __($m))
                                ->all())
                            ->live(),
                    ]),

                Forms\Components\Section::make(__('Costo financiero por cuotas (tarjeta de crédito)'))
                    ->description(__('Se aplica sobre el monto con el costo de facturación ya sumado.'))
                    ->schema([
                        Forms\Components\Repeater::make('cuotas')
                            ->label('')
                            ->columns(2)
                            ->addActionLabel(__('Agregar cantidad de cuotas'))
                            ->defaultItems(1)
                            ->minItems(1)
                            ->live()
                            ->schema([
                                Forms\Components\TextInput::make('cuotas')
                                    ->label(__('Cuotas'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(36)
                                    ->required()
                                    ->distinct(),
                                Forms\Components\TextInput::make('porcentaje')
                                    ->label(__('Recargo'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('%')
                                    ->default(0)
                                    ->required(),
                            ]),
                    ]),

                Forms\Components\Section::make(__('Ejemplo'))
                    ->schema([
                        Forms\Components\Placeholder::make('ejemplo')
                            ->label(__('Un presupuesto de $1.000.000'))
                            ->content(fn (Get $get): \Illuminate\Support\HtmlString => self::ejemplo($get)),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        Recargos::guardar(CurrentTenant::get(), $this->form->getState());

        Notification::make()->title(__('Recargos guardados'))->success()->send();
    }

    /** Cómo queda un presupuesto de $1.000.000 con lo que hay cargado en el formulario. */
    private static function ejemplo(Get $get): \Illuminate\Support\HtmlString
    {
        $tenant = clone CurrentTenant::get();
        $tenant->settings = array_merge($tenant->settings ?? [], ['recargos' => [
            'facturacion'             => (float) $get('facturacion'),
            'metodos_con_facturacion' => (array) $get('metodos_con_facturacion'),
            'cuotas'                  => array_values(array_filter((array) $get('cuotas'), fn ($c) => filled($c['cuotas'] ?? null))),
        ]]);

        $lineas = [Recargos::describir(1_000_000, 'efectivo', null, $tenant)];

        if (in_array('debito', (array) $get('metodos_con_facturacion'), true)) {
            $lineas[] = Recargos::describir(1_000_000, 'debito', null, $tenant);
        }

        foreach (array_keys(Recargos::opcionesDeCuotas($tenant)) as $n) {
            $lineas[] = Recargos::describir(1_000_000, 'credito', $n, $tenant);
        }

        return new \Illuminate\Support\HtmlString(implode('<br>', array_map('e', $lineas)));
    }
}
