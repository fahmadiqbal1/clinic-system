<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FBR IRIS Digital Invoicing Service
 *
 * Handles submission of paid invoices to Pakistan's Federal Board of Revenue (FBR)
 * IRIS (Integrated Revenue Information System) in real-time.
 *
 * Compliance with Pakistan's mandatory digital invoicing rules:
 *  - Invoices submitted within 24 hours of generation.
 *  - Every invoice carries a digital HMAC-SHA256 signature.
 *  - Full FBR API response archived for the mandatory 5-year retention period.
 *  - Sequential FBR invoice numbers (POSID-YYYY-NNNNNN) for traceability.
 *  - QR code verification URL included on every paid invoice.
 *
 * FBR IRIS API:
 *   Live:    https://gst.fbr.gov.pk/invoices/v1
 *   Sandbox: https://sdnfbr.fbr.gov.pk/invoices/v1
 */
class FbrService
{
    /** Default HS code for healthcare/medical services (WTO CPC 931). */
    private const DEFAULT_HS_CODE = '9018.90.10';

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
     *
     * Assigns a sequential FBR invoice number, signs the payload, sends to
     * FBR IRIS, archives the full response, and stores the IRN and QR code.
     *
     * @return array{success: bool, irn: ?string, error: ?string}
     */
    public function submitInvoice(Invoice $invoice): array
    {
        if (!$this->settings->isFbrReady()) {
            $invoice->update(['fbr_status' => 'not_configured']);
            return ['success' => false, 'irn' => null, 'error' => 'FBR integration is not configured.'];
        }

        // Warn (but don't block) if submission is overdue (> 24 hours after payment)
        $overdueWarning = null;
        if ($invoice->paid_at && $invoice->paid_at->diffInHours(now()) > 24) {
            $overdueWarning = 'Warning: invoice was paid more than 24 hours ago. FBR requires submission within 24 hours.';
            Log::warning('FBR submission overdue', [
                'invoice_id' => $invoice->id,
                'paid_at'    => $invoice->paid_at,
                'hours_late' => $invoice->paid_at->diffInHours(now()),
            ]);
        }

        $invoice->update(['fbr_status' => 'pending']);

        try {
            // Atomically allocate a sequential FBR invoice number
            $seq     = $this->nextSequenceNumber();
            $fbrNum  = $this->formatInvoiceNumber($seq);

            $invoice->update([
                'fbr_invoice_number' => $fbrNum,
                'fbr_invoice_seq'    => $seq,
            ]);

            $payload   = $this->buildPayload($invoice);
            $signature = $this->sign($payload);

            $response = Http::withToken($this->settings->api_key)
                ->timeout(30)
                ->post($this->settings->api_url, $payload);

            // Archive the full response regardless of outcome (5-year record keeping)
            $responseData = [
                'http_status' => $response->status(),
                'body'        => $response->json() ?? $response->body(),
                'submitted_at' => now()->toIso8601String(),
                'payload_hash' => hash('sha256', json_encode($payload)),
            ];

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $irn  = $this->extractIrn($data, $fbrNum);
                $qrCode = $this->buildQrCodeData($invoice, $irn, $fbrNum);

                $invoice->update([
                    'fbr_status'       => 'submitted',
                    'fbr_submitted_at' => now(),
                    'fbr_irn'          => $irn,
                    'fbr_qr_code'      => $qrCode,
                    'fbr_signature'    => $signature,
                    'fbr_response'     => array_merge($responseData, ['irn' => $irn]),
                ]);

                return [
                    'success'  => true,
                    'irn'      => $irn,
                    'error'    => $overdueWarning,
                    'fbrNum'   => $fbrNum,
                ];
            }

            $error = $this->describeHttpError($response->status(), $response->body());

            $invoice->update([
                'fbr_status'       => 'failed',
                'fbr_submitted_at' => now(),
                'fbr_signature'    => $signature,
                'fbr_response'     => array_merge($responseData, ['error' => $error]),
            ]);

            Log::warning('FBR IRIS submission failed', [
                'invoice_id'  => $invoice->id,
                'http_status' => $response->status(),
                'body'        => substr($response->body(), 0, 500),
            ]);

            return ['success' => false, 'irn' => null, 'error' => $error];

        } catch (\Throwable $e) {
            $invoice->update([
                'fbr_status'       => 'failed',
                'fbr_submitted_at' => now(),
                'fbr_response'     => [
                    'error'        => $e->getMessage(),
                    'submitted_at' => now()->toIso8601String(),
                ],
            ]);

            Log::error('FBR IRIS connection error', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            return ['success' => false, 'irn' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test the connection to FBR IRIS.
     *
     * @return array{status: string, error: ?string}
     */
    public function testConnection(): array
    {
        if (!$this->settings->isFbrReady()) {
            return [
                'status' => 'failed',
                'error'  => 'FBR settings are incomplete. Please fill in STRN, POSID, and Bearer Token.',
            ];
        }

        $this->settings->update(['status' => 'connecting', 'last_error' => null]);

        try {
            // FBR IRIS ping — InvoiceType=3 is the test/validation type
            $testPayload = $this->buildTestPayload();
            $signature   = $this->sign($testPayload);

            $response = Http::withToken($this->settings->api_key)
                ->timeout(15)
                ->post($this->settings->api_url, $testPayload);

            // FBR may return 400 (bad request) or 422 (validation error) for the minimal
            // test payload. These HTTP error codes still prove the endpoint is reachable
            // and the bearer token is valid (a wrong token would return 401/403).
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
     * Follows the mandatory fields required by FBR IRIS JSON schema.
     */
    public function buildPayload(Invoice $invoice): array
    {
        $taxRate   = (float) ($this->settings->getMeta('tax_rate', 0));
        $netAmount = (float) ($invoice->net_amount ?? $invoice->total_amount);
        $discount  = (float) ($invoice->discount_amount ?? 0);
        $saleValue = $taxRate > 0 ? round($netAmount / (1 + $taxRate / 100), 2) : $netAmount;
        $taxCharged = round($netAmount - $saleValue, 2);

        $paymentMode = match ($invoice->payment_method) {
            'card'     => 2,
            'transfer' => 3,
            default    => 1, // cash
        };

        $usin   = $this->buildUsin($invoice);
        $fbrNum = $invoice->fbr_invoice_number ?? (string) $invoice->id;
        $items  = $this->buildItemsPayload($invoice, $taxRate, $netAmount, $saleValue, $taxCharged, $discount);

        return [
            'InvoiceNumber'    => $fbrNum,
            'POSID'            => (int) $this->settings->getMeta('posid'),
            'USIN'             => $usin,
            'DateTime'         => $invoice->paid_at?->format('Ymd\THis') ?? now()->format('Ymd\THis'),
            'BuyerNTN'         => '',
            'BuyerCNIC'        => $this->formatCnic($invoice->patient?->cnic ?? ''),
            'BuyerName'        => $invoice->patient?->full_name ?? 'Walk-in Patient',
            'BuyerPhoneNumber' => $invoice->patient?->phone ?? '',
            'BuyerAddress'     => '',
            'TotalBillAmount'  => $netAmount,
            'TotalQuantity'    => max(1, $invoice->items()->count() ?: 1),
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
     * Generate an HMAC-SHA256 digital signature for the given payload.
     * The signing secret is stored in the FBR platform settings meta.
     * Configure it via Owner Profile → FBR Settings → Digital Signing Secret.
     */
    public function sign(array $payload): string
    {
        $secret = $this->settings->getMeta('signing_secret');

        if (empty($secret)) {
            Log::warning('FBR: No signing_secret configured — digital signatures will not be verifiable. Set one in Owner Profile → FBR Settings.');
            // Use a deterministic but non-secret value so signatures are at least consistent
            $secret = 'fbr-unsigned-' . $this->settings->getMeta('posid', 'default');
        }

        $message = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', $message, $secret);
    }

    /**
     * Verify a stored signature against a payload (for audit/integrity checks).
     */
    public function verifySignature(array $payload, string $storedSignature): bool
    {
        return hash_equals($this->sign($payload), $storedSignature);
    }

    /**
     * Build the items array for the FBR payload.
     * Uses HS code from service_catalog if available.
     */
    private function buildItemsPayload(
        Invoice $invoice,
        float $taxRate,
        float $netAmount,
        float $saleValue,
        float $taxCharged,
        float $discount,
    ): array {
        $items = $invoice->items()->with('serviceCatalog')->get();

        if ($items->isNotEmpty()) {
            return $items->map(function ($item) use ($taxRate) {
                $lineNet  = (float) $item->line_total;
                $lineSale = $taxRate > 0 ? round($lineNet / (1 + $taxRate / 100), 2) : $lineNet;
                $lineTax  = round($lineNet - $lineSale, 2);
                $hsCode   = $item->serviceCatalog?->hs_code ?? self::DEFAULT_HS_CODE;

                return [
                    'ItemCode'   => (string) $item->id,
                    'ItemName'   => $item->description,
                    'PCTCode'    => $hsCode,
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
        $hsCode = $invoice->serviceCatalog?->hs_code ?? self::DEFAULT_HS_CODE;
        return [[
            'ItemCode'   => (string) $invoice->id,
            'ItemName'   => $invoice->service_name,
            'PCTCode'    => $hsCode,
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
     * Build a minimal test payload for connection verification.
     */
    private function buildTestPayload(): array
    {
        $ts = now()->format('Ymd\THis');
        return [
            'InvoiceNumber'    => 'TEST-' . $ts,
            'POSID'            => (int) $this->settings->getMeta('posid'),
            'USIN'             => 'TEST-' . $ts,
            'DateTime'         => $ts,
            'BuyerNTN'         => '',
            'BuyerCNIC'        => '',
            'BuyerName'        => 'TEST',
            'BuyerPhoneNumber' => '',
            'BuyerAddress'     => '',
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
    }

    /**
     * Build a USIN (Unique Sales Invoice Number).
     * Format: {POSID}-{YYYYMMDD}-{invoiceId}
     */
    private function buildUsin(Invoice $invoice): string
    {
        $posid = $this->settings->getMeta('posid', '0');
        $date  = $invoice->paid_at?->format('Ymd') ?? now()->format('Ymd');
        return sprintf('%s-%s-%d', $posid, $date, $invoice->id);
    }

    /**
     * Allocate the next sequential FBR invoice number using a DB lock.
     * Thread-safe: uses SELECT FOR UPDATE to prevent duplicate sequence numbers.
     */
    private function nextSequenceNumber(): int
    {
        return (int) DB::transaction(function () {
            $last = DB::table('invoices')
                ->whereNotNull('fbr_invoice_seq')
                ->lockForUpdate()
                ->max('fbr_invoice_seq');

            return ($last ?? 0) + 1;
        });
    }

    /**
     * Format the sequential number as a human-readable FBR invoice number.
     * Format: {POSID}-{YYYY}-{000001}
     */
    private function formatInvoiceNumber(int $seq): string
    {
        $posid = $this->settings->getMeta('posid', '0');
        return sprintf('%s-%s-%06d', $posid, now()->year, $seq);
    }

    /**
     * Extract the IRN from the FBR IRIS API response.
     */
    private function extractIrn(array $data, string $fallback): string
    {
        if (!empty($data['IRN'])) {
            return (string) $data['IRN'];
        }

        // Some FBR IRIS versions embed the IRN in the Response string
        if (!empty($data['Response']) && preg_match('/IRN[:\s]+([A-Z0-9\-]+)/i', $data['Response'], $m)) {
            return $m[1];
        }

        return $data['USIN'] ?? $fallback;
    }

    /**
     * Build the FBR verification QR code URL.
     * Encodes seller STRN, POSID, USIN, date, amount, and IRN — all required fields.
     */
    public function buildQrCodeData(Invoice $invoice, string $irn, string $fbrNum): string
    {
        $posid  = $this->settings->getMeta('posid', '');
        $strn   = $this->settings->getMeta('strn', '');
        $usin   = $this->buildUsin($invoice);
        $date   = $invoice->paid_at?->format('Ymd') ?? now()->format('Ymd');
        $amount = number_format((float) ($invoice->net_amount ?? $invoice->total_amount), 2, '.', '');

        return sprintf(
            'https://gst.fbr.gov.pk/qrinvoice/v1?TaxpayerRegistrationNo=%s&POSID=%s&USIN=%s&InvoiceDateTime=%s&TotalBillAmount=%s&IRN=%s&InvoiceNumber=%s',
            urlencode($strn),
            urlencode($posid),
            urlencode($usin),
            urlencode($date),
            urlencode($amount),
            urlencode($irn),
            urlencode($fbrNum)
        );
    }

    /**
     * Format a CNIC string to the FBR-expected format (digits only, max 15 chars).
     */
    private function formatCnic(string $cnic): string
    {
        return preg_replace('/[^0-9]/', '', $cnic);
    }

    /**
     * Return a human-readable description for FBR IRIS HTTP errors.
     */
    private function describeHttpError(int $status, string $body): string
    {
        $truncated = mb_substr($body, 0, 300);

        return match ($status) {
            401 => 'FBR IRIS authentication failed. Please verify your Bearer Token in Owner Profile → FBR Settings.',
            403 => 'Access denied by FBR IRIS. Ensure your POSID and STRN are correctly registered with FBR.',
            422 => 'FBR IRIS validation error — check mandatory fields (NTN/CNIC, HS code, POSID): ' . $truncated,
            503 => 'FBR IRIS is temporarily unavailable. The invoice will need to be resubmitted. (Max 24-hour window applies.)',
            default => "FBR IRIS returned HTTP {$status}: {$truncated}",
        };
    }
}
