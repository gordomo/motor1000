<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class WorkOrderPdfController extends Controller
{
    public function __invoke(WorkOrder $workOrder): Response
    {
        abort_unless(auth()->check(), 401);
        abort_unless($workOrder->tenant_id === auth()->user()->tenant_id, 403);

        $workOrder->load([
            'tenant',
            'customer',
            'vehicle',
            'mechanic',
            'items',
        ]);

        $pdf = Pdf::loadView('pdf.work-order', [
            'workOrder' => $workOrder,
        ])->setPaper('a4');

        return $pdf->download('work-order-' . $workOrder->number . '.pdf');
    }
}
