<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;

class MechanicProductivityWidget extends ChartWidget
{
    protected static ?string $heading = 'Productividad de mecánicos';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'xl' => 6,
    ];

    protected function getData(): array
    {
        $tenantId = app('current.tenant')?->id ?? 0;
        $rows = app(DashboardService::class)->getMechanicProductivity($tenantId);

        if ($rows === []) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'OS cerradas',
                    'data' => array_column($rows, 'orders'),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.55)',
                    'borderColor' => '#3B82F6',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Ingresos ($)',
                    'data' => array_map(fn ($value) => round((float) $value, 2), array_column($rows, 'revenue')),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.55)',
                    'borderColor' => '#F59E0B',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => array_column($rows, 'mechanic'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
