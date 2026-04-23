<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use Illuminate\Support\Facades\DB;

class ProcurementService
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Receive a procurement request atomically.
     *
     * All items must be received in full (quantity_received = quantity_requested).
     * Unit prices must be provided for all items at receipt time.
     * Expense records are created immediately.
     * Stock is increased atomically.
     *
     * @param ProcurementRequest $request
     * @param array $unitPrices Map of ProcurementRequestItem::id => unit_price
     * @return void
    /**
     * Receive a procurement request with three-way reconciliation data.
     *
     * @param ProcurementRequest $request
     * @param array $unitPrices       ProcurementRequestItem ID => actual unit price
     * @param array $quantitiesReceived ProcurementRequestItem ID => actual received qty
     * @param array $invoiceData      ProcurementRequestItem ID => ['quantity_invoiced' => int, 'unit_price_invoiced' => float]
     * @throws \Exception if validation fails or already received
     */
    public function receiveProcurement(
        ProcurementRequest $request,
        array $unitPrices,
        array $quantitiesReceived = [],
        array $invoiceData = [],
    ): void
    {
        DB::transaction(function () use ($request, $unitPrices, $quantitiesReceived, $invoiceData) {
            // Validate request status allows receipt
            if (!in_array($request->status, ['approved', 'ordered'])) {
                throw new \Exception(
                    "Cannot receive procurement request with status '{$request->status}'. " .
                    "Only 'approved' or 'ordered' requests can be received."
                );
            }

            // Load items with lock
            $items = $request->items()->lockForUpdate()->get();

            if ($items->isEmpty()) {
                throw new \Exception('Procurement request has no items to receive.');
            }

            // Resolve auth user — supports both HTTP and queue/CLI contexts
            $authUser = \Illuminate\Support\Facades\Auth::user()
                ?? \App\Models\User::find($request->approved_by ?? $request->requested_by);

            // Validate unit prices provided for all items
            foreach ($items as $item) {
                $itemName = $item->inventoryItem?->name ?? "Item #{$item->inventory_item_id}";
                if (!isset($unitPrices[$item->id])) {
                    throw new \Exception(
                        "Unit price missing for item '{$itemName}' " .
                        "(ProcurementRequestItem ID: {$item->id})."
                    );
                }

                if ($unitPrices[$item->id] === null) {
                    throw new \Exception(
                        "Unit price cannot be null for item '{$itemName}' " .
                        "(ProcurementRequestItem ID: {$item->id})."
                    );
                }
            }

            // Validate not already received (atomic: no partial receipt)
            foreach ($items as $item) {
                if ($item->quantity_received !== null) {
                    $itemName = $item->inventoryItem?->name ?? "Item #{$item->inventory_item_id}";
                    throw new \Exception(
                        "Procurement request already received. Item '{$itemName}' " .
                        "has quantity_received = {$item->quantity_received}. " .
                        "Procurement receipts are atomic and cannot be repeated."
                    );
                }
            }

            // Process receipt: update quantities, create expenses, record stock
            foreach ($items as $item) {
                $unitPrice    = $unitPrices[$item->id];
                $qtyReceived  = $quantitiesReceived[$item->id] ?? $item->quantity_requested;
                $totalCost    = $qtyReceived * $unitPrice;
                $inventoryItem = $item->inventoryItem;
                $itemName     = $inventoryItem?->name ?? "Item #{$item->inventory_item_id}";
                $itemUnit     = $inventoryItem?->unit ?? 'unit';

                // Build update data
                $updateData = [
                    'quantity_received' => $qtyReceived,
                    'unit_price' => $unitPrice,
                ];

                // Store invoice data if provided
                if (isset($invoiceData[$item->id])) {
                    if (isset($invoiceData[$item->id]['quantity_invoiced'])) {
                        $updateData['quantity_invoiced'] = $invoiceData[$item->id]['quantity_invoiced'];
                    }
                    if (isset($invoiceData[$item->id]['unit_price_invoiced'])) {
                        $updateData['unit_price_invoiced'] = $invoiceData[$item->id]['unit_price_invoiced'];
                    }
                }

                $item->update($updateData);

                // Create Expense record for cost tracking
                Expense::create([
                    'department' => $request->department,
                    'patient_id' => null,
                    'invoice_id' => null,
                    'description' => "Procurement: {$itemName} ({$qtyReceived} {$itemUnit})",
                    'cost' => $totalCost,
                    'created_by' => $authUser?->id,
                ]);

                // Record stock movement (inbound) with unit cost for WAC
                if ($inventoryItem && $qtyReceived > 0) {
                    $this->inventoryService->recordInbound(
                        item: $inventoryItem,
                        quantity: $qtyReceived,
                        unitCost: (float) $unitPrice,
                        referenceType: 'procurement_request',
                        referenceId: $request->id,
                        user: $authUser ?? new \App\Models\User(['id' => 0]),
                    );
                }
            }

            // Mark procurement request as received
            $request->update([
                'status' => 'received',
            ]);
        });
    }
}
