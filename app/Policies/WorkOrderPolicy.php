<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'mechanic', 'receptionist']);
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->tenant_id === $workOrder->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'receptionist']);
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->tenant_id === $workOrder->tenant_id
            && $user->hasAnyRole(['admin', 'mechanic', 'receptionist']);
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $user->tenant_id === $workOrder->tenant_id
            && $user->hasAnyRole(['admin', 'receptionist']);
    }
}
