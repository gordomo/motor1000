<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\Widget;

class ExecutiveOverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.executive-overview-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = [
        'xl' => 12,
    ];

    protected function getViewData(): array
    {
        $tenantId = \App\Support\CurrentTenant::id() ?? 0;
        $service = app(DashboardService::class);
        $kpis = $service->getKpis($tenantId);

        $currentMonthRevenue = (float) $kpis['monthly_revenue'];
        $previousMonthRevenue = $service->revenueForPeriod(
            $tenantId,
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->endOfMonth(),
        );

        $revenueVariation = null;
        if ($previousMonthRevenue > 0) {
            $revenueVariation = (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100;
        }

        return [
            'kpis' => $kpis,
            'currentMonthRevenue' => $currentMonthRevenue,
            'revenueVariation' => $revenueVariation,
        ];
    }
}
