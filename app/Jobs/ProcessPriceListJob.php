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
        $data         = null;

        // Try AI sidecar for PDF/image files (with JWT auth)
        if ($sidecarUrl && $priceList->file_type !== 'csv') {
            try {
                $response = Http::timeout(60)
                    ->withToken($this->mintJwt())
                    ->attach('file', $fileContents, $priceList->original_filename)
                    ->post("{$sidecarUrl}/v1/price-extract", [
                        'vendor_category' => $vendor->category,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                } else {
                    Log::warning('ProcessPriceListJob: sidecar returned error', [
                        'price_list_id' => $priceList->id,
                        'status'        => $response->status(),
                        'body'          => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('ProcessPriceListJob: sidecar unavailable', [
                    'price_list_id' => $priceList->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        // Local CSV fallback — works for CSV uploads, or if sidecar failed on a CSV
        if ($data === null && $priceList->file_type === 'csv') {
            $data = $this->parseCsvFallback($fileContents);
        }

        // For PDF/image: if sidecar is down, mark as pending_review so the owner
        // can retry once the sidecar is available, rather than hard-failing.
        if ($data === null && $priceList->file_type !== 'csv') {
            $priceList->update([
                'status'       => 'pending_sidecar',
                'flag_reasons' => ['sidecar_unavailable_on_first_attempt'],
            ]);
            Log::info('ProcessPriceListJob: PDF queued as pending_sidecar — will retry when sidecar is available', [
                'price_list_id' => $priceList->id,
            ]);
            return;
        }

        if ($data === null) {
            $this->markFailed(['sidecar_unavailable', 'no_fallback_for_file_type']);
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
                'pack_size'             => $item['pack_size'] ?? $item['unit'] ?? null,
                'unit_detected'         => $item['pack_size'] ?? $item['unit'] ?? null,
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

    /**
     * Parse a CSV file locally — no sidecar required.
     * Expects columns: name/item, price/unit_price/rate, sku (optional), unit (optional).
     *
     * @return array{items: array<array{item_name: string, price: float|null, sku: string|null, unit: string|null, confidence: float}>}
     */
    private function parseCsvFallback(string $csvContents): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $csvContents)));
        if (empty($lines)) {
            return ['items' => []];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Map common column aliases
        $nameCol  = $this->resolveColumn($header, ['name', 'item', 'product', 'description', 'item_name', 'medicine']);
        $priceCol = $this->resolveColumn($header, ['price', 'unit_price', 'rate', 'cost', 'mrp', 'tp']);
        $skuCol   = $this->resolveColumn($header, ['sku', 'code', 'item_code', 'barcode', 'article']);
        $unitCol  = $this->resolveColumn($header, ['unit', 'pack', 'pack_size', 'uom']);

        if ($nameCol === null) {
            return ['items' => []];
        }

        $items = [];
        foreach ($lines as $line) {
            $row  = str_getcsv(trim((string) $line));
            $name = $nameCol !== null && isset($row[$nameCol]) ? trim($row[$nameCol]) : null;
            if (! $name) {
                continue;
            }

            $price = null;
            if ($priceCol !== null && isset($row[$priceCol])) {
                $raw   = preg_replace('/[^\d.]/', '', $row[$priceCol]);
                $price = $raw !== '' ? (float) $raw : null;
            }

            $items[] = [
                'item_name'   => $name,
                'price'       => $price,
                'sku'         => $skuCol !== null ? ($row[$skuCol] ?? null) : null,
                'unit'        => $unitCol !== null ? ($row[$unitCol] ?? null) : null,
                'confidence'  => ($name && $price !== null) ? 0.85 : 0.5,
                'needs_review' => $price === null,
            ];
        }

        return ['items' => $items];
    }

    private function mintJwt(): string
    {
        $now    = time();
        $secret = config('clinic.sidecar_jwt_secret', '');

        $b64url = fn(string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        $header  = $b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $b64url(json_encode([
            'sub'  => 'system',
            'role' => 'Owner',
            'iat'  => $now,
            'exp'  => $now + 300,
        ]));
        $sig = $b64url(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

        return "{$header}.{$payload}.{$sig}";
    }

    private function resolveColumn(array $header, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $idx = array_search($alias, $header, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }
        return null;
    }

    private function markFailed(array $reasons): void
    {
        $this->priceList->update([
            'status'       => 'failed',
            'flag_reasons' => $reasons,
        ]);
    }
}
