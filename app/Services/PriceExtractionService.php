<?php

namespace App\Services;

use App\Jobs\ProcessPriceListJob;
use App\Models\ExternalLabTestPrice;
use App\Models\InventoryItem;
use App\Models\VendorPriceItem;
use App\Models\VendorPriceList;

class PriceExtractionService
{
    /**
     * Mark the price list as processing and dispatch the extraction job.
     */
    public function queueExtraction(VendorPriceList $priceList): void
    {
        $priceList->update(['status' => 'processing']);
        ProcessPriceListJob::dispatch($priceList);
    }

    /**
     * Apply approved price items to the correct tables.
     *
     * Pharmaceutical vendors → update inventory_items.purchase_price only.
     * External lab vendors   → create/update ExternalLabTestPrice records.
     * NEVER touches service_catalog.
     *
     * @param  array<int>  $approvedItemIds  VendorPriceItem IDs selected by the human reviewer
     * @return int Number of items successfully applied
     */
    public function applyExtractedPrices(VendorPriceList $priceList, array $approvedItemIds): int
    {
        $applied = 0;
        $vendor  = $priceList->vendor;

        $items = VendorPriceItem::whereIn('id', $approvedItemIds)
            ->where('vendor_price_list_id', $priceList->id)
            ->where('applied', false)
            ->get();

        foreach ($items as $item) {
            if ($vendor->category === 'pharmaceutical' || $vendor->category === 'lab_supplies') {
                // Update purchase_price only — selling_price is never touched
                if ($item->inventory_item_id && $item->detected_price !== null) {
                    InventoryItem::where('id', $item->inventory_item_id)
                        ->update(['purchase_price' => $item->detected_price]);
                    $applied++;
                }
            } elseif ($vendor->category === 'external_lab') {
                // Write to external_lab_test_prices, never service_catalog
                $labId = $item->external_lab_id ?? $priceList->external_lab_id;
                if ($labId && $item->detected_price !== null) {
                    ExternalLabTestPrice::updateOrCreate(
                        [
                            'external_lab_id' => $labId,
                            'test_name'       => $item->item_name,
                            'effective_from'  => now()->toDateString(),
                        ],
                        [
                            'price'               => $item->detected_price,
                            'source_price_list_id' => $priceList->id,
                            'is_active'           => true,
                        ]
                    );
                    $applied++;
                }
            }

            $item->update([
                'applied'     => true,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        }

        $priceList->update([
            'status'       => 'applied',
            'applied_at'   => now(),
            'applied_by'   => auth()->id(),
            'applied_count' => $applied,
        ]);

        return $applied;
    }
}
