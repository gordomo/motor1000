<?php

namespace App\Services;

use App\Scopes\TenantScope;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonInterface;

class DashboardService
{
    public function getKpis(int $tenantId): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();

        return [
            'monthly_revenue' => $this->revenueForPeriod($tenantId, $monthStart, $monthEnd),

            'open_work_orders' => WorkOrder::withoutGlobalScopes([TenantScope::class])
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->count(),

            'completed_this_month' => WorkOrder::withoutGlobalScopes([TenantScope::class])
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['completed', 'delivered'])
                ->whereRaw('COALESCE(completed_at, delivered_at) BETWEEN ? AND ?', [$monthStart, $monthEnd])
                ->count(),

            'average_ticket' => Invoice::withoutGlobalScopes([TenantScope::class])
                ->where('tenant_id', $tenantId)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->avg('total') ?? 0,

            'inactive_customers' => Customer::withoutGlobalScopes([TenantScope::class])
                ->where('tenant_id', $tenantId)
                ->inactive(Customer::inactivityMonths(Tenant::find($tenantId)))
                ->count(),

            'pending_reminders' => Reminder::withoutGlobalScopes([TenantScope::class])
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->where('due_at', '<=', now()->addDays(7))
                ->count(),

            'open_tasks' => Task::withoutGlobalScopes([TenantScope::class])
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->count(),

            'work_orders_by_status' => WorkOrder::withoutGlobalScopes([TenantScope::class])
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),

            'monthly_revenue_trend' => $this->getMonthlyRevenueTrend($tenantId),
        ];
    }

    public function revenueForPeriod(int $tenantId, CarbonInterface $desde, CarbonInterface $hasta): float
    {
        return (float) Payment::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->whereBetween('paid_at', [$desde, $hasta])
            ->sum('amount');
    }

    private function getMonthlyRevenueTrend(int $tenantId): array
    {
        $start = now()->startOfMonth()->subMonthsNoOverflow(5);
        $expr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', paid_at)"
            : "DATE_FORMAT(paid_at, '%Y-%m')";

        $totals = Payment::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->whereBetween('paid_at', [$start, now()->endOfMonth()])
            ->selectRaw("{$expr} as month, SUM(amount) as revenue")
            ->groupBy('month')
            ->pluck('revenue', 'month')
            ->toArray();

        $trend = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i)->format('Y-m');
            $trend[$month] = (float) ($totals[$month] ?? 0);
        }

        return $trend;
    }

    public function getMechanicProductivity(int $tenantId): array
    {
        return WorkOrder::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->startOfMonth())
            ->with('mechanic:id,name')
            ->select(
                'mechanic_id',
                DB::raw('count(*) as total_orders'),
                DB::raw('SUM(labor_cost) as total_labor'),
            )
            ->groupBy('mechanic_id')
            ->get()
            ->map(fn($row) => [
                'mechanic' => $row->mechanic?->name ?? 'No asignado',
                'orders'   => $row->total_orders,
                'revenue'  => $row->total_labor,
            ])
            ->toArray();
    }
}
