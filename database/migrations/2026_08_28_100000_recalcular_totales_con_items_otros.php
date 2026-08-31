<?php

use App\Models\WorkOrder;
use App\Scopes\TenantScope;
use Illuminate\Database\Migrations\Migration;

/**
 * Repara los totales que quedaron cortos por el bug de recalculateTotal().
 *
 * El cálculo viejo sumaba solo mano de obra y repuestos, e ignoraba los ítems de
 * tipo "Otro". Se arregló en el código, pero eso solo recalcula cuando alguien
 * toca los ítems de una orden: los totales ya guardados quedaron mal.
 *
 * En prod al 28/08/2026 hay exactamente una orden afectada:
 *   WO-00008 (entregada) · guardado $584.000 · real $854.000 · faltaban $270.000
 *
 * No se inventa ningún dato: el total se recalcula desde los ítems de cada orden,
 * que son el dato original cargado por el taller. Es idempotente, así que correrla
 * de nuevo no cambia nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $corregidas = 0;

        WorkOrder::withoutGlobalScopes([TenantScope::class])
            ->with('items')
            ->chunkById(100, function ($ordenes) use (&$corregidas): void {
                foreach ($ordenes as $orden) {
                    $esperado = round((float) $orden->items->sum('total') - (float) $orden->discount, 2);

                    if (abs($esperado - round((float) $orden->total, 2)) <= 0.01) {
                        continue;
                    }

                    $orden->recalculateTotal();
                    // El estado de pago depende del total, así que se recalcula también.
                    $orden->refresh()->syncPaymentStatus();

                    $corregidas++;
                }
            });

        if ($corregidas > 0) {
            echo "  → Totales corregidos: {$corregidas}" . PHP_EOL;
        }
    }

    public function down(): void
    {
        // No hay vuelta atrás posible ni deseable: el valor anterior estaba mal
        // calculado y no se guardó en ningún lado. El total correcto siempre se
        // puede volver a derivar de los ítems de la orden.
    }
};
