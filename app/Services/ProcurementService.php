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
     * @throws \Exception if validation fails or already received
     */
    public function receiveProcurement(ProcurementRequest $request, array $unitPrices): void
    {
        DB::transaction(function () use ($request, $unitPrices) {
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

            // Validate unit prices provided for all items
            foreach ($items as $item) {
                if (!isset($unitPrices[$item->id])) {
                    throw new \Exception(
                        "Unit price missing for item '{$item->inventoryItem->name}' " .
                        "(ProcurementRequestItem ID: {$item->id})."
                    );
                }

                if ($unitPrices[$item->id] === null) {
                    throw new \Exception(
                        "Unit price cannot be null for item '{$item->inventoryItem->name}' " .
                        "(ProcurementRequestItem ID: {$item->id})."
                    );
                }
            }

            // Validate not already received (atomic: no partial receipt)
            foreach ($items as $item) {
                if ($item->quantity_received !== null) {
                    throw new \Exception(
                        "Procurement request already received. Item '{$item->inventoryItem->name}' " .
                        "has quantity_received = {$item->quantity_received}. " .
                        "Procurement receipts are atomic and cannot be repeated."
                    );
                }
            }

            // Process receipt: update quantities, create expenses, record stock
            foreach ($items as $item) {
                $unitPrice = $unitPrices[$item->id];
                $totalCost = $item->quantity_requested * $unitPrice;

                // Atomically set quantity_received and unit_price together
                $item->update([
                    'quantity_received' => $item->quantity_requested,
                    'unit_price' => $unitPrice,
                ]);

                // Create Expense record for cost tracking
                Expense::create([
                    'department' => $request->department,
                    'patient_id' => null,
                    'invoice_id' => null,
                    'description' => "Procurement: {$item->inventoryItem->name} ({$item->quantity_requested} {$item->inventoryItem->unit})",
                    'cost' => $totalCost,
                    'created_by' => \Illuminate\Support\Facades\Auth::user()->id,
                ]);

                // Record stock movement (inbound) with unit cost for WAC
                $this->inventoryService->recordInbound(
                    item: $item->inventoryItem,
                    quantity: $item->quantity_requested,
                    unitCost: (float) $unitPrice,
                    referenceType: 'procurement_request',
                    referenceId: $request->id,
                    user: \Illuminate\Support\Facades\Auth::user()
                );
            }

            // Mark procurement request as received
            $request->update([
                'status' => 'received',
            ]);
        });
    }
}
