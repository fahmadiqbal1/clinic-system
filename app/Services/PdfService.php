<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Prescription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating PDF documents.
 */
class PdfService
{
    /**
     * Generate an invoice PDF.
     */
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $invoice->load(['patient', 'items.serviceCatalog', 'prescribingDoctor', 'performer']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'qrCode' => $this->generateFbrQrCode($invoice),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = "invoice-{$invoice->id}-" . now()->format('YmdHis') . '.pdf';
        $path = "invoices/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Generate a prescription PDF.
     */
    public function generatePrescriptionPdf(Prescription $prescription): string
    {
        $prescription->load(['patient', 'doctor', 'items']);

        $pdf = Pdf::loadView('pdf.prescription', [
            'prescription' => $prescription,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = "prescription-{$prescription->id}-" . now()->format('YmdHis') . '.pdf';
        $path = "prescriptions/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Generate FBR QR code data for invoice.
     * 
     * In production, this would generate a proper FBR IRIS-compliant QR code.
     */
    private function generateFbrQrCode(Invoice $invoice): ?string
    {
        // Only generate QR for paid invoices
        if ($invoice->status !== Invoice::STATUS_PAID) {
            return null;
        }

        // FBR QR code would contain invoice verification data
        $data = [
            'invoice_number' => $invoice->id,
            'amount' => $invoice->net_amount,
            'date' => $invoice->paid_at?->format('Y-m-d H:i:s'),
            'clinic_ntn' => config('app.clinic_ntn'),
        ];

        return base64_encode(json_encode($data));
    }

    /**
     * Download invoice PDF directly.
     */
    public function downloadInvoicePdf(Invoice $invoice)
    {
        $invoice->load(['patient', 'items.serviceCatalog', 'prescribingDoctor', 'performer']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'qrCode' => $this->generateFbrQrCode($invoice),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("invoice-{$invoice->id}.pdf");
    }

    /**
     * Download prescription PDF directly.
     */
    public function downloadPrescriptionPdf(Prescription $prescription)
    {
        $prescription->load(['patient', 'doctor', 'items']);

        $pdf = Pdf::loadView('pdf.prescription', [
            'prescription' => $prescription,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("prescription-{$prescription->id}.pdf");
    }
}
