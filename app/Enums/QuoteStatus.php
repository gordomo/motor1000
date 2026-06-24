<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum QuoteStatus: string implements HasLabel, HasColor, HasIcon
{
    case Draft    = 'draft';
    case Sent     = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match($this) {
            self::Draft    => __('Borrador'),
            self::Sent     => __('Enviado'),
            self::Accepted => __('Aceptado'),
            self::Rejected => __('Rechazado'),
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Draft    => 'gray',
            self::Sent     => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match($this) {
            self::Draft    => 'heroicon-o-pencil',
            self::Sent     => 'heroicon-o-paper-airplane',
            self::Accepted => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }
}
