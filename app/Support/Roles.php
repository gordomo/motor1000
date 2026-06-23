<?php

namespace App\Support;

class Roles
{
    /**
     * Roles que puede tener el staff de un taller (no incluye super-admin,
     * que es un flag en la tabla users, no un rol).
     */
    public const WORKSHOP = ['admin', 'manager', 'receptionist', 'mechanic'];

    private const LABELS = [
        'admin'        => 'Administrador',
        'manager'      => 'Gerente',
        'receptionist' => 'Recepcionista',
        'mechanic'     => 'Mecánico',
    ];

    public static function label(string $name): string
    {
        return self::LABELS[$name] ?? ucfirst($name);
    }

    /**
     * Opciones [name => label] para los roles del taller.
     *
     * @return array<string, string>
     */
    public static function workshopOptions(): array
    {
        return collect(self::WORKSHOP)
            ->mapWithKeys(fn (string $name): array => [$name => self::label($name)])
            ->all();
    }
}
