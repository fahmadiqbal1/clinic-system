<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Notifications\ProcurementStatusUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplyNewItemsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $procurementRequestId) {}

    public function handle(): void
    {
        $pr = ProcurementRequest::find($this->procurementRequestId);
        if (!$pr) {
            return;
        }

        $rows = match ($pr->type) {
            ProcurementRequest::TYPE_NEW_ITEM_REQUEST => (array) ($pr->change_payload ?? []),
            ProcurementRequest::TYPE_PRICE_LIST       => (array) (($pr->price_list_diff ?? [])['added'] ?? []),
            default                                   => [],
        };

        if (empty($rows)) {
            return;
        }

        $created = 0;

        foreach ($rows as $row) {
            $name         = trim((string) ($row['name'] ?? ''));
            $manufacturer = trim((string) ($row['manufacturer'] ?? ''));
            $dept         = $pr->department;
            $newPrice     = (float) ($row['unit_price'] ?? $row['price'] ?? 0);

            if ($name === '') {
                continue;
            }

            $manufacturerTag = InventoryItem::deriveManufacturerTag($manufacturer ?: 'Unknown');

            try {
                $item = InventoryItem::firstOrCreate(
                    // Identity key — DB unique constraint is the last-resort safety net
                    ['name' => $name, 'manufacturer' => $manufacturer, 'department' => $dept],
                    // Defaults applied only on first creation
                    [
                        'sku'                 => $row['sku'] ?? null,
                        'vendor_id'           => $row['vendor_id'] ?? $pr->vendor_id ?? null,
                        'purchase_price'      => $newPrice,
                        'selling_price'       => $newPrice,
                        'unit'                => $row['unit'] ?? 'pack',
                        'pack_size'           => $row['pack_size'] ?? null,
                        'manufacturer_tag'    => $manufacturerTag,
                        'minimum_stock_level' => 5,
                        'is_active'           => true,
                    ]
                );
            } catch (\Throwable) {
                // Race condition: DB unique constraint fired — item was created by another process; skip
                continue;
            }

            if ($item->wasRecentlyCreated) {
                $created++;
                AuditLog::log(
                    'inventory.item_created_by_ai',
                    InventoryItem::class,
                    $item->id,
                    null,
                    ['name' => $name, 'manufacturer' => $manufacturer, 'department' => $dept,
                     'procurement_request_id' => $pr->id]
                );
            } else {
                // Item already existed — update price to latest checklist value
                if ($newPrice > 0) {
                    $item->update(['purchase_price' => $newPrice, 'selling_price' => $newPrice]);
                }
                // Reactivate if previously soft-archived
                if (!$item->is_active) {
                    $item->update(['is_active' => true]);
                }
            }
        }

        // Notify requester of how many items were newly added
        if ($pr->requester && $created > 0) {
            $pr->requester->notify(new ProcurementStatusUpdated(
                $pr,
                ProcurementStatusUpdated::EVENT_APPROVED,
                "{$created} new item(s) added to the inventory catalogue."
            ));
        }
    }
}
