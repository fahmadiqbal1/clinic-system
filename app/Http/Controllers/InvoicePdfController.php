<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\PdfService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoicePdfController extends Controller
{
    /**
     * Download the formatted invoice PDF (DomPDF-rendered, FBR-compliant).
     */
    public function download(Invoice $invoice, PdfService $pdfService): BinaryFileResponse
    {
        $path = $pdfService->generateInvoicePdf($invoice);

        return response()->download(storage_path('app/public/' . $path), "invoice-{$invoice->id}.pdf")
            ->deleteFileAfterSend();
    }
}
