<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedido 4: se retiran los estados 'diagnosis' y 'esperando piezas' del flujo de
 * órdenes de trabajo. Las órdenes que estuvieran en esos estados hay que moverlas,
 * porque el cast del modelo a WorkOrderStatus lanza ValueError al hidratar un valor
 * que ya no existe en el enum.
 *
 * En prod (17/08/2026) esto es un no-op: 0 órdenes en esos estados (había 2 received,
 * 1 completed y 32 delivered). Aplica sobre todo a las bases de desarrollo, donde la
 * factory vieja sí generaba órdenes en 'diagnosis'.
 *
 * NO se toca work_order_status_history: es un registro de auditoría y reescribirlo
 * falsearía el historial real de las órdenes. WorkOrderStatus::labelFor() se encarga
 * de mostrar esos estados retirados sin romper.
 */
return new class extends Migration
{
    /** Estado retirado => estado al que se mueve. Transforma, no borra. */
    private const MAPPING = [
        'diagnosis'     => 'received',   // todavía no se había empezado a trabajar
        'waiting_parts' => 'repairing',  // el auto ya estaba en el taller, en curso
    ];

    public function up(): void
    {
        foreach (self::MAPPING as $retired => $replacement) {
            DB::table('work_orders')
                ->where('status', $retired)
                ->update(['status' => $replacement]);
        }
    }

    public function down(): void
    {
        // Irreversible a propósito: una vez unificados los estados no hay forma de
        // saber cuáles eran 'diagnosis' y cuáles 'waiting_parts'. El historial en
        // work_order_status_history queda intacto y sirve como registro de lo que pasó.
    }
};
