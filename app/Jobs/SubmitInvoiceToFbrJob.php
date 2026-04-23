<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\FbrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Submit a paid invoice to FBR PRAL Digital Invoicing API asynchronously.
 *
 * This prevents the 30-second FBR HTTP call from blocking the user's request,
 * enabling the system to handle high invoice throughput without HTTP timeouts.
 */
class SubmitInvoiceToFbrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [60, 300, 600, 1800, 3600]; // 1m, 5m, 10m, 30m, 1h

    public function __construct(public int $invoiceId)
    {
    }

    public function handle(): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (!$invoice || !$invoice->isPaid()) {
            return;
        }

        // Skip if already successfully submitted
        if ($invoice->fbr_irn) {
            return;
        }

        $result = FbrService::make()->submitInvoice($invoice);

        if (!$result['success']) {
            Log::warning("FBR submission failed for invoice #{$this->invoiceId}: {$result['error']}");
            $this->release($this->backoff[$this->attempts() - 1] ?? 3600);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("FBR submission permanently failed for invoice #{$this->invoiceId}: {$exception->getMessage()}");
    }
}
