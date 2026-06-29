<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte por sucursal (cross-tenant). Reusa DashboardService::getKpis() para
 * cada taller, así la lógica de KPIs es la misma que en el panel del taller.
 */
class TenantsReportWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Reporte por sucursal';

    /** Memo de KPIs por tenant (evita recalcular en cada columna). */
    protected array $kpiCache = [];

    protected function kpis(int $tenantId): array
    {
        return $this->kpiCache[$tenantId] ??= (function () use ($tenantId): array {
            $monthStart = now()->startOfMonth();
            $monthEnd   = now()->endOfMonth();

            return [
                'monthly_revenue' => (float) Invoice::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$monthStart, $monthEnd])
                    ->sum('total'),

                'open_work_orders' => WorkOrder::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn('status', ['delivered'])
                    ->count(),

                'completed_this_month' => WorkOrder::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$monthStart, $monthEnd])
                    ->count(),

                'inactive_customers' => Customer::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->where(fn ($q) => $q->whereNull('last_visit_at')
                        ->orWhere('last_visit_at', '<', now()->subMonths(6)))
                    ->count(),
            ];
        })();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Tenant::query()->orderBy('name'))
            ->paginated([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Sucursal')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (Tenant $r): ?string => $r->brand_slug),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('ingresos')
                    ->label('Ingresos del mes')
                    ->state(fn (Tenant $r): string => '$ ' . number_format($this->kpis($r->id)['monthly_revenue'], 2, ',', '.')),

                Tables\Columns\TextColumn::make('os_abiertas')
                    ->label('OS abiertas')
                    ->badge()
                    ->color('primary')
                    ->state(fn (Tenant $r): int => $this->kpis($r->id)['open_work_orders']),

                Tables\Columns\TextColumn::make('completadas')
                    ->label('Completadas (mes)')
                    ->state(fn (Tenant $r): int => $this->kpis($r->id)['completed_this_month']),

                Tables\Columns\TextColumn::make('inactivos')
                    ->label('Clientes inactivos')
                    ->state(fn (Tenant $r): int => $this->kpis($r->id)['inactive_customers']),

                Tables\Columns\TextColumn::make('turnos_web')
                    ->label('Turnos web s/confirmar')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->state(fn (Tenant $r): int => Appointment::withoutGlobalScopes()
                        ->where('tenant_id', $r->id)
                        ->where('source', 'web_turnero')
                        ->whereNull('client_confirmed_at')
                        ->whereDate('scheduled_at', '>=', today())
                        ->count()),
            ]);
    }
}
