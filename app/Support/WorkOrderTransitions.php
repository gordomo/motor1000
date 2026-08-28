<?php

namespace App\Support;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * Quién puede mover cada estado de la orden de trabajo y qué datos hacen falta,
 * según el flujo que definió el cliente:
 *
 *   Recibido      → En reparación : el mecánico, confirmando que tiene todo para
 *                                   empezar. Si falta algo, la orden queda marcada
 *                                   como trabada y no avanza.
 *   En reparación → Completado    : el mecánico, con el checklist resuelto y el
 *                                   trabajo realizado escrito.
 *   Completado    → Entregado     : el comercial, registrando el cobro. Esto es lo
 *                                   que convierte la orden en plata cobrada.
 *
 * El administrador puede hacer todo, para no quedar bloqueado si falta alguien.
 */
class WorkOrderTransitions
{
    /** Rol habilitado para cada transición, además del admin. */
    private const OWNERS = [
        'received:repairing'   => ['mechanic'],
        'repairing:completed'  => ['mechanic'],
        'completed:delivered'  => ['receptionist'],
    ];

    public static function key(WorkOrderStatus $from, WorkOrderStatus $to): string
    {
        return $from->value . ':' . $to->value;
    }

    /** Roles que pueden hacer la transición (el admin siempre puede). */
    public static function allowedRoles(WorkOrderStatus $from, WorkOrderStatus $to): array
    {
        return array_merge(['admin'], self::OWNERS[self::key($from, $to)] ?? []);
    }

    public static function userCanMove(?User $user, WorkOrderStatus $from, WorkOrderStatus $to): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(self::allowedRoles($from, $to));
    }

    /**
     * Motivo por el que la orden no puede avanzar todavía, o null si puede.
     * Es lo que se le muestra al usuario, así que va en lenguaje llano.
     */
    public static function blocker(WorkOrder $order, WorkOrderStatus $to): ?string
    {
        $from = $order->status;

        if ($from === null) {
            return null;
        }

        if ($from === WorkOrderStatus::Received && $to === WorkOrderStatus::Repairing && $order->isBlocked()) {
            return __('La orden está trabada: :motivo', ['motivo' => $order->blocked_reason]);
        }

        if ($to === WorkOrderStatus::Completed) {
            if (blank($order->work_performed)) {
                return __('Falta escribir el trabajo realizado.');
            }

            if ($pendientes = self::pendingChecklistPoints($order)) {
                return __('Faltan :n puntos del trabajo sin marcar.', ['n' => $pendientes]);
            }

            // Si algo no se pudo hacer, tiene que estar explicado.
            $sinExplicar = collect($order->workChecklist())
                ->filter(fn (array $punto): bool => $punto['estado'] === \App\Models\WorkOrder::PUNTO_NO_SE_PUDO
                    && blank($punto['aclaracion']))
                ->count();

            if ($sinExplicar) {
                return __('Hay :n puntos marcados como "no se pudo" sin explicar por qué.', ['n' => $sinExplicar]);
            }
        }

        return null;
    }

    /**
     * Puntos del checklist sin resolver. Un punto está resuelto si tiene estado,
     * cualquiera sea: si el mecánico no lo pudo hacer, lo marca como tal y aclara.
     */
    public static function pendingChecklistPoints(WorkOrder $order): int
    {
        return collect($order->workChecklist())
            ->filter(fn (array $punto): bool => blank($punto['estado']))
            ->count();
    }
}
