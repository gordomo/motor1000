<?php

namespace App\Actions\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Services\CommunicationService;
use App\Services\Inventory\WorkOrderStockService;
use Illuminate\Support\Facades\DB;

class UpdateWorkOrderStatusAction
{
    public function __construct(
        private readonly CommunicationService $communicationService,
        private readonly WorkOrderStockService $stockService,
    ) {}

    public function execute(WorkOrder $order, WorkOrderStatus $newStatus, ?string $comment = null): WorkOrder
    {
        return DB::transaction(function () use ($order, $newStatus, $comment) {
            $previousStatus = $order->status;

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

            $this->syncStock($order, $previousStatus, $newStatus);

            return $order->fresh();
        });
    }

    /**
     * Pedido 5: el trabajo terminado descuenta los repuestos del inventario, y
     * reabrir la orden los devuelve. El servicio es idempotente, así que volver a
     * pasar por Completado no descuenta dos veces.
     */
    private function syncStock(WorkOrder $order, ?WorkOrderStatus $from, WorkOrderStatus $to): void
    {
        $cerrados = [WorkOrderStatus::Completed, WorkOrderStatus::Delivered];

        $estabaCerrada = $from !== null && in_array($from, $cerrados, true);
        $quedaCerrada  = in_array($to, $cerrados, true);

        if ($quedaCerrada) {
            $this->stockService->consume($order);

            return;
        }

        // Se reabrió una orden que ya había descontado: devolvemos las piezas.
        if ($estabaCerrada) {
            $this->stockService->revert($order);
        }
    }
}
