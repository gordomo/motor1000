<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
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
        $tenantId = app('current.tenant')?->id ?? 0;
        $kpis = app(DashboardService::class)->getKpis($tenantId);

        $currentMonthRevenue = (float) $kpis['monthly_revenue'];
        $previousMonthRevenue = (float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ])
            ->sum('total');

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
