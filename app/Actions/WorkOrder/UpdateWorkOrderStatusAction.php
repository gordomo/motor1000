<?php

namespace App\Actions\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Services\CommunicationService;
use Illuminate\Support\Facades\DB;

class UpdateWorkOrderStatusAction
{
    public function __construct(
        private readonly CommunicationService $communicationService,
    ) {}

    public function execute(WorkOrder $order, WorkOrderStatus $newStatus, ?string $comment = null): WorkOrder
    {
        return DB::transaction(function () use ($order, $newStatus, $comment) {
            $timestamps = match ($newStatus) {
                WorkOrderStatus::Repairing => ['started_at' => now()],
                WorkOrderStatus::Completed => ['completed_at' => now()],
                WorkOrderStatus::Delivered => ['delivered_at' => now()],
                default                    => [],
            };

            $order->update(array_merge(['status' => $newStatus], $timestamps));

            // Notify customer when vehicle is ready
            if ($newStatus === WorkOrderStatus::Completed) {
                $this->communicationService->notifyVehicleReady($order);
            }

            return $order->fresh();
        });
    }
}
