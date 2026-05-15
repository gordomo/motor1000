<?php

namespace App\Helpers;

use App\Models\Tenant;

class TenantBranding
{
    public static function primary(): string
    {
        return self::getTenant()?->primary_color ?? '#0f766e';
    }

    public static function secondary(): string
    {
        return self::getTenant()?->secondary_color ?? '#f5f5f5';
    }

    public static function logoPath(): ?string
    {
        $tenant = self::getTenant();

        return $tenant?->logo_path ? asset('storage/' . $tenant->logo_path) : null;
    }

    public static function getTenant(): ?Tenant
    {
        return app()->bound('current.tenant') ? app('current.tenant') : null;
    }

    /**
     * Convert hex color to RGB string (e.g., "15, 118, 110")
     */
    public static function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "$r, $g, $b";
    }

    /**
     * Get primary color RGB for use in rgba()
     */
    public static function primaryRgb(): string
    {
        return self::hexToRgb(self::primary());
    }

    /**
     * Get secondary color RGB for use in rgba()
     */
    public static function secondaryRgb(): string
    {
        return self::hexToRgb(self::secondary());
    }

    /**
     * Get CSS variables for use in inline styles or CSS blocks
     */
    public static function cssVariables(): array
    {
        return [
            '--tenant-primary'     => self::primary(),
            '--tenant-primary-rgb' => self::primaryRgb(),
            '--tenant-secondary'   => self::secondary(),
            '--tenant-secondary-rgb' => self::secondaryRgb(),
        ];
    }

    /**
     * Get CSS variable string for use in inline styles or CSS blocks
     */
    public static function cssVariableString(): string
    {
        return implode('; ', array_map(
            fn ($key, $value) => "$key: $value",
            array_keys(self::cssVariables()),
            array_values(self::cssVariables())
        ));
    }
}
