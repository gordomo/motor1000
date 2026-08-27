<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ExecutiveOverviewWidget;
use App\Filament\Widgets\LowStockWidget;
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

    /**
     * Punto 7: el mecánico no ve el tablero comercial, solo el del taller.
     * Su pantalla de inicio se define en AppPanelProvider::homeUrl().
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return ! ($user->hasRole('mechanic') && ! $user->hasAnyRole(['admin', 'receptionist']));
    }

    public function getWidgets(): array
    {
        return [
            ExecutiveOverviewWidget::class,
            MonthlyRevenueChartWidget::class,
            WorkOrderStatusChartWidget::class,
            OperationsPulseWidget::class,
            LowStockWidget::class,
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
