<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CommunicationChannel: string implements HasLabel, HasColor
{
    case WhatsApp = 'whatsapp';
    case Email    = 'email';
    case Sms      = 'sms';

    public function getLabel(): string
    {
        return match($this) {
            self::WhatsApp => 'WhatsApp',
            self::Email    => 'E-mail',
            self::Sms      => 'SMS',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::WhatsApp => 'success',
            self::Email    => 'info',
            self::Sms      => 'warning',
        };
    }
}
