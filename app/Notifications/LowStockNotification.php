<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Pedido 6: aviso de inventario crítico.
 *
 * Llega a la campanita del panel de los administradores del taller. Se manda una
 * sola notificación con todos los ítems bajos, no una por pieza: con 43 ítems en
 * inventario, avisar de a uno sería ruido y nadie lo leería.
 */
class LowStockNotification extends Notification
{
    use Queueable;

    /** @param Collection<int, InventoryItem> $items */
    public function __construct(private readonly Collection $items) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $cantidad = $this->items->count();

        $detalle = $this->items
            ->take(5)
            ->map(fn (InventoryItem $i): string => sprintf(
                '%s: %s %s (mínimo %s)',
                $i->name,
                rtrim(rtrim((string) $i->stock_quantity, '0'), '.'),
                $i->unit,
                rtrim(rtrim((string) $i->min_stock, '0'), '.'),
            ))
            ->implode(' · ');

        $resto = $cantidad > 5 ? ' ' . __('y :n más', ['n' => $cantidad - 5]) : '';

        return FilamentNotification::make()
            ->title(trans_choice('{1} Hay 1 repuesto por debajo del mínimo|[2,*] Hay :count repuestos por debajo del mínimo', $cantidad, ['count' => $cantidad]))
            ->body($detalle . $resto)
            ->icon('heroicon-o-exclamation-triangle')
            ->warning()
            ->getDatabaseMessage();
    }
}
