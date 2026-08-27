<?php

namespace App\Actions\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Models\Payment;
use App\Models\WorkOrder;
use App\Services\CommunicationService;
use App\Services\Inventory\WorkOrderStockService;
use App\Support\WorkOrderTransitions;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateWorkOrderStatusAction
{
    public function __construct(
        private readonly CommunicationService $communicationService,
        private readonly WorkOrderStockService $stockService,
    ) {}

    /**
     * @param array{mechanic_id?: int|null, payment?: array{amount: float, method: string, type?: string, notes?: string|null}|null, force?: bool} $options
     *
     * @throws DomainException si el usuario no tiene permiso o falta algo para avanzar
     */
    public function execute(
        WorkOrder $order,
        WorkOrderStatus $newStatus,
        ?string $comment = null,
        array $options = [],
    ): WorkOrder {
        $previousStatus = $order->status;

        // Las validaciones de flujo se pueden saltear solo desde código interno
        // (por ejemplo una migración o un seeder), nunca desde la UI.
        if (! ($options['force'] ?? false)) {
            $this->guard($order, $previousStatus, $newStatus);
        }

        return DB::transaction(function () use ($order, $newStatus, $previousStatus, $comment, $options) {
            $order->actingMechanicId = $options['mechanic_id'] ?? null;

            $timestamps = match ($newStatus) {
                WorkOrderStatus::Repairing => ['started_at' => $order->started_at ?? now()],
                WorkOrderStatus::Completed => ['completed_at' => now()],
                WorkOrderStatus::Delivered => ['delivered_at' => now()],
                default                    => [],
            };

            // El mecánico que toma la orden queda asignado a ella.
            if ($newStatus === WorkOrderStatus::Repairing && ($options['mechanic_id'] ?? null)) {
                $timestamps['mechanic_id'] = $options['mechanic_id'];
            }

            $order->update(array_merge(['status' => $newStatus], $timestamps));

            if ($comment) {
                $order->statusHistory()->latest('id')->first()?->update(['comment' => $comment]);
            }

            // Empezar a trabajar destraba la orden.
            if ($newStatus === WorkOrderStatus::Repairing && $order->isBlocked()) {
                $order->unblock();
            }

            if ($newStatus === WorkOrderStatus::Completed) {
                $this->communicationService->notifyVehicleReady($order);
            }

            // Entregar es cobrar: el cliente definió que la orden entregada es un
            // cobro realizado, salvo que sea gratis.
            if ($pago = $options['payment'] ?? null) {
                $this->registerPayment($order, $pago);
            }

            $this->syncStock($order, $previousStatus, $newStatus);

            $order->refresh()->syncPaymentStatus();

            return $order->fresh();
        });
    }

    /** @throws DomainException */
    private function guard(WorkOrder $order, ?WorkOrderStatus $from, WorkOrderStatus $to): void
    {
        if ($from === null || $from === $to) {
            return;
        }

        if (! WorkOrderTransitions::userCanMove(auth()->user(), $from, $to)) {
            throw new DomainException(__('Tu usuario no puede pasar la orden de :de a :a.', [
                'de' => $from->getLabel(),
                'a'  => $to->getLabel(),
            ]));
        }

        if ($motivo = WorkOrderTransitions::blocker($order, $to)) {
            throw new DomainException($motivo);
        }
    }

    private function registerPayment(WorkOrder $order, array $pago): void
    {
        if ((float) ($pago['amount'] ?? 0) <= 0) {
            return;
        }

        Payment::create([
            'tenant_id'     => $order->tenant_id,
            'work_order_id' => $order->id,
            'type'          => $pago['type'] ?? 'saldo',
            'amount'        => $pago['amount'],
            'method'        => $pago['method'],
            'paid_at'       => $pago['paid_at'] ?? now(),
            'notes'         => $pago['notes'] ?? null,
        ]);
    }

    /**
     * El trabajo terminado descuenta los repuestos del inventario, y reabrir la
     * orden los devuelve. El servicio es idempotente.
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

        if ($estabaCerrada) {
            $this->stockService->revert($order);
        }
    }
}
