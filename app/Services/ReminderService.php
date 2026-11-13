<?php

namespace App\Services;

use App\Scopes\TenantScope;
use App\Models\Customer;
use App\Models\Reminder;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

class ReminderService
{
    public function create(array $data): Reminder
    {
        return Reminder::create($data);
    }

    public function getDueReminders(int $tenantId): Collection
    {
        return Reminder::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('due_at', '<=', now()->addDays(3))
            ->with(['customer', 'vehicle'])
            ->get();
    }

    public function getInactiveCustomers(int $tenantId, ?int $months = null): Collection
    {
        return Customer::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->inactive($months ?? Customer::inactivityMonths(Tenant::find($tenantId)))
            ->with('vehicles')
            ->orderBy('last_visit_at')
            ->get();
    }

    public function getUpcomingMaintenances(int $tenantId, int $days = 30): Collection
    {
        return Reminder::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->whereBetween('due_at', [now(), now()->addDays($days)])
            ->with(['customer', 'vehicle'])
            ->orderBy('due_at')
            ->get();
    }
}
