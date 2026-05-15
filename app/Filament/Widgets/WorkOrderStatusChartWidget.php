<?php

namespace App\Filament\Widgets;

use App\Models\WorkOrder;
use App\Enums\WorkOrderStatus;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class WorkOrderStatusChartWidget extends ChartWidget
{
    protected static ?string $heading = 'OS por estado';
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'xl' => 6,
    ];

    protected function getData(): array
    {
        $tenantId = app('current.tenant')?->id ?? 0;

        $data = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['delivered'])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $labels = [];
        $values = [];
        $colors = [];

        foreach (WorkOrderStatus::cases() as $status) {
            if ($status === WorkOrderStatus::Delivered) continue;
            $labels[] = $status->getLabel();
            $values[] = $data[$status->value] ?? 0;
            $colors[] = match($status->getColor()) {
                'gray'    => '#9ca3af',
                'warning' => '#f59e0b',
                'orange'  => '#fb923c',
                'info'    => '#60a5fa',
                'success' => '#10b981',
                'primary' => '#94a3b8',
                default   => '#9ca3af',
            };
        }

        return [
            'datasets' => [
                [
                    'label'           => 'OS',
                    'data'            => $values,
                    'backgroundColor' => $colors,
                    'borderWidth'     => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 10,
                        'padding' => 12,
                    ],
                ],
            ],
            'cutout' => '68%',
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
