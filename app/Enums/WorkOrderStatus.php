<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderStatus: string implements HasLabel, HasColor, HasIcon
{
    case Received     = 'received';
    case Diagnosis    = 'diagnosis';
    case WaitingParts = 'waiting_parts';
    case Repairing    = 'repairing';
    case Completed    = 'completed';
    case Delivered    = 'delivered';

    public function getLabel(): string
    {
        return match($this) {
            self::Received     => 'Recibido',
            self::Diagnosis    => 'Diagnóstico',
            self::WaitingParts => 'Esperando piezas',
            self::Repairing    => 'En reparación',
            self::Completed    => 'Completado',
            self::Delivered    => 'Entregado',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Received     => 'gray',
            self::Diagnosis    => 'warning',
            self::WaitingParts => 'orange',
            self::Repairing    => 'info',
            self::Completed    => 'success',
            self::Delivered    => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match($this) {
            self::Received     => 'heroicon-o-inbox',
            self::Diagnosis    => 'heroicon-o-magnifying-glass',
            self::WaitingParts => 'heroicon-o-clock',
            self::Repairing    => 'heroicon-o-wrench',
            self::Completed    => 'heroicon-o-check-circle',
            self::Delivered    => 'heroicon-o-truck',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered]);
    }

    public static function nextStates(self $current): array
    {
        return match($current) {
            self::Received     => [self::Diagnosis],
            self::Diagnosis    => [self::WaitingParts, self::Repairing],
            self::WaitingParts => [self::Repairing],
            self::Repairing    => [self::Completed],
            self::Completed    => [self::Delivered],
            self::Delivered    => [],
        };
    }
}
