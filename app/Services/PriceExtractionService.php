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
     * Pharmaceutical/lab_supplies vendors:
     *   - Matched items  → update inventory_items.purchase_price
     *   - Unmatched items → CREATE new InventoryItem tagged to this vendor
     * External lab vendors → create/update ExternalLabTestPrice records.
     * NEVER touches service_catalog. NEVER changes selling_price on existing items.
     *
     * @param  array<int>     $approvedItemIds  VendorPriceItem IDs selected by the human reviewer
     * @param  array<int,string>  $itemDepartments  Map of VendorPriceItem.id => department for new items
     * @return int Number of items successfully applied
     */
    public function applyExtractedPrices(VendorPriceList $priceList, array $approvedItemIds, array $itemDepartments = [], array $itemPrices = [], array $deniedItemIds = []): int
    {
        $applied = 0;
        $vendor  = $priceList->vendor;

        // Mark explicitly denied items as closed — no inventory/price change
        if (! empty($deniedItemIds)) {
            VendorPriceItem::whereIn('id', $deniedItemIds)
                ->where('vendor_price_list_id', $priceList->id)
                ->where('applied', false)
                ->update([
                    'applied'     => true,
                    'needs_review' => false,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'detected_price' => null,
                ]);
        }

        $items = VendorPriceItem::whereIn('id', $approvedItemIds)
            ->where('vendor_price_list_id', $priceList->id)
            ->where('applied', false)
            ->get();

        foreach ($items as $item) {
            // Use owner-entered price override if provided, otherwise fall back to detected price
            $effectivePrice = $itemPrices[$item->id] ?? $item->detected_price;

            if ($vendor->category === 'pharmaceutical' || $vendor->category === 'lab_supplies') {
                if ($effectivePrice === null || $effectivePrice <= 0) {
                    continue;
                }

                if ($item->inventory_item_id) {
                    // Matched — update purchase_price only, never touching selling_price
                    InventoryItem::where('id', $item->inventory_item_id)
                        ->update(['purchase_price' => $effectivePrice]);
                    $applied++;
                } else {
                    // Unmatched — create new inventory item tagged to this vendor
                    $department = $itemDepartments[$item->id]
                        ?? $this->inferDepartment($vendor->category);

                    $sku = $item->sku_detected
                        ?? ('VND-' . $vendor->id . '-' . $item->id . '-' . substr(md5($item->item_name), 0, 6));

                    $newItem = InventoryItem::create([
                        'name'                  => $item->item_name,
                        'sku'                   => $sku,
                        'unit'                  => $item->pack_size ?? $item->unit_detected ?? 'pcs',
                        'department'            => $department,
                        'purchase_price'        => $effectivePrice,
                        'selling_price'         => $effectivePrice, // department head sets margin later
                        'vendor_id'             => $vendor->id,
                        'minimum_stock_level'   => 5,
                        'requires_prescription' => false,
                        'is_active'             => true,
                    ]);

                    // Back-link so future price lists can match this item
                    $item->inventory_item_id = $newItem->id;
                    $item->current_price     = $effectivePrice;
                    $applied++;
                }
            } elseif ($vendor->category === 'external_lab') {
                // Write to external_lab_test_prices, never service_catalog
                $labId = $item->external_lab_id ?? $priceList->external_lab_id;
                if ($labId && $effectivePrice !== null && $effectivePrice > 0) {
                    ExternalLabTestPrice::updateOrCreate(
                        [
                            'external_lab_id' => $labId,
                            'test_name'       => $item->item_name,
                            'effective_from'  => now()->toDateString(),
                        ],
                        [
                            'price'               => $effectivePrice,
                            'source_price_list_id' => $priceList->id,
                            'is_active'           => true,
                        ]
                    );
                    $applied++;
                }
            }

            $updateData = [
                'applied'     => true,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ];
            if ($item->inventory_item_id) {
                $updateData['inventory_item_id'] = $item->inventory_item_id;
            }
            if ($item->current_price) {
                $updateData['current_price'] = $item->current_price;
            }
            $item->update($updateData);
        }

        $priceList->update([
            'status'       => 'applied',
            'applied_at'   => now(),
            'applied_by'   => auth()->id(),
            'applied_count' => $applied,
        ]);

        return $applied;
    }

    private function inferDepartment(string $vendorCategory): string
    {
        return match ($vendorCategory) {
            'pharmaceutical' => 'pharmacy',
            'lab_supplies'   => 'lab',
            default          => 'general',
        };
    }
}
