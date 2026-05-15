<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Collection;

class ReminderService
{
    public function create(array $data): Reminder
    {
        return Reminder::create($data);
    }

    public function getDueReminders(int $tenantId): Collection
    {
        return Reminder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('due_at', '<=', now()->addDays(3))
            ->with(['customer', 'vehicle'])
            ->get();
    }

    public function getInactiveCustomers(int $tenantId, int $months = 6): Collection
    {
        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) use ($months) {
                $q->whereNull('last_visit_at')
                  ->orWhere('last_visit_at', '<', now()->subMonths($months));
            })
            ->with('vehicles')
            ->orderBy('last_visit_at')
            ->get();
    }

    public function getUpcomingMaintenances(int $tenantId, int $days = 30): Collection
    {
        return Reminder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->whereBetween('due_at', [now(), now()->addDays($days)])
            ->with(['customer', 'vehicle'])
            ->orderBy('due_at')
            ->get();
    }
}
