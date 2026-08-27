<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Estados del presupuesto según el flujo que definió el cliente: pendiente de
 * aprobación, aprobado o rechazado.
 *
 * Antes había cuatro ('draft' y 'sent' por separado) y en prod 58 de 70
 * presupuestos quedaban en borrador porque nadie los movía. Se unificaron en
 * Pending. No se pierde información: que el presupuesto se le haya enviado al
 * cliente sigue registrado en quotes.sent_at.
 */
enum QuoteStatus: string implements HasLabel, HasColor, HasIcon
{
    case Pending  = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    /** Estados retirados que siguen vivos en registros históricos. */
    public const RETIRED = [
        'draft' => 'Borrador',
        'sent'  => 'Enviado',
    ];

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending  => __('Pendiente de aprobación'),
            self::Accepted => __('Aprobado'),
            self::Rejected => __('Rechazado'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending  => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending  => 'heroicon-o-clock',
            self::Accepted => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }

    public static function labelFor(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return self::tryFrom($value)?->getLabel() ?? __(self::RETIRED[$value] ?? $value);
    }
}
