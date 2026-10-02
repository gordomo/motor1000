<?php

namespace App\Notifications;

use App\Filament\Pages\ParaHoyPage;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Resumen de la mañana en la campanita: qué hay para atender hoy.
 * Lo recibe el usuario del sistema (admin y recepción), no el cliente.
 */
class ParaHoyNotification extends Notification
{
    use Queueable;

    /** @param array{cumpleanos: int, recordatorios: int, turnos: int} $pendientes */
    public function __construct(private readonly array $pendientes) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $partes = array_filter([
            $this->pendientes['cumpleanos']
                ? trans_choice('{1} 1 cumpleaños|[2,*] :count cumpleaños', $this->pendientes['cumpleanos'], ['count' => $this->pendientes['cumpleanos']])
                : null,
            $this->pendientes['recordatorios']
                ? trans_choice('{1} 1 recordatorio|[2,*] :count recordatorios', $this->pendientes['recordatorios'], ['count' => $this->pendientes['recordatorios']])
                : null,
            $this->pendientes['turnos']
                ? trans_choice('{1} 1 turno de mañana para confirmar|[2,*] :count turnos de mañana para confirmar', $this->pendientes['turnos'], ['count' => $this->pendientes['turnos']])
                : null,
        ]);

        // Abre la pestaña de lo primero que haya.
        $pestana = collect(['cumpleanos', 'recordatorios', 'turnos'])
            ->first(fn (string $p): bool => $this->pendientes[$p] > 0);

        return FilamentNotification::make()
            ->title(__('Para hoy'))
            ->body(implode(' · ', $partes))
            ->icon('heroicon-o-sun')
            ->info()
            ->actions([
                Action::make('ver')
                    ->label(__('Ver'))
                    ->button()
                    ->url(ParaHoyPage::getUrl(['pestana' => $pestana], panel: 'app'))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
