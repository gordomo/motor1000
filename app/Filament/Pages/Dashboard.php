<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CommercialOverviewWidget;
use App\Filament\Widgets\ExecutiveOverviewWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\MonthlyRevenueChartWidget;
use App\Filament\Widgets\OperationsPulseWidget;
use App\Filament\Widgets\RevenueBreakdownWidget;
use App\Filament\Widgets\WorkOrderStatusChartWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

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
            // Primero los números comerciales, que son los que se miran.
            CommercialOverviewWidget::class,
            RevenueBreakdownWidget::class,
            ExecutiveOverviewWidget::class,
            MonthlyRevenueChartWidget::class,
            WorkOrderStatusChartWidget::class,
            OperationsPulseWidget::class,
            LowStockWidget::class,
        ];
    }

    /**
     * Pedido 14: filtro de fechas, por defecto el mes actual. Antes todo estaba
     * fijo al mes calendario y no había forma de mirar otro período.
     */
    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make()
                ->columns(2)
                ->schema([
                    DatePicker::make('desde')
                        ->label(__('Desde'))
                        ->native(false)
                        ->default(now()->startOfMonth()),
                    DatePicker::make('hasta')
                        ->label(__('Hasta'))
                        ->native(false)
                        ->default(now()->endOfMonth()),
                ]),
        ]);
    }

    public function getColumns(): int|string|array
    {
        return [
            'md' => 2,
            'xl' => 12,
        ];
    }
}
