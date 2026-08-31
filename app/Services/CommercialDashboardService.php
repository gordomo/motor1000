<?php

namespace App\Services;

use App\Scopes\TenantScope;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\WorkOrder;
use Carbon\CarbonInterface;

/**
 * Números del tablero comercial, con las definiciones que dio el cliente:
 *
 *   - Presupuestado: lo que se cotizó en el período.
 *   - Por cobrar: orden COMPLETADA = trabajo terminado listo para cobrar.
 *   - Cobrado: orden ENTREGADA = cobro realizado. Se mide por los cobros
 *     registrados y su fecha real, no por la fecha de entrega, porque el adelanto
 *     del presupuesto entra antes.
 *   - Las órdenes gratis no cuentan como plata, pero se muestran aparte para que
 *     el trabajo hecho sin cargo no quede invisible.
 *
 * Reemplaza la lógica anterior, que medía todo sobre Invoice y daba siempre cero:
 * en prod no hay ni una factura emitida.
 */
class CommercialDashboardService
{
    public function metrics(int $tenantId, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        return [
            'presupuestado' => $this->presupuestado($tenantId, $desde, $hasta),
            'por_cobrar'    => $this->porCobrar($tenantId),
            'cobrado'       => $this->cobrado($tenantId, $desde, $hasta),
            'rubros'        => $this->rubros($tenantId, $desde, $hasta),
            'gratis'        => $this->gratis($tenantId, $desde, $hasta),
        ];
    }

    /** Lo cotizado en el período y en qué terminó. */
    private function presupuestado(int $tenantId, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $base = fn () => Quote::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$desde, $hasta]);

        $total     = (float) $base()->sum('total');
        $cantidad  = $base()->count();
        $aprobados = $base()->where('status', QuoteStatus::Accepted->value);

        return [
            'monto'          => $total,
            'cantidad'       => $cantidad,
            'monto_aprobado' => (float) $aprobados->sum('total'),
            'aprobados'      => $base()->where('status', QuoteStatus::Accepted->value)->count(),
            'pendientes'     => $base()->where('status', QuoteStatus::Pending->value)->count(),
            'rechazados'     => $base()->where('status', QuoteStatus::Rejected->value)->count(),
            // Qué proporción de lo presupuestado se convirtió en trabajo.
            'conversion'     => $cantidad > 0
                ? round($base()->where('status', QuoteStatus::Accepted->value)->count() / $cantidad * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Plata que el taller tiene para cobrar, sin filtrar por fecha: lo que se debe
     * importa hoy, sin importar cuándo se hizo el trabajo. Son dos situaciones:
     *
     *  - Trabajo terminado que todavía no se entregó.
     *  - Autos ya entregados con saldo pendiente, porque el cliente pagó una parte.
     *
     * El segundo caso antes no aparecía en ningún lado: la orden salía de "listo
     * para cobrar" al entregarse y en "cobrado" solo figuraba lo que había pagado,
     * así que la deuda quedaba invisible.
     */
    private function porCobrar(int $tenantId): array
    {
        $ordenes = WorkOrder::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Delivered->value])
            ->where('total', '>', 0)
            ->with('payments')
            ->get()
            ->filter(fn (WorkOrder $o): bool => $o->balance() > 0);

        $terminado = $ordenes->where('status', WorkOrderStatus::Completed);
        $entregado = $ordenes->where('status', WorkOrderStatus::Delivered);

        $saldo = fn ($coleccion): float => round((float) $coleccion->sum(
            fn (WorkOrder $o): float => max(0, $o->balance())
        ), 2);

        return [
            'monto'              => $saldo($ordenes),
            'cantidad'           => $ordenes->count(),
            'terminado'          => $saldo($terminado),
            'cantidad_terminado' => $terminado->count(),
            'entregado'          => $saldo($entregado),
            'cantidad_entregado' => $entregado->count(),
        ];
    }

    /** Plata que entró en el período, por fecha real de cada cobro. */
    private function cobrado(int $tenantId, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $cobros = Payment::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->whereBetween('paid_at', [$desde, $hasta])
            ->get();

        return [
            'monto'      => round((float) $cobros->sum('amount'), 2),
            'cantidad'   => $cobros->count(),
            'adelantos'  => round((float) $cobros->where('type', 'adelanto')->sum('amount'), 2),
            'por_medio'  => $cobros->groupBy('method')
                ->map(fn ($grupo): float => round((float) $grupo->sum('amount'), 2))
                ->sortDesc()
                ->all(),
        ];
    }

    /**
     * Mano de obra vs repuestos vs otros, sobre las órdenes entregadas en el
     * período. Es el desglose que pidió el cliente; se toma de las órdenes porque
     * es donde vive el detalle, no en el cobro.
     */
    private function rubros(int $tenantId, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $ordenes = WorkOrder::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->where('status', WorkOrderStatus::Delivered->value)
            ->whereBetween('delivered_at', [$desde, $hasta])
            ->get();

        $manoDeObra = (float) $ordenes->sum('labor_cost');
        $repuestos  = (float) $ordenes->sum('parts_cost');
        $otros      = (float) $ordenes->sum(fn (WorkOrder $o): float => $o->otherCost());

        return [
            'mano_de_obra' => round($manoDeObra, 2),
            'repuestos'    => round($repuestos, 2),
            'otros'        => round($otros, 2),
            'descuentos'   => round((float) $ordenes->sum('discount'), 2),
            'total'        => round((float) $ordenes->sum('total'), 2),
            'cantidad'     => $ordenes->count(),
        ];
    }

    /** Trabajo entregado sin cargo: no es plata, pero es trabajo hecho. */
    private function gratis(int $tenantId, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $cantidad = WorkOrder::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', $tenantId)
            ->where('status', WorkOrderStatus::Delivered->value)
            ->whereBetween('delivered_at', [$desde, $hasta])
            ->where('total', '<=', 0)
            ->count();

        return ['cantidad' => $cantidad];
    }
}
