<?php

namespace App\Filament\Widgets;

use App\Models\Reminder;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatusHistory;
use Filament\Widgets\Widget;

class OperationsPulseWidget extends Widget
{
    protected static string $view = 'filament.widgets.operations-pulse-widget';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'xl' => 12,
    ];

    protected function getViewData(): array
    {
        $tenantId = app('current.tenant')?->id ?? 0;

        $recentWorkOrders = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'vehicle:id,license_plate,brand,model', 'mechanic:id,name'])
            ->latest()
            ->limit(6)
            ->get();

        $reminders = Reminder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->orderBy('due_at')
            ->limit(6)
            ->with(['customer:id,name'])
            ->get();

        $activity = WorkOrderStatusHistory::query()
            ->whereHas('workOrder', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['workOrder:id,number', 'user:id,name'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        return [
            'recentWorkOrders' => $recentWorkOrders,
            'reminders' => $reminders,
            'activity' => $activity,
        ];
    }
}
