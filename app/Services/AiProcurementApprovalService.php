<?php

namespace App\Services;

use App\Models\AiActionRequest;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Notifications\ProcurementStatusUpdated;
use App\Jobs\ApplyNewItemsJob;

class AiProcurementApprovalService
{
    public const AI_AUTO_APPROVE_THRESHOLD = 25000; // PKR

    /**
     * Evaluate a newly created procurement request and either auto-approve or leave
     * pending for owner review. Also runs price-rise detection regardless of outcome.
     *
     * Called synchronously from ProcurementRequestController::store() for all types.
     */
    public function evaluate(ProcurementRequest $pr): void
    {
        $totalCost = $this->computeTotalCost($pr);

        // --- Duplicate check (new_item_request and price_list only) ---
        if (in_array($pr->type, [ProcurementRequest::TYPE_NEW_ITEM_REQUEST, ProcurementRequest::TYPE_PRICE_LIST])) {
            if ($this->hasDuplicates($pr)) {
                // Leave as pending; owner will review. Log why.
                $pr->update(['ai_approval_reason' => 'Duplicate item(s) detected — owner review required.']);
                $this->notifyOwners($pr, "Procurement #{$pr->id} needs review — possible duplicate items detected. Cost: PKR " . number_format($totalCost, 2));
                return;
            }
        }

        // --- Cost gate ---
        if ($totalCost >= self::AI_AUTO_APPROVE_THRESHOLD) {
            $pr->update(['ai_approval_reason' => 'Cost PKR ' . number_format($totalCost, 2) . ' ≥ 25,000 — owner approval required.']);
            $this->notifyOwners($pr, "Procurement #{$pr->id} ({$pr->department}) requires your approval — PKR " . number_format($totalCost, 2) . ". Review: /procurement/{$pr->id}");
            return;
        }

        // --- AUTO-APPROVE path ---
        $reason = 'Cost PKR ' . number_format($totalCost, 2) . ' < 25,000; no duplicates detected.';

        $pr->update([
            'status'              => 'approved',
            'approved_by'         => null,
            'ai_approved_at'      => now(),
            'ai_approval_reason'  => $reason,
            'receipt_deadline_at' => now()->addHours(48),
        ]);

        AiActionRequest::create([
            'requested_by_source' => 'ops_ai',
            'target_type'         => 'ProcurementRequest',
            'target_id'           => $pr->id,
            'proposed_action'     => 'auto_approve_procurement',
            'proposed_payload'    => ['total_cost' => $totalCost, 'reason' => $reason],
            'status'              => 'approved',
            'decided_at'          => now(),
            'created_at'          => now(),
        ]);

        AuditLog::log(
            'procurement.ai_auto_approved',
            ProcurementRequest::class,
            $pr->id,
            null,
            ['status' => 'approved', 'ai_approved_at' => now()->toIso8601String(), 'total_cost' => $totalCost]
        );

        // Notify the requester
        if ($pr->requester) {
            $pr->requester->notify(new ProcurementStatusUpdated($pr, ProcurementStatusUpdated::EVENT_APPROVED));
        }

        // Notify owners (advisory — AI acted on their behalf)
        $this->notifyOwners($pr, "AI auto-approved procurement #{$pr->id} ({$pr->department}) — PKR " . number_format($totalCost, 2) . ". Review: /procurement/{$pr->id}");

        // Dispatch job to create InventoryItem records for new_item_request
        if ($pr->type === ProcurementRequest::TYPE_NEW_ITEM_REQUEST) {
            ApplyNewItemsJob::dispatch($pr->id);
        }

        // --- Price rise check (runs regardless of approve/escalate, after approve path) ---
        $this->flagPriceRises($pr);
    }

    /**
     * Compute estimated total cost from items.
     * For change requests with change_payload, sum qty * unit_price from the payload.
     */
    private function computeTotalCost(ProcurementRequest $pr): float
    {
        // Standard procurement items — sum qty × quoted (or actual) unit price
        if ($pr->items()->count() > 0) {
            return (float) $pr->items->sum(fn ($i) =>
                ($i->quantity_requested ?? 0) * ($i->quoted_unit_price ?? $i->unit_price ?? 0)
            );
        }

        // Payload-based (new_item_request / price_list)
        if (!empty($pr->change_payload) && is_array($pr->change_payload)) {
            $total = 0.0;
            foreach ($pr->change_payload as $row) {
                $qty = (float) ($row['qty'] ?? $row['quantity'] ?? 1);
                $price = (float) ($row['unit_price'] ?? $row['price'] ?? 0);
                $total += $qty * $price;
            }
            return $total;
        }

        return 0.0;
    }

    /**
     * Check whether any item in the request already exists in inventory_items
     * for the same vendor AND the same name (cross-manufacturer is NOT a duplicate).
     */
    private function hasDuplicates(ProcurementRequest $pr): bool
    {
        $payload = $pr->change_payload;
        if (empty($payload) || !is_array($payload)) {
            return false;
        }

        foreach ($payload as $row) {
            $name         = trim((string) ($row['name'] ?? ''));
            $manufacturer = trim((string) ($row['manufacturer'] ?? ''));
            $dept         = $pr->department;

            if ($name === '') {
                continue;
            }

            // Only flag as duplicate when name+manufacturer+dept already exists
            if (InventoryItem::findByIdentity($name, $manufacturer, $dept) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect price rises in price_list or new_item_request payloads and create
     * an advisory AiActionRequest for owner review. Non-blocking — never prevents approval.
     */
    private function flagPriceRises(ProcurementRequest $pr): void
    {
        if (!in_array($pr->type, [ProcurementRequest::TYPE_PRICE_LIST, ProcurementRequest::TYPE_NEW_ITEM_REQUEST])) {
            return;
        }

        $payload = $pr->change_payload;
        if (empty($payload) || !is_array($payload)) {
            return;
        }

        $rises = [];

        foreach ($payload as $row) {
            $name         = trim((string) ($row['name'] ?? ''));
            $manufacturer = trim((string) ($row['manufacturer'] ?? ''));
            $newPrice     = (float) ($row['unit_price'] ?? $row['price'] ?? 0);

            if ($name === '' || $newPrice <= 0) {
                continue;
            }

            $existing = InventoryItem::findByIdentity($name, $manufacturer, $pr->department);
            if ($existing && $existing->selling_price !== null) {
                $oldPrice = (float) $existing->selling_price;
                if ($newPrice > $oldPrice + 0.01) {
                    $rises[] = [
                        'name'          => $name,
                        'manufacturer'  => $manufacturer,
                        'old_price'     => $oldPrice,
                        'new_price'     => $newPrice,
                        'increase_pct'  => round(($newPrice - $oldPrice) / max($oldPrice, 0.01) * 100, 2),
                    ];
                }
            }
        }

        if (empty($rises)) {
            return;
        }

        AiActionRequest::create([
            'requested_by_source' => 'ops_ai',
            'target_type'         => 'ProcurementRequest',
            'target_id'           => $pr->id,
            'proposed_action'     => 'flag_price_rise',
            'proposed_payload'    => ['rises' => $rises, 'count' => count($rises)],
            'status'              => 'pending',
            'created_at'          => now(),
        ]);

        $n = count($rises);
        $this->notifyOwners($pr, "Price rise detected on {$n} item(s) in procurement #{$pr->id} from {$pr->checklist_supplier}. AI has updated prices. Review: /procurement/{$pr->id}");
    }

    /**
     * Send an in-app notification to all Owner users.
     */
    private function notifyOwners(ProcurementRequest $pr, string $message): void
    {
        User::role('Owner')->get()->each(function (User $owner) use ($pr, $message): void {
            $owner->notify(new ProcurementStatusUpdated($pr, 'owner_advisory', $message));
        });
    }
}
