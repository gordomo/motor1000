<?php

namespace App\Actions\WorkOrder;

use App\DTOs\CreateWorkOrderDTO;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class CreateWorkOrderAction
{
    public function execute(CreateWorkOrderDTO $dto): WorkOrder
    {
        return DB::transaction(function () use ($dto) {
            $order = WorkOrder::create([
                'tenant_id'    => $dto->tenantId,
                'customer_id'  => $dto->customerId,
                'vehicle_id'   => $dto->vehicleId,
                'mechanic_id'  => $dto->mechanicId,
                'complaint'    => $dto->complaint,
                'priority'     => $dto->priority,
                'estimated_at' => $dto->estimatedAt,
                'status'       => 'received',
            ]);

            // Update vehicle last service
            $order->vehicle->update(['last_service_at' => now()]);

            // Update customer last visit
            $order->customer->update(['last_visit_at' => now()]);

            return $order;
        });
    }
}
