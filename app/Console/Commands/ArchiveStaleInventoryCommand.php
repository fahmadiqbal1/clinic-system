<?php

namespace App\Console\Commands;

use App\Models\AiActionRequest;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Notifications\GenericOwnerAlert;
use Illuminate\Console\Command;

class ArchiveStaleInventoryCommand extends Command
{
    protected $signature   = 'inventory:archive-stale';
    protected $description = 'Soft-archive inventory items out of stock for 12+ months with no pending procurement.';

    public function handle(): int
    {
        $cutoff = now()->subMonths(12);

        $stale = InventoryItem::where('is_active', true)
            ->where(function ($q) use ($cutoff): void {
                // Out of stock for 12+ months via last_stocked_at, or since creation if never stocked
                $q->where(fn ($i) => $i->whereNotNull('last_stocked_at')->where('last_stocked_at', '<', $cutoff))
                  ->orWhere(fn ($i) => $i->whereNull('last_stocked_at')->where('created_at', '<', $cutoff));
            })
            ->get()
            ->filter(function (InventoryItem $item): bool {
                // Skip items with any active (pending/approved) procurement request
                return !ProcurementRequest::whereIn('status', ['pending', 'approved'])
                    ->whereHas('items', fn ($q) => $q->where('inventory_item_id', $item->id))
                    ->exists();
            });

        if ($stale->isEmpty()) {
            $this->info('No stale items to archive.');
            return self::SUCCESS;
        }

        $archivedIds   = [];
        $archivedNames = [];

        foreach ($stale as $item) {
            $item->update(['is_active' => false]);

            AuditLog::log(
                'inventory.auto_archived_stale',
                InventoryItem::class,
                $item->id,
                ['is_active' => true],
                ['is_active' => false, 'reason' => 'Out of stock for 12+ months']
            );

            $archivedIds[]   = $item->id;
            $archivedNames[] = $item->name . ($item->manufacturer ? " ({$item->manufacturer})" : '');
            $this->line("Archived: [{$item->id}] {$item->name}");
        }

        AiActionRequest::create([
            'requested_by_source' => 'ops_ai',
            'target_type'         => 'InventoryItem',
            'target_id'           => $archivedIds[0],
            'proposed_action'     => 'stale_item_archived',
            'proposed_payload'    => ['item_ids' => $archivedIds, 'names' => $archivedNames],
            'status'              => 'approved',
            'decided_at'          => now(),
            'created_at'          => now(),
        ]);

        $count   = count($archivedIds);
        $nameStr = implode(', ', array_slice($archivedNames, 0, 10));
        $more    = $count > 10 ? ' … and ' . ($count - 10) . ' more' : '';
        $message = "Auto-archived {$count} item(s) out of stock for 12+ months: {$nameStr}{$more}";

        User::role('Owner')->get()->each(fn (User $owner) => $owner->notify(
            new GenericOwnerAlert($message, 'bi-archive', 'warning', '/inventory', 'Stale Inventory Auto-Archived')
        ));

        $this->info("Archived {$count} stale item(s).");
        return self::SUCCESS;
    }
}
