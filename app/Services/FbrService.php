<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FBR IRIS Digital Invoicing Service
 *
 * Handles submission of paid invoices to Pakistan's Federal Board of Revenue (FBR)
 * IRIS (Integrated Revenue Information System) in real-time.
 *
 * FBR IRIS API documentation:
 *   Live:    https://gst.fbr.gov.pk/invoices/v1
 *   Sandbox: https://sdnfbr.fbr.gov.pk/invoices/v1
 *
 * Each paid invoice is submitted with seller details (NTN/STRN), buyer info,
 * itemised service breakdown, and tax amounts. FBR responds with an IRN
 * (Invoice Reference Number) which is stored on the invoice.
 * A QR code is generated from the IRN for printing on receipts.
 */
class FbrService
{
    private PlatformSetting $settings;

    public function __construct(PlatformSetting $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Create a new FbrService instance loaded with the persisted FBR settings.
     */
    public static function make(): self
    {
        return new self(PlatformSetting::fbr());
    }

    /**
     * Submit a paid invoice to FBR IRIS.
     * Updates fbr_status, fbr_irn, fbr_qr_code, fbr_submitted_at on the invoice.
     *
     * @return array{success: bool, irn: ?string, error: ?string}
     */
    public function submitInvoice(Invoice $invoice): array
    {
        if (!$this->settings->isFbrReady()) {
            $invoice->update(['fbr_status' => 'not_configured']);
            return ['success' => false, 'irn' => null, 'error' => 'FBR integration is not configured.'];
        }

        $invoice->update(['fbr_status' => 'pending']);

        try {
            $payload = $this->buildPayload($invoice);
            $response = Http::withToken($this->settings->api_key)
                ->timeout(30)
                ->post($this->settings->api_url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $irn = $this->extractIrn($data);
                $qrCode = $this->buildQrCodeData($invoice, $irn);

                $invoice->update([
                    'fbr_status'       => 'submitted',
                    'fbr_submitted_at' => now(),
                    'fbr_irn'          => $irn,
                    'fbr_qr_code'      => $qrCode,
                ]);

                return ['success' => true, 'irn' => $irn, 'error' => null];
            }

            $error = $this->describeHttpError($response->status(), $response->body());

            $invoice->update([
                'fbr_status'       => 'failed',
                'fbr_submitted_at' => now(),
                'fbr_irn'          => null,
            ]);

            Log::warning('FBR IRIS submission failed', [
                'invoice_id' => $invoice->id,
                'http_status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return ['success' => false, 'irn' => null, 'error' => $error];

        } catch (\Throwable $e) {
            $invoice->update([
                'fbr_status'       => 'failed',
                'fbr_submitted_at' => now(),
            ]);

            Log::error('FBR IRIS connection error', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'irn' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test the connection to FBR IRIS by sending a minimal validation request.
     *
     * @return array{status: string, error: ?string}
     */
    public function testConnection(): array
    {
        if (!$this->settings->isFbrReady()) {
            return [
                'status' => 'failed',
                'error' => 'FBR settings are incomplete. Please fill in STRN, POSID, and Bearer Token.',
            ];
        }

        $this->settings->update(['status' => 'connecting', 'last_error' => null]);

        try {
            // FBR IRIS ping: submit a zero-amount test invoice (InvoiceType=3 = test)
            $payload = [
                'InvoiceNumber' => 'TEST-' . now()->format('YmdHis'),
                'POSID'         => (int) $this->settings->getMeta('posid'),
                'USIN'          => 'TEST-' . now()->format('YmdHis'),
                'DateTime'      => now()->format('Ymd\THis'),
                'BuyerNTN'      => '',
                'BuyerCNIC'     => '',
                'BuyerName'     => 'TEST',
                'BuyerPhoneNumber' => '',
                'BuyerAddress'  => '',
                'TotalBillAmount'  => 0,
                'TotalQuantity'    => 0,
                'TotalSaleValue'   => 0,
                'TotalTaxCharged'  => 0,
                'Discount'         => 0,
                'FurtherTax'       => 0,
                'PaymentMode'      => 1,
                'RefUSIN'          => null,
                'InvoiceType'      => 3,
                'Items'            => [],
            ];

            $response = Http::withToken($this->settings->api_key)
                ->timeout(15)
                ->post($this->settings->api_url, $payload);

            // FBR may return 400/422 for test data but that still means connectivity works
            if ($response->successful() || in_array($response->status(), [400, 422], true)) {
                $this->settings->update([
                    'status'         => 'connected',
                    'last_tested_at' => now(),
                    'last_error'     => null,
                ]);
                return ['status' => 'connected', 'error' => null];
            }

            $error = $this->describeHttpError($response->status(), $response->body());
            $this->settings->update([
                'status'         => 'failed',
                'last_tested_at' => now(),
                'last_error'     => $error,
            ]);
            return ['status' => 'failed', 'error' => $error];

        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $this->settings->update([
                'status'         => 'failed',
                'last_tested_at' => now(),
                'last_error'     => $error,
            ]);
            return ['status' => 'failed', 'error' => $error];
        }
    }

    /**
     * Build the FBR IRIS invoice submission payload.
     */
    private function buildPayload(Invoice $invoice): array
    {
        $taxRate    = (float) ($this->settings->getMeta('tax_rate', 0));
        $netAmount  = (float) ($invoice->net_amount ?? $invoice->total_amount);
        $discount   = (float) ($invoice->discount_amount ?? 0);
        $saleValue  = $taxRate > 0 ? round($netAmount / (1 + $taxRate / 100), 2) : $netAmount;
        $taxCharged = round($netAmount - $saleValue, 2);

        $paymentMode = match ($invoice->payment_method) {
            'card'     => 2,
            'transfer' => 3,
            default    => 1, // cash
        };

        $usin = $this->buildUsin($invoice);

        $items = $this->buildItemsPayload($invoice, $taxRate, $netAmount, $saleValue, $taxCharged, $discount);

        return [
            'InvoiceNumber'    => $invoice->fbr_invoice_number ?? (string) $invoice->id,
            'POSID'            => (int) $this->settings->getMeta('posid'),
            'USIN'             => $usin,
            'DateTime'         => $invoice->paid_at?->format('Ymd\THis') ?? now()->format('Ymd\THis'),
            'BuyerNTN'         => '',
            'BuyerCNIC'        => $invoice->patient?->cnic ?? '',
            'BuyerName'        => $invoice->patient?->full_name ?? 'Walk-in Patient',
            'BuyerPhoneNumber' => $invoice->patient?->phone ?? '',
            'BuyerAddress'     => '',
            'TotalBillAmount'  => $netAmount,
            'TotalQuantity'    => max(1, $invoice->items()->count()),
            'TotalSaleValue'   => $saleValue,
            'TotalTaxCharged'  => $taxCharged,
            'Discount'         => $discount,
            'FurtherTax'       => 0,
            'PaymentMode'      => $paymentMode,
            'RefUSIN'          => null,
            'InvoiceType'      => 1,
            'Items'            => $items,
        ];
    }

    /**
     * Build the items array for the FBR payload.
     */
    private function buildItemsPayload(
        Invoice $invoice,
        float $taxRate,
        float $netAmount,
        float $saleValue,
        float $taxCharged,
        float $discount,
    ): array {
        $items = $invoice->items()->get();

        if ($items->isNotEmpty()) {
            return $items->map(function ($item) use ($taxRate) {
                $lineNet     = (float) $item->line_total;
                $lineSale    = $taxRate > 0 ? round($lineNet / (1 + $taxRate / 100), 2) : $lineNet;
                $lineTax     = round($lineNet - $lineSale, 2);

                return [
                    'ItemCode'   => (string) ($item->id),
                    'ItemName'   => $item->description,
                    'PCTCode'    => '9018.90.10', // Medical/healthcare services HS code
                    'Quantity'   => $item->quantity,
                    'TaxRate'    => $taxRate,
                    'Amount'     => $lineNet,
                    'Discount'   => 0,
                    'FurtherTax' => 0,
                    'FixedTax'   => 0,
                    'SaleValue'  => $lineSale,
                    'TaxCharged' => $lineTax,
                ];
            })->all();
        }

        // Fallback: single line item from invoice
        return [[
            'ItemCode'   => (string) $invoice->id,
            'ItemName'   => $invoice->service_name,
            'PCTCode'    => '9018.90.10',
            'Quantity'   => 1,
            'TaxRate'    => $taxRate,
            'Amount'     => $netAmount,
            'Discount'   => $discount,
            'FurtherTax' => 0,
            'FixedTax'   => 0,
            'SaleValue'  => $saleValue,
            'TaxCharged' => $taxCharged,
        ]];
    }

    /**
     * Build a USIN (Unique Sales Invoice Number) for the FBR payload.
     * Format: {POSID}-{YYYYMMDD}-{invoiceId}
     */
    private function buildUsin(Invoice $invoice): string
    {
        $posid = $this->settings->getMeta('posid', '0');
        $date  = $invoice->paid_at?->format('Ymd') ?? now()->format('Ymd');
        return sprintf('%s-%s-%d', $posid, $date, $invoice->id);
    }

    /**
     * Extract the IRN from the FBR IRIS API response.
     */
    private function extractIrn(array $data): string
    {
        // FBR IRIS may return IRN in different fields depending on version
        if (!empty($data['IRN'])) {
            return (string) $data['IRN'];
        }

        // Some versions embed IRN in the Response string
        if (!empty($data['Response']) && preg_match('/IRN[:\s]+([A-Z0-9\-]+)/i', $data['Response'], $m)) {
            return $m[1];
        }

        // Fallback: use USIN as the reference identifier
        return $data['USIN'] ?? ('FBR-' . now()->format('YmdHis'));
    }

    /**
     * Build QR code data string for the FBR invoice.
     * Format follows FBR IRIS verification URL standard.
     */
    public function buildQrCodeData(Invoice $invoice, string $irn): string
    {
        $posid = $this->settings->getMeta('posid', '');
        $strn  = $this->settings->getMeta('strn', '');
        $usin  = $this->buildUsin($invoice);
        $date  = $invoice->paid_at?->format('Ymd') ?? now()->format('Ymd');
        $amount = number_format((float) ($invoice->net_amount ?? $invoice->total_amount), 2, '.', '');

        // FBR verification URL format
        return sprintf(
            'https://gst.fbr.gov.pk/qrinvoice/v1?TaxpayerRegistrationNo=%s&POSID=%s&USIN=%s&InvoiceDateTime=%s&TotalBillAmount=%s&IRN=%s',
            urlencode($strn),
            urlencode($posid),
            urlencode($usin),
            urlencode($date),
            urlencode($amount),
            urlencode($irn)
        );
    }

    /**
     * Return a human-readable description for FBR IRIS HTTP errors.
     */
    private function describeHttpError(int $status, string $body): string
    {
        $truncated = mb_substr($body, 0, 300);

        return match ($status) {
            401 => 'FBR IRIS authentication failed. Please verify your Bearer Token.',
            403 => 'Access denied by FBR IRIS. Ensure your POSID and STRN are correctly registered.',
            422 => 'FBR IRIS validation error: ' . $truncated,
            503 => 'FBR IRIS is temporarily unavailable. Please try again later.',
            default => "FBR IRIS returned HTTP {$status}: {$truncated}",
        };
    }
}
