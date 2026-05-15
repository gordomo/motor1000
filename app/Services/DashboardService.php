<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getKpis(int $tenantId): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();

        return [
            'monthly_revenue' => Invoice::withoutGlobalScopes()
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

            'average_ticket' => Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->avg('total') ?? 0,

            'inactive_customers' => Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('last_visit_at')
                      ->orWhere('last_visit_at', '<', now()->subMonths(6));
                })
                ->count(),

            'pending_reminders' => Reminder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->where('due_at', '<=', now()->addDays(7))
                ->count(),

            'open_tasks' => Task::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->count(),

            'work_orders_by_status' => WorkOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['delivered'])
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),

            'monthly_revenue_trend' => $this->getMonthlyRevenueTrend($tenantId),
        ];
    }

    private function getMonthlyRevenueTrend(int $tenantId): array
    {
        return Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as month"),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('revenue', 'month')
            ->toArray();
    }

    public function getMechanicProductivity(int $tenantId): array
    {
        return WorkOrder::withoutGlobalScopes()
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
