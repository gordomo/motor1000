<?php

namespace App\Filament\Widgets;

use App\Services\CommercialDashboardService;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Los números que pidió el cliente, con sus definiciones:
 * presupuestado, trabajo listo para cobrar, y cobrado de verdad.
 */
class CommercialOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'receptionist']) ?? false;
    }

    protected function getStats(): array
    {
        [$desde, $hasta] = $this->rango();

        $m = app(CommercialDashboardService::class)
            ->metrics(CurrentTenant::id() ?? 0, $desde, $hasta);

        return [
            Stat::make(__('Presupuestado'), $this->plata($m['presupuestado']['monto']))
                ->description(trans_choice(
                    '{1} 1 presupuesto|[2,*] :count presupuestos',
                    $m['presupuestado']['cantidad'],
                    ['count' => $m['presupuestado']['cantidad']],
                ) . ' · ' . __(':n aprobados', ['n' => $m['presupuestado']['aprobados']]))
                ->descriptionIcon('heroicon-o-document-text')
                ->color('gray'),

            Stat::make(__('Aprobado'), $this->plata($m['presupuestado']['monto_aprobado']))
                ->description(__(':n% de conversión', ['n' => $m['presupuestado']['conversion']]))
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('info'),

            // Incluye los autos ya entregados con saldo: esa deuda antes no
            // aparecía en ningún número del tablero.
            Stat::make(__('Por cobrar'), $this->plata($m['por_cobrar']['monto']))
                ->description($this->detallePorCobrar($m['por_cobrar']))
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),

            Stat::make(__('Cobrado'), $this->plata($m['cobrado']['monto']))
                ->description($m['cobrado']['adelantos'] > 0
                    ? __('incluye :monto de adelantos', ['monto' => $this->plata($m['cobrado']['adelantos'])])
                    : trans_choice('{1} 1 cobro|[2,*] :count cobros', $m['cobrado']['cantidad'], ['count' => $m['cobrado']['cantidad']]))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }

    /** Cómo se reparte lo que hay para cobrar, para que el número se entienda. */
    private function detallePorCobrar(array $porCobrar): string
    {
        if ($porCobrar['cantidad'] === 0) {
            return __('No hay nada pendiente de cobro');
        }

        $partes = [];

        if ($porCobrar['cantidad_terminado'] > 0) {
            $partes[] = trans_choice(
                '{1} 1 orden terminada sin entregar|[2,*] :count terminadas sin entregar',
                $porCobrar['cantidad_terminado'],
                ['count' => $porCobrar['cantidad_terminado']],
            );
        }

        if ($porCobrar['cantidad_entregado'] > 0) {
            $partes[] = trans_choice(
                '{1} 1 entregada con saldo|[2,*] :count entregadas con saldo',
                $porCobrar['cantidad_entregado'],
                ['count' => $porCobrar['cantidad_entregado']],
            ) . ' (' . $this->plata($porCobrar['entregado']) . ')';
        }

        return implode(' · ', $partes);
    }

    /** Rango del filtro del tablero; por defecto el mes actual (pedido 14). */
    private function rango(): array
    {
        $desde = CarbonImmutable::parse($this->filters['desde'] ?? now()->startOfMonth())->startOfDay();
        $hasta = CarbonImmutable::parse($this->filters['hasta'] ?? now()->endOfMonth())->endOfDay();

        return $desde->greaterThan($hasta) ? [$hasta, $desde] : [$desde, $hasta];
    }

    private function plata(float $monto): string
    {
        return '$ ' . number_format($monto, 0, ',', '.');
    }
}
