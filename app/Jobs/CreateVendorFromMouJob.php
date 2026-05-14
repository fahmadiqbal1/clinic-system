<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VendorPriceList;
use App\Notifications\GenericOwnerAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles vendor MOU documents uploaded via the price-list flow.
 *
 * MOU processing is intentionally lightweight: we flag the document for
 * the owner to review rather than attempting automated extraction,
 * because MOU terms (payment schedule, penalty clauses, exclusivity) are
 * too variable for deterministic parsing.
 */
class CreateVendorFromMouJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(
        public readonly VendorPriceList $priceList,
        public readonly string          $classificationReason = '',
    ) {}

    public function handle(): void
    {
        $priceList = $this->priceList;
        $vendor    = $priceList->vendor;

        $priceList->update([
            'status'       => 'flagged',
            'flag_reasons' => ['document_type_mou', 'requires_manual_review'],
            'extracted_at' => now(),
            'item_count'   => 0,
            'flagged_count' => 1,
        ]);

        Log::channel('single')->info('mou_detected', [
            'price_list_id'        => $priceList->id,
            'vendor_id'            => $vendor->id,
            'vendor_name'          => $vendor->name,
            'original_filename'    => $priceList->original_filename,
            'classification_reason' => $this->classificationReason,
        ]);

        $owners  = User::role('Owner')->get();
        $message = "MOU/agreement detected from {$vendor->name}: '{$priceList->original_filename}'. "
            . "Manual review required — no items were extracted.";

        foreach ($owners as $owner) {
            $owner->notify(new GenericOwnerAlert(
                message: $message,
                icon:    'bi-file-earmark-text',
                color:   'warning',
                url:     "/owner/vendors/price-list/{$priceList->id}/review",
                title:   'MOU Document Flagged',
            ));
        }
    }
}
