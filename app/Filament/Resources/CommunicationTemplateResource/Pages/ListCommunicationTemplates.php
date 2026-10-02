<?php

namespace App\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Filament\Resources\CommunicationTemplateResource;
use App\Support\Mensajes;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCommunicationTemplates extends ListRecords
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Carga los mensajes del sistema con su texto de siempre, para que
            // editarlos sea abrir y cambiar, sin tener que crearlos uno por uno.
            Actions\Action::make('cargar_mensajes')
                ->label(__('Cargar los mensajes del sistema'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => Mensajes::faltantes() !== [])
                ->requiresConfirmation()
                ->modalDescription(__('Agrega los mensajes que todavía no tienen plantilla, con el texto que se usa hoy. Las que ya cargaste no se tocan.'))
                ->action(function (): void {
                    $creadas = Mensajes::crearFaltantes();

                    Notification::make()
                        ->success()
                        ->title(trans_choice('{1} Se agregó 1 mensaje|[2,*] Se agregaron :count mensajes', $creadas, ['count' => $creadas]))
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
