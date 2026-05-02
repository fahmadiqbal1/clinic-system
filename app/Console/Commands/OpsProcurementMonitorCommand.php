<?php

namespace App\Console\Commands;

use App\Models\AiActionRequest;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Notifications\GenericOwnerAlert;
use App\Services\AiProcurementApprovalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OpsProcurementMonitorCommand extends Command
{
    protected $signature   = 'ai:ops-procurement-monitor';
    protected $description = 'Daily ops AI monitor: auto-draft procurement for critical (zero) stock; flag warning-tier items.';

    // Stock tier thresholds
    private const WATCH_MULTIPLIER = 1.5;

    public function handle(): int
    {
        [$critical, $warning, $watch] = $this->classifyStock();

        $autoDrafted = 0;
        $flagged     = 0;

        // CRITICAL (qty = 0): create draft + auto-approve if cost < 25K
        foreach ($critical as $item) {
            if ($this->hasPendingProcurement($item)) {
                continue;
            }
            $pr = $this->createDraftProcurement($item);
            if ($pr) {
                $autoDrafted++;
                app(AiProcurementApprovalService::class)->evaluate($pr->fresh());
            }
        }

        // WARNING (qty ≤ minimum): create draft only, leave pending for owner decision
        foreach ($warning as $item) {
            if ($this->hasPendingProcurement($item)) {
                continue;
            }
            $this->createDraftProcurement($item);
            $flagged++;
        }

        $criticalCount = count($critical);
        $warningCount  = count($warning);
        $watchCount    = count($watch);

        AiActionRequest::create([
            'requested_by_source' => 'ops_ai',
            'target_type'         => 'InventoryItem',
            'target_id'           => 0,
            'proposed_action'     => 'ops_daily_monitor_run',
            'proposed_payload'    => [
                'critical_count'  => $criticalCount,
                'warning_count'   => $warningCount,
                'watch_count'     => $watchCount,
                'auto_drafted'    => $autoDrafted,
                'flagged'         => $flagged,
                'run_at'          => now()->toIso8601String(),
            ],
            'status'     => 'approved',
            'decided_at' => now(),
            'created_at' => now(),
        ]);

        AuditLog::log('ops.daily_monitor_run', 'InventoryItem', 0, null, [
            'critical' => $criticalCount,
            'warning'  => $warningCount,
            'watch'    => $watchCount,
            'auto_drafted' => $autoDrafted,
        ]);

        if ($criticalCount > 0 || $warningCount > 0) {
            $message = "Ops Monitor: {$criticalCount} out of stock (auto-ordered {$autoDrafted}), "
                     . "{$warningCount} low stock (need review), {$watchCount} approaching minimum (watch list).";

            User::role('Owner')->get()->each(fn (User $owner) => $owner->notify(
                new GenericOwnerAlert($message, 'bi-robot', 'info', '/procurement', 'Daily Inventory Monitor')
            ));
        }

        $this->info("Monitor complete — Critical: {$criticalCount}, Warning: {$warningCount}, Watch: {$watchCount}. Auto-drafted: {$autoDrafted}.");
        return self::SUCCESS;
    }

    /**
     * Return three arrays: [critical, warning, watch] — each containing InventoryItem records.
     * Stock quantity is computed from stock_movements sum.
     */
    private function classifyStock(): array
    {
        $items = InventoryItem::where('is_active', true)
            ->with('stockMovements')
            ->get();

        $critical = [];
        $warning  = [];
        $watch    = [];

        foreach ($items as $item) {
            $qty     = $item->stockMovements->sum(fn ($m) => $m->type === 'purchase' ? $m->quantity : -$m->quantity);
            $minimum = $item->minimum_stock_level ?? 0;

            if ($qty <= 0) {
                $critical[] = $item;
            } elseif ($qty <= $minimum) {
                $warning[] = $item;
            } elseif ($minimum > 0 && $qty <= $minimum * self::WATCH_MULTIPLIER) {
                $watch[] = $item;
            }
        }

        return [$critical, $warning, $watch];
    }

    private function hasPendingProcurement(InventoryItem $item): bool
    {
        return ProcurementRequest::whereIn('status', ['pending', 'approved'])
            ->whereHas('items', fn ($q) => $q->where('inventory_item_id', $item->id))
            ->exists();
    }

    private function createDraftProcurement(InventoryItem $item): ?ProcurementRequest
    {
        try {
            $reorderQty = max(($item->minimum_stock_level ?? 0) * 2, 10);

            $pr = ProcurementRequest::create([
                'department'   => $item->department,
                'type'         => ProcurementRequest::TYPE_INVENTORY,
                'vendor_id'    => $item->vendor_id,
                'requested_by' => null,
                'status'       => 'pending',
                'notes'        => "Auto-draft by Ops AI Monitor — stock critical/low for: {$item->name}",
            ]);

            \App\Models\ProcurementRequestItem::create([
                'procurement_request_id' => $pr->id,
                'inventory_item_id'      => $item->id,
                'quantity_requested'     => $reorderQty,
                'quoted_unit_price'      => $item->purchase_price ?? 0,
            ]);

            return $pr;
        } catch (\Throwable $e) {
            $this->error("Failed to create draft for item #{$item->id}: {$e->getMessage()}");
            return null;
        }
    }
}
