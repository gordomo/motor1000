<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CustomerStatus: string implements HasLabel, HasColor
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Vip      = 'vip';
    case Prospect = 'prospect';

    public function getLabel(): string
    {
        return match($this) {
            self::Active   => 'Activo',
            self::Inactive => 'Inactivo',
            self::Vip      => 'VIP',
            self::Prospect => 'Prospecto',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Active   => 'success',
            self::Inactive => 'danger',
            self::Vip      => 'warning',
            self::Prospect => 'info',
        };
    }
}
