<?php

namespace App\Services\Pdf;

use App\Models\Invoice;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BulkPdfZipService
{
    public function downloadWorkOrders(Collection $records): StreamedResponse
    {
        $records = $records->filter(fn ($record) => $record instanceof WorkOrder)->values();

        return $this->zipDownload(
            $records,
            'work-orders',
            function (WorkOrder $workOrder): array {
                $workOrder->loadMissing(['tenant', 'customer', 'vehicle', 'mechanic', 'items']);

                $content = Pdf::loadView('pdf.work-order', [
                    'workOrder' => $workOrder,
                ])->setPaper('a4')->output();

                return [
                    'name' => 'work-order-' . $workOrder->number . '.pdf',
                    'content' => $content,
                ];
            }
        );
    }

    public function downloadInvoices(Collection $records): StreamedResponse
    {
        $records = $records->filter(fn ($record) => $record instanceof Invoice)->values();

        return $this->zipDownload(
            $records,
            'invoices',
            function (Invoice $invoice): array {
                $invoice->loadMissing(['tenant', 'customer', 'workOrder']);

                $content = Pdf::loadView('pdf.invoice', [
                    'invoice' => $invoice,
                ])->setPaper('a4')->output();

                return [
                    'name' => 'invoice-' . $invoice->number . '.pdf',
                    'content' => $content,
                ];
            }
        );
    }

    private function zipDownload(Collection $records, string $prefix, callable $renderer): StreamedResponse
    {
        $fileName = $prefix . '-' . now()->format('Ymd-His') . '.zip';

        return response()->streamDownload(function () use ($records, $renderer): void {
            $zipPath = tempnam(sys_get_temp_dir(), 'm1000_zip_');
            $zip = new ZipArchive();

            if ($zipPath === false || $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return;
            }

            foreach ($records as $record) {
                $file = $renderer($record);
                if (! empty($file['name']) && isset($file['content'])) {
                    $zip->addFromString($file['name'], $file['content']);
                }
            }

            $zip->close();

            $stream = fopen($zipPath, 'rb');
            if ($stream !== false) {
                fpassthru($stream);
                fclose($stream);
            }

            @unlink($zipPath);
        }, $fileName, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
