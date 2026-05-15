<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        abort_unless(auth()->check(), 401);
        abort_unless($invoice->tenant_id === auth()->user()->tenant_id, 403);

        $invoice->load([
            'tenant',
            'customer',
            'workOrder',
        ]);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
        ])->setPaper('a4');

        return $pdf->download('invoice-' . $invoice->number . '.pdf');
    }
}
