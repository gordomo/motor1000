<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class QuotePdfController extends Controller
{
    public function __invoke(Quote $quote): Response
    {
        abort_unless(auth()->check(), 401);
        abort_unless($quote->tenant_id === auth()->user()->tenant_id, 403);

        $quote->load(['tenant', 'customer', 'vehicle']);

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
        ])->setPaper('a4');

        return $pdf->download('presupuesto-' . $quote->code . '.pdf');
    }

    // Retorna el PDF como stream para previsualización (WhatsApp/Email link)
    public function stream(Quote $quote): Response
    {
        abort_unless(auth()->check(), 401);
        abort_unless($quote->tenant_id === auth()->user()->tenant_id, 403);

        $quote->load(['tenant', 'customer', 'vehicle']);

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
        ])->setPaper('a4');

        return $pdf->stream('presupuesto-' . $quote->code . '.pdf');
    }
}
