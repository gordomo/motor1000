<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs consolidados de TODA la plataforma (todas las sucursales). Visible solo
 * en el panel /admin (super-admin). Usa withoutGlobalScopes para agregar
 * cross-sucursal sin importar el contexto de tenant.
 */
class PlatformStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();

        $ingresos = (float) Invoice::withoutGlobalScopes()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('total');

        $abiertas = WorkOrder::withoutGlobalScopes()
            ->whereNotIn('status', ['delivered'])
            ->count();

        $citasHoy = Appointment::withoutGlobalScopes()
            ->whereDate('scheduled_at', today())
            ->count();

        $turnosWebPend = Appointment::withoutGlobalScopes()
            ->where('source', 'web_turnero')
            ->whereNull('client_confirmed_at')
            ->whereDate('scheduled_at', '>=', today())
            ->count();

        return [
            Stat::make('Sucursales activas', (string) Tenant::where('is_active', true)->count())
                ->description('Talleres en la plataforma')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),

            Stat::make('Ingresos del mes', '$ ' . number_format($ingresos, 2, ',', '.'))
                ->description('Facturado (todas las sucursales)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('OS abiertas', (string) $abiertas)
                ->description('Órdenes activas en total')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($abiertas > 50 ? 'warning' : 'primary'),

            Stat::make('Citas de hoy', (string) $citasHoy)
                ->description('Agendadas para hoy')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Clientes', (string) Customer::withoutGlobalScopes()->count())
                ->description('Total en la plataforma')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),

            Stat::make('Turnos web sin confirmar', (string) $turnosWebPend)
                ->description('Reservas online pendientes')
                ->descriptionIcon('heroicon-m-clock')
                ->color($turnosWebPend > 0 ? 'warning' : 'success'),
        ];
    }
}
