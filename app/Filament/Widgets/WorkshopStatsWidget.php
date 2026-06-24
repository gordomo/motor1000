<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Number;

class WorkshopStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenantId = \App\Support\CurrentTenant::id() ?? 0;
        $kpis = app(DashboardService::class)->getKpis($tenantId);

        return [
            Stat::make(__('Ingresos del mes'), '$ ' . number_format($kpis['monthly_revenue'], 2, ',', '.'))
                ->description(__('Total facturado en el mes'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(__('OS Abiertas'), (string) $kpis['open_work_orders'])
                ->description(__('Órdenes activas en el taller'))
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($kpis['open_work_orders'] > 10 ? 'warning' : 'primary'),

            Stat::make(__('Completadas del Mes'), (string) $kpis['completed_this_month'])
                ->description(__('Servicios finalizados'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('Ticket promedio'), '$ ' . number_format($kpis['average_ticket'], 2, ',', '.'))
                ->description(__('Valor promedio por OS pagada'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),

            Stat::make(__('Clientes inactivos'), (string) $kpis['inactive_customers'])
                ->description(__('Sin visita hace 6+ meses'))
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($kpis['inactive_customers'] > 0 ? 'danger' : 'success'),

            Stat::make(__('Recordatorios pendientes'), (string) $kpis['pending_reminders'])
                ->description(__('Vence en 7 días'))
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($kpis['pending_reminders'] > 0 ? 'warning' : 'success'),
        ];
    }
}
