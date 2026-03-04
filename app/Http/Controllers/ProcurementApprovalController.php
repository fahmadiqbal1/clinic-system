<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\ProcurementRequest;
use App\Models\ServiceCatalog;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class ProcurementApprovalController extends Controller
{
    use AuthorizesRequests;

    /**
     * Approve a pending procurement request (Owner only)
     */
    public function approve(Request $request, ProcurementRequest $procurementRequest)
    {
        $this->authorize('approve', $procurementRequest);

        // --- Handle equipment/catalog change requests ---
        if ($procurementRequest->isChangeRequest()) {
            return $this->approveChangeRequest($procurementRequest);
        }

        // Validate service procurements have required data
        if ($procurementRequest->type === 'service') {
            $procurementRequest->load('items');

            foreach ($procurementRequest->items as $item) {
                if ($item->inventory_item_id !== null) {
                    return back()->withErrors([
                        'error' => "Service procurement item must not reference inventory. Item ID: {$item->id}",
                    ]);
                }

                if ($item->unit_price === null) {
                    return back()->withErrors([
                        'error' => "Service procurement items must have unit_price set. Item ID: {$item->id}",
                    ]);
                }
            }
        }

        // Validate inventory procurements DO have inventory_item_id
        if ($procurementRequest->type === 'inventory') {
            $procurementRequest->load('items');

            foreach ($procurementRequest->items as $item) {
                if ($item->inventory_item_id === null) {
                    return back()->withErrors([
                        'error' => "Inventory procurement item must reference an inventory item. Item ID: {$item->id}",
                    ]);
                }
            }
        }

        $procurementRequest->update([
            'status' => 'approved',
            'approved_by' => Auth::user()->id,
        ]);

        // If service type, create expense immediately
        if ($procurementRequest->type === 'service') {
            $this->createServiceExpense($procurementRequest);
        }

        return redirect()->route('procurement.show', $procurementRequest)
            ->with('success', 'Procurement request approved.');
    }

    /**
     * Reject a pending procurement request (Owner only)
     */
    public function reject(Request $request, ProcurementRequest $procurementRequest)
    {
        $this->authorize('approve', $procurementRequest);

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $procurementRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::user()->id,
            'notes' => $request->input('rejection_reason'),
        ]);

        return redirect()->route('procurement.show', $procurementRequest)
            ->with('success', 'Procurement request rejected.');
    }

    /**
     * Apply an approved equipment/catalog change request.
     */
    protected function approveChangeRequest(ProcurementRequest $req): \Illuminate\Http\RedirectResponse
    {
        $payload = $req->change_payload ?? [];
        $action = $req->change_action;

        try {
            if ($req->type === ProcurementRequest::TYPE_EQUIPMENT_CHANGE) {
                $this->applyEquipmentChange($action, $payload, $req->target_id);
            } elseif ($req->type === ProcurementRequest::TYPE_CATALOG_CHANGE) {
                $this->applyCatalogChange($action, $payload, $req->target_id);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to apply change: ' . $e->getMessage()]);
        }

        $req->update([
            'status' => 'approved',
            'approved_by' => Auth::user()->id,
        ]);

        return redirect()->route('procurement.show', $req)
            ->with('success', 'Change request approved and applied.');
    }

    /**
     * Apply equipment change (create/update/delete).
     */
    protected function applyEquipmentChange(string $action, array $payload, ?int $targetId): void
    {
        match ($action) {
            ProcurementRequest::ACTION_CREATE => Equipment::create($payload),
            ProcurementRequest::ACTION_UPDATE => Equipment::findOrFail($targetId)->update($payload),
            ProcurementRequest::ACTION_DELETE => Equipment::findOrFail($targetId)->delete(),
        };
    }

    /**
     * Apply catalog change (create/update/delete).
     */
    protected function applyCatalogChange(string $action, array $payload, ?int $targetId): void
    {
        match ($action) {
            ProcurementRequest::ACTION_CREATE => ServiceCatalog::create($payload),
            ProcurementRequest::ACTION_UPDATE => ServiceCatalog::findOrFail($targetId)->update($payload),
            ProcurementRequest::ACTION_DELETE => ServiceCatalog::findOrFail($targetId)->delete(),
        };
    }

    /**
     * Create expense for service procurement upon approval
     */
    protected function createServiceExpense(ProcurementRequest $procurementRequest): void
    {
        $totalCost = 0;

        foreach ($procurementRequest->items as $item) {
            if ($item->unit_price === null) {
                throw new \Exception(
                    "Service procurement items must have unit_price set. " .
                    "Item ID: {$item->id}"
                );
            }

            $totalCost += $item->quantity_requested * $item->unit_price;
        }

        \App\Models\Expense::create([
            'department' => $procurementRequest->department,
            'patient_id' => null,
            'invoice_id' => null,
            'description' => "Service Procurement (ID: {$procurementRequest->id})",
            'cost' => $totalCost,
            'created_by' => Auth::user()->id,
        ]);
    }
}
