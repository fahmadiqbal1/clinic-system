<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VendorPriceItem;
use App\Models\VendorPriceList;
use App\Notifications\GenericOwnerAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessPriceListJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public readonly VendorPriceList $priceList) {}

    public function handle(): void
    {
        $priceList = $this->priceList;
        $vendor    = $priceList->vendor;

        // Read the uploaded file from private storage
        if (! Storage::disk('local')->exists($priceList->file_path)) {
            $this->fail("File not found: {$priceList->file_path}");
            return;
        }

        $fileContents = Storage::disk('local')->get($priceList->file_path);
        $sidecarUrl   = config('clinic.sidecar_url');

        try {
            $response = Http::timeout(60)
                ->attach('file', $fileContents, $priceList->original_filename)
                ->post("{$sidecarUrl}/v1/price-extract", [
                    'vendor_category' => $vendor->category,
                ]);

            if (! $response->successful()) {
                $this->markFailed(['sidecar_error', "HTTP {$response->status()}"]);
                return;
            }

            $data = $response->json();
        } catch (\Throwable $e) {
            Log::warning('ProcessPriceListJob: sidecar unavailable', [
                'price_list_id' => $priceList->id,
                'error'         => $e->getMessage(),
            ]);
            $this->markFailed(['sidecar_unavailable']);
            return;
        }

        $extractedItems = $data['items'] ?? [];
        $itemCount      = count($extractedItems);
        $flaggedCount   = 0;

        foreach ($extractedItems as $item) {
            $itemName   = trim($item['item_name'] ?? '');
            $price      = isset($item['price']) ? (float) $item['price'] : null;
            $confidence = (float) ($item['confidence'] ?? 0.75);
            $needsReview = (bool) ($item['needs_review'] ?? false) || $confidence < 0.7;

            if ($needsReview) {
                $flaggedCount++;
            }

            $priceItemData = [
                'vendor_price_list_id'  => $priceList->id,
                'item_name'             => $itemName,
                'sku_detected'          => $item['sku'] ?? null,
                'unit_detected'         => $item['unit'] ?? null,
                'detected_price'        => $price,
                'confidence'            => $confidence,
                'needs_review'          => $needsReview,
                'applied'               => false,
            ];

            // Pharmaceutical: attempt to match inventory item by name
            if (in_array($vendor->category, ['pharmaceutical', 'lab_supplies'], true)) {
                if ($itemName && $confidence >= 0.8) {
                    $match = \App\Models\InventoryItem::where('name', 'LIKE', "%{$itemName}%")
                        ->first();
                    if ($match) {
                        $priceItemData['inventory_item_id'] = $match->id;
                        $priceItemData['current_price']     = $match->purchase_price;
                    }
                }
            }

            // External lab: tag with external_lab_id and normalise test name
            if ($vendor->category === 'external_lab' && $priceList->external_lab_id) {
                $priceItemData['external_lab_id']       = $priceList->external_lab_id;
                $priceItemData['test_name_normalized']  = strtolower(trim($itemName));
            }

            VendorPriceItem::create($priceItemData);
        }

        $newStatus = $flaggedCount > 0 ? 'flagged' : 'extracted';

        $priceList->update([
            'status'        => $newStatus,
            'extracted_at'  => now(),
            'item_count'    => $itemCount,
            'flagged_count' => $flaggedCount,
        ]);

        // Notify Owner
        $owners = User::role('Owner')->get();
        $message = "Price list from {$vendor->name}: {$itemCount} items extracted"
            . ($flaggedCount > 0 ? ", {$flaggedCount} need review" : ', all items ready to apply');

        foreach ($owners as $owner) {
            $owner->notify(new GenericOwnerAlert(
                message: $message,
                icon:    'bi-file-earmark-text',
                color:   $flaggedCount > 0 ? 'warning' : 'success',
                url:     "/owner/vendors/price-list/{$priceList->id}/review",
                title:   'Price List Extracted',
            ));
        }
    }

    private function markFailed(array $reasons): void
    {
        $this->priceList->update([
            'status'       => 'failed',
            'flag_reasons' => $reasons,
        ]);
    }
}
