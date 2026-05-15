<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReminderType: string implements HasLabel, HasColor
{
    case OilChange       = 'oil_change';
    case BrakeInspection = 'brake_inspection';
    case TireRotation    = 'tire_rotation';
    case Alignment       = 'alignment';
    case Checkup         = 'checkup';
    case Custom          = 'custom';

    public function getLabel(): string
    {
        return match($this) {
            self::OilChange       => 'Cambio de aceite',
            self::BrakeInspection => 'Inspección de frenos',
            self::TireRotation    => 'Rotación de neumáticos',
            self::Alignment       => 'Alineación/balanceo',
            self::Checkup         => 'Revisión general',
            self::Custom          => 'Personalizado',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::OilChange       => 'warning',
            self::BrakeInspection => 'danger',
            self::TireRotation    => 'info',
            self::Alignment       => 'primary',
            self::Checkup         => 'success',
            self::Custom          => 'gray',
        };
    }
}
