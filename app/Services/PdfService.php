<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\DoctorPayout;
use App\Models\PlatformSetting;
use App\Models\Prescription;
use App\Models\StaffContract;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRMarkupSVG;
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
        $invoice->load(['patient', 'items.serviceCatalog', 'prescribingDoctor', 'performer', 'serviceCatalog']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'qrCode'  => $this->generateFbrQrCode($invoice),
            'fbr'     => PlatformSetting::fbr(),
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption(['isPhpEnabled' => true, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'DejaVu Sans']);

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
     * Generate a real SVG QR code image from the invoice's FBR QR data string.
     * Returns an inline SVG string ready for DomPDF embedding, or null if unavailable.
     */
    private function generateFbrQrCode(Invoice $invoice): ?string
    {
        $content = $invoice->fbr_qr_code ?? null;

        if (empty($content) || $invoice->status !== Invoice::STATUS_PAID) {
            return null;
        }

        try {
            $options = new QROptions([
                'outputInterface'       => QRMarkupSVG::class,
                'svgAddXmlHeader'       => false,
                'imageTransparent'      => false,
                'outputBase64'          => false,
            ]);

            return (new QRCode($options))->render($content);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Download invoice PDF directly.
     */
    public function downloadInvoicePdf(Invoice $invoice)
    {
        $invoice->load(['patient', 'items.serviceCatalog', 'prescribingDoctor', 'performer', 'serviceCatalog']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'qrCode'  => $this->generateFbrQrCode($invoice),
            'fbr'     => PlatformSetting::fbr(),
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption(['isPhpEnabled' => true, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'DejaVu Sans']);

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

    /**
     * Download staff contract PDF directly.
     */
    public function downloadContractPdf(StaffContract $contract)
    {
        $contract->load(['user.roles', 'creator']);

        $pdf = Pdf::loadView('pdf.contract', [
            'contract' => $contract,
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption(['isPhpEnabled' => true, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'DejaVu Sans']);

        $staffName = str_replace(' ', '-', strtolower($contract->user?->name ?? 'staff'));
        return $pdf->download("contract-{$staffName}-v{$contract->version}.pdf");
    }

    /**
     * Download payout PDF directly.
     */
    public function downloadPayoutPdf(DoctorPayout $payout)
    {
        $payout->load(['doctor.roles', 'revenueLedgers', 'creator', 'approver', 'confirmer']);

        $pdf = Pdf::loadView('pdf.payout', [
            'payout' => $payout,
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption(['isPhpEnabled' => true, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'DejaVu Sans']);

        $staffName = str_replace(' ', '-', strtolower($payout->doctor?->name ?? 'staff'));
        return $pdf->download("payout-{$payout->id}-{$staffName}.pdf");
    }
}
