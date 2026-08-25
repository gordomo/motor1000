<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Pedido 5: los repuestos que consume una orden de trabajo descuentan del
 * inventario.
 *
 * El descuento ocurre al pasar la orden a Completado, no al cargar el ítem: hasta
 * que el trabajo no está hecho, el repuesto puede cambiar. Cada ítem impactado
 * queda marcado con stock_applied_at, así una orden no descuenta dos veces.
 *
 * Todo pasa por InventoryMovement, que ya ajusta el stock por sí solo y deja el
 * movimiento registrado: el inventario nunca se edita a mano desde acá, para que
 * el historial explique cada unidad que entró o salió.
 */
class WorkOrderStockService
{
    /**
     * Descuenta del inventario los repuestos de la orden que todavía no impactaron.
     *
     * @return int cantidad de ítems descontados
     */
    public function consume(WorkOrder $order): int
    {
        return DB::transaction(function () use ($order): int {
            $items = $order->items()
                ->whereNotNull('inventory_item_id')
                ->whereNull('stock_applied_at')
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                InventoryMovement::create([
                    'tenant_id'         => $order->tenant_id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'type'              => 'out',
                    'quantity'          => $item->quantity,
                    'unit_cost'         => $item->inventoryItem?->cost_price ?? 0,
                    'reason'            => __('Consumo de la orden :number', ['number' => $order->number]),
                    'work_order_id'     => $order->id,
                    'user_id'           => auth()->id(),
                ]);

                $item->forceFill(['stock_applied_at' => now()])->saveQuietly();
            }

            return $items->count();
        });
    }

    /**
     * Devuelve al inventario lo consumido por la orden, con un movimiento de
     * entrada compensatorio. No se borran los movimientos originales: el
     * historial tiene que mostrar que salió y volvió.
     *
     * @return int cantidad de ítems devueltos
     */
    public function revert(WorkOrder $order): int
    {
        return DB::transaction(function () use ($order): int {
            $items = $order->items()
                ->whereNotNull('inventory_item_id')
                ->whereNotNull('stock_applied_at')
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                InventoryMovement::create([
                    'tenant_id'         => $order->tenant_id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'type'              => 'in',
                    'quantity'          => $item->quantity,
                    'unit_cost'         => $item->inventoryItem?->cost_price ?? 0,
                    'reason'            => __('Devolución por reapertura de la orden :number', ['number' => $order->number]),
                    'work_order_id'     => $order->id,
                    'user_id'           => auth()->id(),
                ]);

                $item->forceFill(['stock_applied_at' => null])->saveQuietly();
            }

            return $items->count();
        });
    }

    /**
     * Repuestos de la orden que dejarían el stock en negativo.
     *
     * No bloquea el cierre: si el mecánico ya usó la pieza, el consumo es un
     * hecho y esconderlo haría que el inventario mienta. Se avisa para que
     * alguien corrija el stock.
     *
     * @return list<array{item: WorkOrderItem, disponible: float, necesario: float}>
     */
    public function shortages(WorkOrder $order): array
    {
        $faltantes = [];

        $items = $order->items()
            ->whereNotNull('inventory_item_id')
            ->whereNull('stock_applied_at')
            ->with('inventoryItem')
            ->get();

        foreach ($items as $item) {
            $disponible = (float) ($item->inventoryItem?->stock_quantity ?? 0);
            $necesario  = (float) $item->quantity;

            if ($necesario > $disponible) {
                $faltantes[] = [
                    'item'       => $item,
                    'disponible' => $disponible,
                    'necesario'  => $necesario,
                ];
            }
        }

        return $faltantes;
    }
}
