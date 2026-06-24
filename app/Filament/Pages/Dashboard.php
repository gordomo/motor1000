<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ExecutiveOverviewWidget;
use App\Filament\Widgets\MonthlyRevenueChartWidget;
use App\Filament\Widgets\OperationsPulseWidget;
use App\Filament\Widgets\WorkOrderStatusChartWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?int $navigationSort = -1;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Centro de Operaciones');
    }

    public static function getNavigationLabel(): string
    {
        return __('Centro de Operaciones');
    }

    public function getWidgets(): array
    {
        return [
            ExecutiveOverviewWidget::class,
            MonthlyRevenueChartWidget::class,
            WorkOrderStatusChartWidget::class,
            OperationsPulseWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return [
            'md' => 2,
            'xl' => 12,
        ];
    }
}
