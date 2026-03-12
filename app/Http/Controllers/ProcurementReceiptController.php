<?php

namespace App\Http\Controllers;

use App\Models\ProcurementRequest;
use App\Notifications\ProcurementStatusUpdated;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class ProcurementReceiptController extends Controller
{
    use AuthorizesRequests;

    protected ProcurementService $procurementService;

    public function __construct(ProcurementService $procurementService)
    {
        $this->procurementService = $procurementService;
    }

    /**
     * Show receipt form for an approved inventory procurement request
     */
    public function create(ProcurementRequest $procurementRequest)
    {
        $this->authorize('receive', $procurementRequest);

        if ($procurementRequest->type !== 'inventory') {
            abort(403, 'Only inventory procurements can be received. Service procurements are expense-only.');
        }

        if ($procurementRequest->status !== 'approved') {
            abort(403, 'Only approved procurements can be received.');
        }

        // Validate all items have inventory_item_id (domain invariant)
        $procurementRequest->load('items');
        foreach ($procurementRequest->items as $item) {
            if ($item->inventory_item_id === null) {
                abort(500, "Inventory procurement item has null inventory_item_id. Data integrity error.");
            }
        }

        return view('procurement.receive', compact('procurementRequest'));
    }

    /**
     * Record receipt of inventory procurement
     */
    public function store(Request $request, ProcurementRequest $procurementRequest)
    {
        $this->authorize('receive', $procurementRequest);

        if ($procurementRequest->type !== 'inventory') {
            abort(403, 'Only inventory procurements can be received. Service procurements are expense-only.');
        }

        if ($procurementRequest->status !== 'approved') {
            abort(403, 'Only approved procurements can be received.');
        }

        $validated = $request->validate([
            'unit_prices' => 'required|array',
            'unit_prices.*' => 'required|numeric|min:0.01',
            'receipt_invoice' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // Build unit_prices map: ProcurementRequestItem ID => price
        $unitPrices = [];
        $procurementRequest->load('items');

        foreach ($procurementRequest->items as $item) {
            // Validate inventory invariant
            if ($item->inventory_item_id === null) {
                return back()->withErrors([
                    'error' => "Inventory procurement item has null inventory_item_id. Data integrity error.",
                ]);
            }

            if (!isset($validated['unit_prices'][$item->id])) {
                return back()->withErrors([
                    'error' => "Unit price missing for item {$item->id}",
                ]);
            }

            $unitPrices[$item->id] = (float) $validated['unit_prices'][$item->id];
        }

        // Call service to perform atomic receipt
        try {
            $this->procurementService->receiveProcurement($procurementRequest, $unitPrices);

            // Handle invoice file upload after successful receipt
            if ($request->hasFile('receipt_invoice')) {
                $path = $request->file('receipt_invoice')->store(
                    'procurement-invoices/' . $procurementRequest->id,
                    'public'
                );
                $procurementRequest->update([
                    'receipt_invoice_path' => $path,
                    'received_at' => now(),
                ]);
            } else {
                $procurementRequest->update(['received_at' => now()]);
            }

            // Notify the requester that goods have been received
            $procurementRequest->requester?->notify(
                new ProcurementStatusUpdated($procurementRequest, ProcurementStatusUpdated::EVENT_RECEIVED)
            );

            return redirect()->route('procurement.show', $procurementRequest)
                ->with('success', 'Procurement received and stock updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
