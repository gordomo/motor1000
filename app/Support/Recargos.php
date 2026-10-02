<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\Tenant;

/**
 * Recargos por forma de pago (definido con el cliente, 2026-10):
 *
 * - Efectivo / transferencia: sin recargo.
 * - Tarjeta: costo de facturación (ej. 10%), y en crédito además un costo
 *   financiero según la cantidad de cuotas (ej. 3 cuotas: 15%).
 * - Se aplican uno sobre otro: 1.000.000 × 1,10 × 1,15 = 1.265.000.
 *
 * Los porcentajes se configuran por taller en "Recargos con tarjeta" (admin y
 * comercial) y se guardan en tenants.settings['recargos']. Nadie los escribe a
 * mano en el presupuesto ni en el cobro: el sistema calcula el monto.
 */
class Recargos
{
    public const POR_DEFECTO = [
        'facturacion'             => 10.0,
        'metodos_con_facturacion' => ['debito', 'credito'],
        'cuotas'                  => [['cuotas' => 1, 'porcentaje' => 0.0]],
    ];

    /** @return array{facturacion: float, metodos_con_facturacion: list<string>, cuotas: list<array{cuotas: int, porcentaje: float}>} */
    public static function config(?Tenant $tenant = null): array
    {
        $tenant ??= CurrentTenant::get();
        $guardado = (array) ($tenant?->getSetting('recargos') ?? []);

        $cuotas = collect($guardado['cuotas'] ?? self::POR_DEFECTO['cuotas'])
            ->map(fn (array $c): array => ['cuotas' => (int) $c['cuotas'], 'porcentaje' => (float) $c['porcentaje']])
            ->filter(fn (array $c): bool => $c['cuotas'] >= 1)
            ->unique('cuotas')
            ->sortBy('cuotas')
            ->values()
            ->all();

        return [
            'facturacion'             => (float) ($guardado['facturacion'] ?? self::POR_DEFECTO['facturacion']),
            'metodos_con_facturacion' => array_values($guardado['metodos_con_facturacion'] ?? self::POR_DEFECTO['metodos_con_facturacion']),
            'cuotas'                  => $cuotas,
        ];
    }

    public static function guardar(Tenant $tenant, array $config): void
    {
        $settings = $tenant->settings ?? [];
        $settings['recargos'] = [
            'facturacion'             => (float) $config['facturacion'],
            'metodos_con_facturacion' => array_values($config['metodos_con_facturacion'] ?? []),
            'cuotas'                  => array_values(array_map(fn (array $c): array => [
                'cuotas'     => (int) $c['cuotas'],
                'porcentaje' => (float) $c['porcentaje'],
            ], $config['cuotas'] ?? [])),
        ];

        $tenant->update(['settings' => $settings]);
    }

    /** Solo la tarjeta de crédito se paga en cuotas. */
    public static function usaCuotas(?string $metodo): bool
    {
        return $metodo === 'credito';
    }

    /** @return array<int, string> [cuotas => "3 cuotas (+15%)"] */
    public static function opcionesDeCuotas(?Tenant $tenant = null): array
    {
        return collect(self::config($tenant)['cuotas'])
            ->mapWithKeys(fn (array $c): array => [$c['cuotas'] => trans_choice(
                '{1} 1 pago|[2,*] :n cuotas',
                $c['cuotas'],
                ['n' => $c['cuotas']],
            ) . ($c['porcentaje'] > 0 ? ' (+' . self::porcentaje($c['porcentaje']) . ')' : '')])
            ->all();
    }

    /**
     * @return array{base: float, recargo: float, total: float, cuotas: int, cuota: float}
     */
    public static function calcular(float $base, ?string $metodo, ?int $cuotas = null, ?Tenant $tenant = null): array
    {
        $config = self::config($tenant);
        $factor = 1.0;

        if ($metodo && in_array($metodo, $config['metodos_con_facturacion'], true)) {
            $factor *= 1 + $config['facturacion'] / 100;
        }

        $cuotas = self::usaCuotas($metodo) ? max(1, (int) $cuotas) : 1;

        if (self::usaCuotas($metodo)) {
            $plan = collect($config['cuotas'])->firstWhere('cuotas', $cuotas);
            $factor *= 1 + (float) ($plan['porcentaje'] ?? 0) / 100;
        }

        $total = round($base * $factor, 2);

        return [
            'base'    => round($base, 2),
            'recargo' => round($total - $base, 2),
            'total'   => $total,
            'cuotas'  => $cuotas,
            'cuota'   => round($total / $cuotas, 2),
        ];
    }

    /** "Tarjeta de crédito: 3 cuotas de $421.667 (total $1.265.000)" */
    public static function describir(float $base, ?string $metodo, ?int $cuotas = null, ?Tenant $tenant = null): string
    {
        if (! $metodo) {
            return '';
        }

        $c = self::calcular($base, $metodo, $cuotas, $tenant);
        $nombre = __(Payment::METHODS[$metodo] ?? $metodo);

        if ($c['cuotas'] > 1) {
            return __(':metodo: :n cuotas de :cuota (total :total)', [
                'metodo' => $nombre, 'n' => $c['cuotas'], 'cuota' => self::plata($c['cuota']), 'total' => self::plata($c['total']),
            ]);
        }

        return $nombre . ': ' . self::plata($c['total']);
    }

    public static function plata(float $monto): string
    {
        return '$' . number_format(round($monto), 0, ',', '.');
    }

    private static function porcentaje(float $p): string
    {
        return rtrim(rtrim(number_format($p, 2, ',', '.'), '0'), ',') . '%';
    }
}
