<?php

namespace App\Filament\Widgets;

use App\Services\CommercialDashboardService;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Desglose de lo entregado en el período: mano de obra, repuestos y otros rubros,
 * más las formas de pago con las que entró la plata. Es la otra mitad del pedido 8.
 */
class RevenueBreakdownWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.revenue-breakdown';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'receptionist']) ?? false;
    }

    public function getMetricsProperty(): array
    {
        $desde = CarbonImmutable::parse($this->filters['desde'] ?? now()->startOfMonth())->startOfDay();
        $hasta = CarbonImmutable::parse($this->filters['hasta'] ?? now()->endOfMonth())->endOfDay();

        if ($desde->greaterThan($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return app(CommercialDashboardService::class)
            ->metrics(CurrentTenant::id() ?? 0, $desde, $hasta);
    }
}
