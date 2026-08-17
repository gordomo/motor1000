<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderStatus: string implements HasLabel, HasColor, HasIcon
{
    case Received  = 'received';
    case Repairing = 'repairing';
    case Completed = 'completed';
    case Delivered = 'delivered';

    /**
     * Estados retirados del flujo (pedido 4 del cliente): 'diagnosis' y
     * 'waiting_parts'. No quedaron órdenes en esos estados, pero sí figuran en
     * work_order_status_history, que es un registro de auditoría y no se
     * reescribe. Por eso label() acepta valores históricos: ver labelFor().
     */
    public const RETIRED = [
        'diagnosis'     => 'Diagnóstico',
        'waiting_parts' => 'Esperando piezas',
    ];

    public function getLabel(): string
    {
        return match ($this) {
            self::Received  => __('Recibido'),
            self::Repairing => __('En reparación'),
            self::Completed => __('Completado'),
            self::Delivered => __('Entregado'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Received  => 'gray',
            self::Repairing => 'info',
            self::Completed => 'success',
            self::Delivered => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Received  => 'heroicon-o-inbox',
            self::Repairing => 'heroicon-o-wrench',
            self::Completed => 'heroicon-o-check-circle',
            self::Delivered => 'heroicon-o-truck',
        };
    }

    /**
     * Etiqueta a partir de un valor crudo, tolerando estados retirados que
     * siguen vivos en el historial. Devuelve el valor tal cual si no lo conoce,
     * en vez de reventar como haría WorkOrderStatus::from().
     */
    public static function labelFor(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return self::tryFrom($value)?->getLabel()
            ?? __(self::RETIRED[$value] ?? $value);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered]);
    }

    public static function nextStates(self $current): array
    {
        return match ($current) {
            self::Received  => [self::Repairing],
            self::Repairing => [self::Completed],
            self::Completed => [self::Delivered],
            self::Delivered => [],
        };
    }
}
