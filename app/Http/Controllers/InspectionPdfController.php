<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * PDF de la revisión (pedido 17). Mismo criterio de autorización que el
 * presupuesto: requiere login y que la revisión sea del taller del usuario.
 */
class InspectionPdfController extends Controller
{
    public function __invoke(Inspection $inspection): Response
    {
        return $this->render($inspection, download: true);
    }

    /** Stream para previsualizar e imprimir desde el navegador. */
    public function stream(Inspection $inspection): Response
    {
        return $this->render($inspection, download: false);
    }

    private function render(Inspection $inspection, bool $download): Response
    {
        abort_unless(auth()->check(), 401);
        abort_unless($inspection->tenant_id === auth()->user()->tenant_id, 403);

        $inspection->load(['tenant', 'customer', 'vehicle']);

        $pdf = Pdf::loadView('pdf.inspection', ['inspection' => $inspection])->setPaper('a4');
        $filename = 'revision-' . $inspection->code . '.pdf';

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
