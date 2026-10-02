<?php

namespace App\Filament\Pages;

use App\Support\ParaHoy;
use Filament\Pages\Page;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * Lo que el taller tiene que atender hoy: cumpleaños, recordatorios vencidos y
 * turnos de mañana para confirmar. Es un recordatorio para el usuario del
 * sistema: el sistema no le escribe al cliente (WhatsApp todavía no está
 * conectado, ver docs/WHATSAPP.md); arma el mensaje y el usuario lo manda.
 */
class ParaHoyPage extends Page
{
    protected static ?string $slug = 'para-hoy';

    protected static ?string $navigationIcon = 'heroicon-o-sun';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.para-hoy';

    /** Pestaña activa; va en la URL para que el link de la campanita abra la correcta. */
    #[Url(as: 'pestana')]
    public string $pestana = 'cumpleanos';

    public static function canAccess(): bool
    {
        return ! (auth()->user()?->isOnlyMechanic() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('Para hoy');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('CRM');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ParaHoy::totalPendientes() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Cumpleaños, recordatorios y turnos de mañana sin atender');
    }

    public function getTitle(): string
    {
        return __('Para hoy');
    }

    public function getSubheading(): ?string
    {
        return ucfirst(now()->translatedFormat('l j \d\e F'));
    }

    public function mount(): void
    {
        if (! array_key_exists($this->pestana, $this->pestanas())) {
            $this->pestana = 'cumpleanos';
        }
    }

    /** @return array<string, array{label: string, icon: string, pendientes: int, widget: class-string}> */
    public function pestanas(): array
    {
        $pendientes = ParaHoy::pendientes();

        return [
            'cumpleanos' => [
                'label'      => __('Cumpleaños'),
                'icon'       => 'heroicon-o-cake',
                'pendientes' => $pendientes['cumpleanos'],
                'widget'     => \App\Filament\Widgets\ParaHoy\CumpleanosHoy::class,
            ],
            'recordatorios' => [
                'label'      => __('Recordatorios'),
                'icon'       => 'heroicon-o-bell-alert',
                'pendientes' => $pendientes['recordatorios'],
                'widget'     => \App\Filament\Widgets\ParaHoy\RecordatoriosHoy::class,
            ],
            'turnos' => [
                'label'      => __('Turnos de mañana'),
                'icon'       => 'heroicon-o-calendar-days',
                'pendientes' => $pendientes['turnos'],
                'widget'     => \App\Filament\Widgets\ParaHoy\TurnosManana::class,
            ],
        ];
    }

    /** Las tablas avisan cuando se atiende algo, para actualizar los contadores. */
    #[On('para-hoy-actualizado')]
    public function actualizar(): void
    {
        // Solo re-render: pestanas() vuelve a contar.
    }
}
