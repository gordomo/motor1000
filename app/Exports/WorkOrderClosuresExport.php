<?php

namespace App\Exports;

use App\Scopes\TenantScope;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Support\CurrentTenant;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Histórico de órdenes cerradas en Excel (pedido nuevo).
 *
 * Una fila por orden: el usuario puede filtrar y hacer tablas dinámicas con esto.
 * Para un comprobante que nadie pueda tocar está la exportación a PDF: proteger
 * una hoja de Excel no sirve como garantía, se saltea en dos clics.
 */
class WorkOrderClosuresExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly CarbonInterface $desde,
        private readonly CarbonInterface $hasta,
    ) {}

    public function collection()
    {
        // Misma definición que el informe en pantalla: si la orden no registró
        // cuándo se completó, se usa la fecha de entrega.
        $fechaCierre = 'COALESCE(work_orders.completed_at, work_orders.delivered_at)';

        return WorkOrder::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', CurrentTenant::id() ?? 0)
            ->whereIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Delivered->value])
            ->where(fn ($q) => $q->whereNotNull('completed_at')->orWhereNotNull('delivered_at'))
            ->whereRaw($fechaCierre . ' BETWEEN ? AND ?', [$this->desde, $this->hasta])
            ->with(['customer', 'vehicle', 'mechanic'])
            ->orderByRaw($fechaCierre)
            ->get()
            ->map(fn (WorkOrder $o): array => [
                $o->number,
                ($o->completed_at ?? $o->delivered_at)?->format('d/m/Y'),
                ($o->completed_at ?? $o->delivered_at)?->format('H:i'),
                $o->created_at?->format('d/m/Y'),
                $o->created_at && ($cierre = $o->completed_at ?? $o->delivered_at)
                    ? $o->created_at->diffInDays($cierre)
                    : null,
                $o->customer?->name ?? '—',
                $o->vehicle?->license_plate ?? '—',
                trim(($o->vehicle?->brand ?? '') . ' ' . ($o->vehicle?->model ?? '')) ?: '—',
                $o->mechanic?->name ?? '—',
                $o->status?->getLabel() ?? '—',
                (float) $o->labor_cost,
                (float) $o->parts_cost,
                (float) $o->total,
            ]);
    }

    public function headings(): array
    {
        return [
            'Orden',
            'Fecha de cierre',
            'Hora',
            'Fecha de ingreso',
            'Días en taller',
            'Cliente',
            'Patente',
            'Vehículo',
            'Mecánico',
            'Estado',
            'Mano de obra',
            'Repuestos',
            'Total',
        ];
    }

    public function title(): string
    {
        return 'Cierres ' . $this->desde->format('d-m-Y') . ' a ' . $this->hasta->format('d-m-Y');
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
