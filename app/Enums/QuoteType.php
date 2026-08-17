<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Pedido 2: el cliente puede armar presupuestos con el checklist de revisión
 * obligatorio, o presupuestos rápidos sin checklist (un cambio de aceite no
 * necesita 20 puntos revisados).
 */
enum QuoteType: string implements HasLabel, HasDescription
{
    case ConChecklist = 'con_checklist';
    case SinChecklist = 'sin_checklist';

    public function getLabel(): string
    {
        return match ($this) {
            self::ConChecklist => __('Con revisión'),
            self::SinChecklist => __('Sin revisión'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::ConChecklist => __('Incluye el checklist de revisión y hay que completarlo'),
            self::SinChecklist => __('Presupuesto directo, sin checklist'),
        };
    }

    public function requiresChecklist(): bool
    {
        return $this === self::ConChecklist;
    }
}
