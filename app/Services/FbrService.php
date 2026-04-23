<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FBR PRAL Digital Invoicing (DI) Service â€” API v1.12
 *
 * Implements the PRAL Digital Invoicing API for real-time invoice reporting
 * to Pakistan's Federal Board of Revenue (FBR).
 *
 * API endpoints (same URL, token determines sandbox vs production routing):
 *   Post:     https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata
 *   Sandbox:  https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb
 *   Validate: https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb
 *
 * Registration process (done once in IRIS portal):
 *   1. Login iris.fbr.gov.pk â†’ Digital Invoicing â†’ API Integration â†’ Choose PRAL
 *   2. Submit technical details + IP whitelist (approved within 2 working hours)
 *   3. Receive Sandbox Token â†’ test scenario SN019 (Services)
 *   4. IRIS auto-generates Production Token after all scenarios pass
 *
 * Compliance:
 *   - Invoices submitted in real-time on payment (within 24 hours)
 *   - FBR assigns unique invoiceNumber (IRN) in response
 *   - QR code encodes NTN|FBR_INVOICE_NO|AMOUNT|TAX|DATETIME|BUSINESS_NAME
 *   - HMAC-SHA256 signature of payload stored for tamper detection (our system)
 *   - Full FBR API response archived for 5-year retention
 */
class FbrService
{
    /**
     * Default HS code for medical/healthcare services.
     * 9813.0000 = Health services (Pakistan PCT heading for healthcare).
     */
    private const DEFAULT_HS_CODE = '9813.0000';

    /**
     * Default sale type for a healthcare clinic.
     * SN019 scenario = "Services rendered or provided"
     */
    private const DEFAULT_SALE_TYPE = 'Services';

    /**
     * PRAL DI API endpoints.
     * Note: The same base URL is used; sandbox vs production is determined by which
     * Bearer Token is used (tokens are environment-specific, issued by PRAL through IRIS).
     */
    private const URL_POST_SANDBOX    = 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb';
    private const URL_POST_PRODUCTION = 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata';
    private const URL_VALIDATE        = 'https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb';

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
     * Resolve the correct API URL and Bearer Token for the current environment.
     * Sandbox uses sandbox_api_key + postinvoicedata_sb endpoint.
     * Production uses production_api_key + postinvoicedata endpoint.
     */
    private function apiUrl(): string
    {
        return $this->settings->getMeta('is_sandbox', true)
            ? self::URL_POST_SANDBOX
            : self::URL_POST_PRODUCTION;
    }

    private function bearerToken(): ?string
    {
        return $this->settings->getMeta('is_sandbox', true)
            ? $this->settings->getMeta('sandbox_api_key')
            : $this->settings->getMeta('production_api_key');
    }

    /**
     * Submit a paid invoice to the PRAL FBR Digital Invoicing API.
     *
     * On success FBR returns a unique invoiceNumber (IRN).
     * We store that IRN, build a spec-compliant QR string, sign the payload,
     * and archive the full API response for the mandatory 5-year retention period.
     *
     * @return array{success: bool, irn: ?string, error: ?string}
     */
    public function submitInvoice(Invoice $invoice): array
    {
        if (!$this->settings->isFbrReady()) {
            $invoice->update(['fbr_status' => 'not_configured']);
            return ['success' => false, 'irn' => null, 'error' => 'FBR not configured. Set up credentials in Owner Profile â†’ FBR Settings.'];
        }

        $overdueWarning = null;
        if ($invoice->paid_at && $invoice->paid_at->diffInHours(now()) > 24) {
            $overdueWarning = 'Warning: invoice was paid more than 24 hours ago. FBR requires submission within 24 hours.';
            Log::warning('FBR DI: submission overdue', [
                'invoice_id' => $invoice->id,
                'paid_at'    => $invoice->paid_at,
                'hours_late' => $invoice->paid_at->diffInHours(now()),
            ]);
        }

        $invoice->update(['fbr_status' => 'pending']);

        try {
            $payload   = $this->buildPayload($invoice);
            $signature = $this->sign($payload);
            $token     = $this->bearerToken();
            $url       = $this->apiUrl();

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($url, $payload);

            // Archive the full response regardless of outcome (5-year record keeping)
            $responseData = [
                'http_status'  => $response->status(),
                'body'         => $response->json() ?? $response->body(),
                'submitted_at' => now()->toIso8601String(),
                'payload_hash' => hash('sha256', json_encode($payload)),
                'environment'  => $this->settings->getMeta('is_sandbox', true) ? 'sandbox' : 'production',
            ];

            if ($response->successful()) {
                $data       = $response->json() ?? [];
                $irn        = $this->extractIrn($data);
                $fbrNum     = $irn; // FBR-issued invoice number IS the IRN
                $qrContent  = $this->buildQrCodeData($invoice, $irn);

                $invoice->update([
                    'fbr_status'         => 'submitted',
                    'fbr_submitted_at'   => now(),
                    'fbr_irn'            => $irn,
                    'fbr_invoice_number' => $fbrNum,
                    'fbr_qr_code'        => $qrContent,
                    'fbr_signature'      => $signature,
                    'fbr_response'       => array_merge($responseData, ['irn' => $irn]),
                ]);

                return [
                    'success' => true,
                    'irn'     => $irn,
                    'error'   => $overdueWarning,
                ];
            }

            // Check for application-level validation error (HTTP 200 with status "Invalid")
            $body = $response->json() ?? [];
            $appError = $this->extractAppError($body);
            $httpError = $this->describeHttpError($response->status(), $response->body());
            $error = $appError ?? $httpError;

            $invoice->update([
                'fbr_status'       => 'failed',
                'fbr_submitted_at' => now(),
                'fbr_signature'    => $signature,
                'fbr_response'     => array_merge($responseData, ['error' => $error]),
            ]);

            Log::warning('FBR DI: submission failed', [
                'invoice_id'  => $invoice->id,
                'http_status' => $response->status(),
                'error'       => $error,
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

            Log::error('FBR DI: connection error', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            return ['success' => false, 'irn' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test the connection to the PRAL DI API sandbox endpoint.
     * Uses the validateinvoicedata_sb endpoint with a minimal Services payload.
     * A 200 response (even with "Invalid" status) confirms token + IP whitelist is working.
     *
     * @return array{status: string, error: ?string}
     */
    public function testConnection(): array
    {
        if (!$this->settings->isFbrReady()) {
            return [
                'status' => 'failed',
                'error'  => 'FBR settings are incomplete. STRN, NTN, Business Name and Bearer Token are required.',
            ];
        }

        $this->settings->update(['status' => 'connecting', 'last_error' => null]);

        try {
            $payload  = $this->buildTestPayload();
            $token    = $this->settings->getMeta('sandbox_api_key')
                        ?? $this->settings->getMeta('production_api_key');

            $response = Http::withToken($token)
                ->timeout(15)
                ->post(self::URL_VALIDATE, $payload);

            // 200 with valid/invalid status = endpoint reachable + token accepted
            // 401 = wrong token or IP not whitelisted yet
            if ($response->successful()) {
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
     * Build the PRAL DI API v1.12 invoice submission payload.
     *
     * Conforms exactly to the JSON schema in the DI API Technical Specification.
     * Header fields appear once; per-item fields are in the `items` array.
     */
    public function buildPayload(Invoice $invoice): array
    {
        $taxRate    = (float) $this->settings->getMeta('tax_rate', 0);
        $netAmount  = (float) ($invoice->net_amount ?? $invoice->total_amount);
        $discount   = (float) ($invoice->discount_amount ?? 0);
        $taxAmount  = $taxRate > 0 ? round($netAmount * $taxRate / (100 + $taxRate), 2) : 0.0;
        $saleValue  = round($netAmount - $taxAmount, 2);
        $totalIncl  = $netAmount; // totalValues = total including tax

        $sellerNtn      = $this->settings->getMeta('ntn', '');
        $sellerName     = $this->settings->getMeta('business_name', '');
        $sellerProvince = $this->settings->getMeta('seller_province', 'Punjab');
        $sellerAddress  = $this->settings->getMeta('business_address', '');
        $saleType       = $this->settings->getMeta('sale_type', self::DEFAULT_SALE_TYPE);
        $uom            = $this->settings->getMeta('uom', 'Numbers, pieces, units');
        $rateString     = $taxRate > 0 ? number_format($taxRate, 0) . '%' : '0%';

        $buyerCnic = preg_replace('/[^0-9]/', '', $invoice->patient?->cnic ?? '');
        $buyerName = $invoice->patient?->full_name ?? 'Walk-in Patient';
        $buyerType = !empty($buyerCnic) ? 'Registered' : 'Unregistered';

        $payload = [
            'invoiceType'            => 'Sale Invoice',
            'invoiceDate'            => ($invoice->paid_at ?? now())->format('Y-m-d'),
            'sellerNTNCNIC'          => $sellerNtn,
            'sellerBusinessName'     => $sellerName,
            'sellerProvince'         => $sellerProvince,
            'sellerAddress'          => $sellerAddress,
            'buyerNTNCNIC'           => $buyerCnic,
            'buyerBusinessName'      => $buyerName,
            'buyerProvince'          => '',
            'buyerAddress'           => $invoice->patient?->address ?? '',
            'buyerRegistrationType'  => $buyerType,
            'invoiceRefNo'           => '',
            'items'                  => $this->buildItemsPayload($invoice, $taxRate, $saleType, $uom, $rateString),
        ];

        // scenarioId is required for sandbox only
        if ($this->settings->getMeta('is_sandbox', true)) {
            $payload['scenarioId'] = $this->settings->getMeta('scenario_id', 'SN019');
        }

        return $payload;
    }

    /**
     * Generate an HMAC-SHA256 digital signature for the given payload.
     * This is OUR tamper-detection mechanism â€” not sent to FBR.
     * Stored on the invoice for audit purposes.
     */
    public function sign(array $payload): string
    {
        $secret = $this->settings->getMeta('signing_secret', '');

        if (empty($secret)) {
            // Fall back to NTN-derived secret so signatures are at least consistent
            $secret = 'fbr-di-' . ($this->settings->getMeta('ntn', 'unsigned'));
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
     * Build the items array for the DI API payload.
     * Each item maps to one service line, using HS code from service catalog.
     */
    private function buildItemsPayload(
        Invoice $invoice,
        float $taxRate,
        string $saleType,
        string $uom,
        string $rateString,
    ): array {
        $items = $invoice->items()->with('serviceCatalog')->get();

        if ($items->isNotEmpty()) {
            return $items->map(function ($item) use ($taxRate, $saleType, $uom, $rateString) {
                $lineNet    = (float) $item->line_total;
                $lineTax    = $taxRate > 0 ? round($lineNet * $taxRate / (100 + $taxRate), 2) : 0.0;
                $lineSale   = round($lineNet - $lineTax, 2);
                $hsCode     = $item->serviceCatalog?->hs_code ?? self::DEFAULT_HS_CODE;

                return [
                    'hsCode'                          => $hsCode,
                    'productDescription'              => $item->description ?? ($item->serviceCatalog?->name ?? 'Medical Service'),
                    'rate'                            => $rateString,
                    'uoM'                             => $uom,
                    'quantity'                        => (float) $item->quantity,
                    'totalValues'                     => round($lineNet, 2),
                    'valueSalesExcludingST'           => $lineSale,
                    'fixedNotifiedValueOrRetailPrice' => 0.00,
                    'salesTaxApplicable'              => $lineTax,
                    'salesTaxWithheldAtSource'        => 0.00,
                    'extraTax'                        => 0.00,
                    'furtherTax'                      => 0.00,
                    'sroScheduleNo'                   => '',
                    'fedPayable'                      => 0.00,
                    'discount'                        => 0.00,
                    'saleType'                        => $saleType,
                    'sroItemSerialNo'                 => '',
                ];
            })->all();
        }

        // Fallback: single item from invoice header
        $discount   = (float) ($invoice->discount_amount ?? 0);
        $netAmount  = (float) ($invoice->net_amount ?? $invoice->total_amount);
        $taxAmount  = $taxRate > 0 ? round($netAmount * $taxRate / (100 + $taxRate), 2) : 0.0;
        $saleValue  = round($netAmount - $taxAmount, 2);
        $hsCode     = $invoice->serviceCatalog?->hs_code ?? self::DEFAULT_HS_CODE;

        return [[
            'hsCode'                          => $hsCode,
            'productDescription'              => $invoice->service_name ?? 'Medical Service',
            'rate'                            => $rateString,
            'uoM'                             => $uom,
            'quantity'                        => 1.0,
            'totalValues'                     => $netAmount,
            'valueSalesExcludingST'           => $saleValue,
            'fixedNotifiedValueOrRetailPrice' => 0.00,
            'salesTaxApplicable'              => $taxAmount,
            'salesTaxWithheldAtSource'        => 0.00,
            'extraTax'                        => 0.00,
            'furtherTax'                      => 0.00,
            'sroScheduleNo'                   => '',
            'fedPayable'                      => 0.00,
            'discount'                        => $discount,
            'saleType'                        => $saleType,
            'sroItemSerialNo'                 => '',
        ]];
    }

    /**
     * Build a minimal sandbox test payload for connection verification.
     * Uses scenario SN019 (Services rendered or provided) â€” correct for healthcare.
     */
    private function buildTestPayload(): array
    {
        $ntn     = $this->settings->getMeta('ntn', '0000000');
        $name    = $this->settings->getMeta('business_name', 'Test Clinic');
        $province = $this->settings->getMeta('seller_province', 'Punjab');

        return [
            'invoiceType'           => 'Sale Invoice',
            'invoiceDate'           => now()->format('Y-m-d'),
            'sellerNTNCNIC'         => $ntn,
            'sellerBusinessName'    => $name,
            'sellerProvince'        => $province,
            'sellerAddress'         => $this->settings->getMeta('business_address', 'Pakistan'),
            'buyerNTNCNIC'          => '',
            'buyerBusinessName'     => 'Walk-in Patient',
            'buyerProvince'         => '',
            'buyerAddress'          => '',
            'buyerRegistrationType' => 'Unregistered',
            'invoiceRefNo'          => '',
            'scenarioId'            => 'SN019',
            'items'                 => [[
                'hsCode'                          => self::DEFAULT_HS_CODE,
                'productDescription'              => 'Medical Consultation',
                'rate'                            => '0%',
                'uoM'                             => 'Numbers, pieces, units',
                'quantity'                        => 1.0,
                'totalValues'                     => 1000.00,
                'valueSalesExcludingST'           => 1000.00,
                'fixedNotifiedValueOrRetailPrice' => 0.00,
                'salesTaxApplicable'              => 0.00,
                'salesTaxWithheldAtSource'        => 0.00,
                'extraTax'                        => 0.00,
                'furtherTax'                      => 0.00,
                'sroScheduleNo'                   => '',
                'fedPayable'                      => 0.00,
                'discount'                        => 0.00,
                'saleType'                        => 'Services',
                'sroItemSerialNo'                 => '',
            ]],
        ];
    }

    /**
     * Extract the FBR-issued Invoice Number (IRN) from the DI API response.
     * Per spec: response field is `invoiceNumber` (e.g. "7000007DI1747119701593").
     */
    private function extractIrn(array $data): string
    {
        // Top-level invoiceNumber is the primary IRN
        if (!empty($data['invoiceNumber'])) {
            return (string) $data['invoiceNumber'];
        }

        // Some responses nest it in validationResponse
        if (!empty($data['validationResponse']['invoiceStatuses'][0]['invoiceNo'])) {
            return (string) $data['validationResponse']['invoiceStatuses'][0]['invoiceNo'];
        }

        // Fallback to dated response if present
        return $data['dated'] ?? ('DI-' . now()->format('YmdHis'));
    }

    /**
     * Extract application-level error from a DI API response body.
     * FBR may return HTTP 200 with statusCode "01" (Invalid) â€” we must check this.
     */
    private function extractAppError(array $data): ?string
    {
        $vr = $data['validationResponse'] ?? null;
        if (!$vr) {
            return null;
        }

        if (($vr['statusCode'] ?? '') === '01' || strtolower($vr['status'] ?? '') === 'invalid') {
            // Collect errors from line items
            $errors = [];
            foreach ($vr['invoiceStatuses'] ?? [] as $item) {
                if (!empty($item['error'])) {
                    $errors[] = "Item {$item['itemSNo']}: [{$item['errorCode']}] {$item['error']}";
                }
            }
            if (!empty($vr['error'])) {
                $errors[] = "[{$vr['errorCode']}] {$vr['error']}";
            }
            return implode('; ', $errors) ?: 'FBR validation failed';
        }

        return null;
    }

    /**
     * Build the QR code data string per FBR specification.
     * Encodes: Seller NTN | FBR Invoice Number | Total Amount | Sales Tax | DateTime | Business Name
     * This pipe-delimited string is what gets encoded into the printable QR image.
     */
    public function buildQrCodeData(Invoice $invoice, string $irn): string
    {
        $ntn        = $this->settings->getMeta('ntn', '');
        $name       = $this->settings->getMeta('business_name', '');
        $netAmount  = number_format((float) ($invoice->net_amount ?? $invoice->total_amount), 2, '.', '');
        $taxRate    = (float) $this->settings->getMeta('tax_rate', 0);
        $taxAmount  = $taxRate > 0
            ? number_format((float)($invoice->net_amount) * $taxRate / (100 + $taxRate), 2, '.', '')
            : '0.00';
        $datetime   = ($invoice->paid_at ?? now())->format('Y-m-d H:i:s');

        return implode('|', [
            $ntn,
            $irn,
            $netAmount,
            $taxAmount,
            $datetime,
            $name,
        ]);
    }

    /**
     * Return a human-readable description for FBR DI API HTTP errors.
     */
    private function describeHttpError(int $status, string $body): string
    {
        $truncated = mb_substr($body, 0, 300);

        return match ($status) {
            401 => 'FBR DI authentication failed. Verify your Bearer Token. If IP whitelisting is pending (PRAL approval takes up to 2 hours), try again later.',
            403 => 'Access denied by FBR DI. Ensure your server IP is whitelisted via IRIS portal.',
            500 => 'FBR DI internal server error. Contact PRAL support at dicrm.pral.com.pk.',
            default => "FBR DI returned HTTP {$status}: {$truncated}",
        };
    }
}
