<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class InventoryService
{
    /**
     * Record an inbound stock movement with weighted average cost (WAC) update.
     *
     * WAC formula: new_avg = ((old_qty × old_avg) + (new_qty × new_cost)) / (old_qty + new_qty)
     *
     * @param InventoryItem $item
     * @param int $quantity Positive integer
     * @param float $unitCost Cost per unit for this purchase
     * @param string $referenceType e.g., 'procurement_request', 'manual'
     * @param int $referenceId
     * @param User $user
     * @return StockMovement
     */
    public function recordInbound(
        InventoryItem $item,
        int $quantity,
        float $unitCost,
        string $referenceType,
        int $referenceId,
        User $user
    ): StockMovement {
        return DB::transaction(function () use ($item, $quantity, $unitCost, $referenceType, $referenceId, $user) {
            // Lock the item for concurrent safety
            $lockedItem = InventoryItem::where('id', $item->id)->lockForUpdate()->first();

            $currentStock = $this->getCurrentStock($lockedItem);
            $currentAvg = (float) $lockedItem->weighted_avg_cost;

            // Weighted average cost calculation
            $absQty = abs($quantity);
            if ($currentStock + $absQty > 0) {
                $newAvg = (($currentStock * $currentAvg) + ($absQty * $unitCost)) / ($currentStock + $absQty);
            } else {
                $newAvg = $unitCost;
            }

            // Update WAC on inventory item
            $lockedItem->update([
                'weighted_avg_cost' => round($newAvg, 2),
                'purchase_price' => $unitCost, // Also update latest purchase price
            ]);

            return StockMovement::create([
                'inventory_item_id' => $lockedItem->id,
                'type' => 'purchase',
                'quantity' => $absQty, // Always positive
                'unit_cost' => $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * Record an outbound stock movement (dispense).
     * Validates sufficient stock exists before recording.
     * Returns movement with COGS snapshot at current WAC.
     *
     * @param InventoryItem $item
     * @param int $quantity Positive integer (service negates it)
     * @param string $referenceType e.g., 'invoice', 'manual'
     * @param int $referenceId
     * @param User $user
     * @return StockMovement
     * @throws \Exception if insufficient stock
     */
    public function recordOutbound(
        InventoryItem $item,
        int $quantity,
        string $referenceType,
        int $referenceId,
        User $user
    ): StockMovement {
        return DB::transaction(function () use ($item, $quantity, $referenceType, $referenceId, $user) {
            // Lock the item for concurrent safety
            $lockedItem = InventoryItem::where('id', $item->id)->lockForUpdate()->first();

            $currentStock = $this->getCurrentStock($lockedItem);

            if ($currentStock < $quantity) {
                throw new \Exception(
                    "Insufficient stock for {$lockedItem->name}. Available: {$currentStock}, Requested: {$quantity}"
                );
            }

            $movement = StockMovement::create([
                'inventory_item_id' => $lockedItem->id,
                'type' => 'dispense',
                'quantity' => -abs($quantity), // Always negative
                'unit_cost' => $lockedItem->weighted_avg_cost, // COGS snapshot at WAC
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $user->id,
            ]);

            // Fire low stock alert (throttled: once per item per 24h)
            if ($this->isBelowMinimum($lockedItem)) {
                $cacheKey = "low_stock_notified_{$lockedItem->id}";
                if (!Cache::has($cacheKey)) {
                    Cache::put($cacheKey, true, now()->addHours(24));
                    $currentStock = $this->getCurrentStock($lockedItem);
                    $recipients = User::role($lockedItem->department)->get()
                        ->merge(User::role('Owner')->get())
                        ->unique('id');
                    Notification::send($recipients, new LowStockAlert($lockedItem, $currentStock));
                }
            }

            return $movement;
        });
    }

    /**
     * Get current stock level by summing all movements for an item.
     * Ledger-based: no stored column, derived from movements only.
     */
    public function getCurrentStock(InventoryItem $item): int
    {
        return (int) StockMovement::where('inventory_item_id', $item->id)
            ->sum('quantity');
    }

    /**
     * Check if an item is below minimum stock level.
     */
    public function isBelowMinimum(InventoryItem $item): bool
    {
        return $this->getCurrentStock($item) < $item->minimum_stock_level;
    }

    /**
     * Check if sufficient stock exists to dispense.
     */
    public function hasSufficientStock(InventoryItem $item, int $quantity): bool
    {
        return $this->getCurrentStock($item) >= $quantity;
    }

    /**
     * Record a wastage (expired, damaged, or discarded stock).
     * Quantity must be positive; stored as negative in the ledger.
     *
     * @throws \Exception if insufficient stock
     */
    public function recordWastage(
        InventoryItem $item,
        int $quantity,
        string $reason,
        User $user
    ): StockMovement {
        return DB::transaction(function () use ($item, $quantity, $reason, $user) {
            $lockedItem = InventoryItem::where('id', $item->id)->lockForUpdate()->first();
            $currentStock = $this->getCurrentStock($lockedItem);

            if ($currentStock < $quantity) {
                throw new \Exception(
                    "Insufficient stock to record wastage for {$lockedItem->name}. Available: {$currentStock}, Requested: {$quantity}"
                );
            }

            return StockMovement::create([
                'inventory_item_id' => $lockedItem->id,
                'type'             => 'wastage',
                'quantity'         => -abs($quantity),
                'unit_cost'        => $lockedItem->weighted_avg_cost,
                'reference_type'   => 'manual',
                'reference_id'     => 0,
                'notes'            => $reason,
                'created_by'       => $user->id,
            ]);
        });
    }

    /**
     * Record a stock return (items returned from patient or department back to inventory).
     * Quantity must be positive; restores ledger balance.
     */
    public function recordReturn(
        InventoryItem $item,
        int $quantity,
        string $reason,
        User $user
    ): StockMovement {
        return DB::transaction(function () use ($item, $quantity, $reason, $user) {
            $lockedItem = InventoryItem::where('id', $item->id)->lockForUpdate()->first();

            return StockMovement::create([
                'inventory_item_id' => $lockedItem->id,
                'type'             => 'return',
                'quantity'         => abs($quantity), // Positive — restores stock
                'unit_cost'        => $lockedItem->weighted_avg_cost,
                'reference_type'   => 'manual',
                'reference_id'     => 0,
                'notes'            => $reason,
                'created_by'       => $user->id,
            ]);
        });
    }
}
