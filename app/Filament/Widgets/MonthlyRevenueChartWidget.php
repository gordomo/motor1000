<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;

class MonthlyRevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Ingresos mensuales (6 meses)';
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'xl' => 6,
    ];

    protected function getData(): array
    {
        $tenantId = app('current.tenant')?->id ?? 0;
        $trend = app(DashboardService::class)->getKpis($tenantId)['monthly_revenue_trend'];

        return [
            'datasets' => [
                [
                    'label'           => 'Ingresos ($)',
                    'data'            => array_values($trend),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.12)',
                    'borderColor'     => '#f59e0b',
                    'borderWidth'     => 2,
                    'pointRadius'     => 2,
                    'tension'         => 0.28,
                    'fill'            => false,
                ],
            ],
            'labels' => array_keys($trend),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'grid' => ['color' => '#e5e7eb'],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
