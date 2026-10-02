<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Aprueba un presupuesto (todo o una parte) y genera la orden de trabajo.
 *
 * Muchas veces el cliente no acepta el presupuesto completo. Antes había que
 * borrar a mano los ítems no aceptados (y se perdía lo cotizado), cambiar el
 * estado, guardar y volver al listado para "Generar OT". Ahora se elige qué
 * ítems aprobó: la orden sale con esos, y el presupuesto conserva todo, con
 * cada ítem marcado como aprobado o no ('aprobado' dentro de items).
 */
class AprobarPresupuestoAction
{
    /**
     * @param  list<int>|null  $indicesAprobados  posiciones de items aprobadas; null = todo
     * @param  array{mechanic_id?: ?int, estimated_at?: ?string, discount?: ?float}  $datos
     */
    public function execute(Quote $quote, ?array $indicesAprobados, array $datos = []): WorkOrder
    {
        if ($quote->hasWorkOrder()) {
            throw new InvalidArgumentException(__('Este presupuesto ya tiene una orden de trabajo.'));
        }

        $items = array_values($quote->items ?? []);
        $aprobados = $indicesAprobados === null
            ? array_keys($items)
            : array_values(array_intersect(array_map('intval', $indicesAprobados), array_keys($items)));

        if ($aprobados === []) {
            throw new InvalidArgumentException(__('Elegí al menos un ítem aprobado.'));
        }

        return DB::transaction(function () use ($quote, $items, $aprobados, $datos): WorkOrder {
            $marcados = [];
            foreach ($items as $i => $item) {
                $marcados[] = array_merge($item, ['aprobado' => in_array($i, $aprobados, true)]);
            }

            $quote->update([
                'items'       => $marcados,
                'status'      => QuoteStatus::Accepted,
                'accepted_at' => now(),
            ]);

            $wo = WorkOrder::create([
                'tenant_id'    => $quote->tenant_id,
                'customer_id'  => $quote->customer_id,
                'vehicle_id'   => $quote->vehicle_id,
                'quote_id'     => $quote->id,
                'mechanic_id'  => $datos['mechanic_id'] ?? null,
                'estimated_at' => $datos['estimated_at'] ?? null,
                // work_orders.complaint es NOT NULL y la falla detectada del
                // presupuesto es opcional.
                'complaint'    => $quote->detected_fault
                    ?: __('Generada desde el presupuesto :code', ['code' => $quote->code]),
                'status'       => 'received',
                // El KM del presupuesto manda; el del vehículo es el respaldo.
                'mileage_in'   => $quote->mileage ?: ($quote->vehicle?->mileage ?? 0),
                // En una aprobación parcial el descuento se revisa en el modal.
                'discount'     => $datos['discount'] ?? $quote->discount,
                // El mecánico trabaja los puntos que el presupuesto marcó
                // como REGULAR o MAL: los que estaban bien no se tocan.
                'checklist'    => WorkOrder::buildChecklistFromQuote($quote->checklist),
                'work_type'    => $quote->type?->value,
            ]);

            foreach ($aprobados as $i) {
                $item = $items[$i];
                $wo->items()->create([
                    'type'        => match ($item['tipo'] ?? null) {
                        'mano_de_obra' => 'labor',
                        'repuesto'     => 'part',
                        default        => 'other',
                    },
                    'description' => $item['descripcion'] ?? '',
                    'quantity'    => $item['cantidad'] ?? 1,
                    'unit_price'  => $item['precio_unitario'] ?? 0,
                    'total'       => $item['total'] ?? 0,
                ]);
            }

            $wo->recalculateTotal();

            return $wo;
        });
    }
}
