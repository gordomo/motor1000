<?php

namespace App\Actions\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelWorkOrderAction
{
    public function execute(WorkOrder $order, string $reason): WorkOrder
    {
        return DB::transaction(function () use ($order, $reason): WorkOrder {
            $order = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            Gate::authorize('cancel', $order);

            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => __('El motivo de anulación es obligatorio.')]);
            }

            // No inventar devoluciones de plata ni de repuestos físicos.
            if ($order->payments()->exists() || $order->items()->whereNotNull('stock_applied_at')->exists()) {
                throw ValidationException::withMessages([
                    'reason' => __('No se puede anular: la orden tiene cobros o stock consumido. Revisá esos movimientos primero.'),
                ]);
            }

            $order->update([
                'status' => WorkOrderStatus::Cancelled,
                'cancelled_reason' => $reason,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);
            $order->statusHistory()->latest('id')->first()?->update(['comment' => $reason]);

            return $order;
        });
    }
}
