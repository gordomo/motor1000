<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Scopes\TenantScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class QuotePdfController extends Controller
{
    public function __invoke(Quote $quote): Response
    {
        abort_unless(auth()->check(), 401);
        abort_unless($quote->tenant_id === auth()->user()->tenant_id, 403);
        // Presupuestos: admin y comercial. El mecánico no ve precios.
        abort_unless(auth()->user()->hasAnyRole(['admin', 'receptionist']), 403);

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
        // Presupuestos: admin y comercial. El mecánico no ve precios.
        abort_unless(auth()->user()->hasAnyRole(['admin', 'receptionist']), 403);

        $quote->load(['tenant', 'customer', 'vehicle']);

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
        ])->setPaper('a4');

        return $pdf->stream('presupuesto-' . $quote->code . '.pdf');
    }

    /**
     * El presupuesto que ve el cliente desde el link de WhatsApp. La firma del
     * link ya la validó el middleware 'signed', así que no hace falta login.
     * Sin el filtro por taller: si quien lo abre tiene sesión de otro taller en
     * ese navegador, igual tiene que ver el presupuesto que le mandaron.
     */
    public function publico(int $quoteId): Response
    {
        $quote = Quote::withoutGlobalScope(TenantScope::class)
            ->with(['tenant', 'customer', 'vehicle'])
            ->findOrFail($quoteId);

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
        ])->setPaper('a4');

        return $pdf->stream('presupuesto-' . $quote->code . '.pdf');
    }

    /** Link para mandarle al cliente. Sin vencimiento: puede abrirlo cuando quiera. */
    public static function linkPublico(Quote $quote): string
    {
        return URL::signedRoute('public.quotes.pdf', ['quoteId' => $quote->id]);
    }
}
