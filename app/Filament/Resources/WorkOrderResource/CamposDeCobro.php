<?php

namespace App\Filament\Resources\WorkOrderResource;

use App\Models\Payment;
use App\Models\WorkOrder;
use App\Support\Recargos;
use Filament\Forms;
use Filament\Forms\Get;

/**
 * Campos del cobro con recargo, compartidos por los formularios que registran
 * un cobro (Registrar cobro, Entregar, cobro masivo, Corregir).
 *
 * El usuario carga cuánto se cancela de la orden y la forma de pago; si es
 * tarjeta, el sistema suma el recargo configurado (Recargos) y muestra cuánto
 * paga el cliente. El cobro guarda lo que pagó (amount) y aparte el recargo
 * (surcharge): el saldo de la orden se descuenta sin el recargo.
 */
class CamposDeCobro
{
    /** @return array<Forms\Components\Component> */
    public static function campos(?WorkOrder $orden, ?float $montoPorDefecto, bool $conMonto = true): array
    {
        $presupuesto = $orden?->quote;

        return array_values(array_filter([
            $conMonto ? Forms\Components\TextInput::make('amount')
                ->label(__('Monto que cancela de la orden'))
                ->helperText(__('Sin recargo: el recargo de la tarjeta se suma solo.'))
                ->numeric()
                ->prefix('$')
                ->default($montoPorDefecto)
                ->required()
                ->live(onBlur: true) : null,
            Forms\Components\Select::make('method')
                ->label(__('Forma de pago'))
                ->options(collect(Payment::METHODS)->map(fn (string $m): string => __($m))->all())
                // La que eligió el cliente en el presupuesto, si la hay.
                ->default($presupuesto?->payment_method)
                ->required()
                ->live(),
            Forms\Components\Select::make('installments')
                ->label(__('Cuotas'))
                ->options(fn (): array => Recargos::opcionesDeCuotas())
                ->default($presupuesto?->installments)
                ->visible(fn (Get $get): bool => Recargos::usaCuotas($get('method')))
                ->required(fn (Get $get): bool => Recargos::usaCuotas($get('method')))
                ->live(),
            Forms\Components\Placeholder::make('cliente_paga')
                ->label(__('El cliente paga'))
                ->visible(fn (Get $get): bool => self::calculo($get, $montoPorDefecto)['recargo'] > 0)
                ->content(function (Get $get) use ($montoPorDefecto): string {
                    $c = self::calculo($get, $montoPorDefecto);
                    $texto = Recargos::describir($c['base'], $get('method'), (int) $get('installments'));

                    return $texto . ' · ' . __('recargo :r', ['r' => Recargos::plata($c['recargo'])]);
                }),
        ]));
    }

    /**
     * Datos listos para guardar el Payment.
     *
     * @return array{amount: float, method: string, installments: ?int, surcharge: float}
     */
    public static function datos(array $data, float $base): array
    {
        $c = Recargos::calcular($base, $data['method'] ?? null, isset($data['installments']) ? (int) $data['installments'] : null);

        return [
            'amount'       => $c['total'],
            'method'       => $data['method'],
            'installments' => Recargos::usaCuotas($data['method'] ?? null) ? $c['cuotas'] : null,
            'surcharge'    => $c['recargo'],
        ];
    }

    /** @return array{base: float, recargo: float, total: float, cuotas: int, cuota: float} */
    private static function calculo(Get $get, ?float $montoPorDefecto): array
    {
        $base = (float) ($get('amount') ?? $montoPorDefecto ?? 0);

        return Recargos::calcular($base, $get('method'), (int) $get('installments'));
    }
}
