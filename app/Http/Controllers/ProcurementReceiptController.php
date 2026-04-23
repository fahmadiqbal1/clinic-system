<?php

namespace App\Http\Controllers;

use App\Models\ProcurementRequest;
use App\Notifications\ProcurementStatusUpdated;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
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
     * Record receipt of inventory procurement with three-way reconciliation.
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
            'quantities_received' => 'required|array',
            'quantities_received.*' => 'required|integer|min:0',
            'quantities_invoiced' => 'nullable|array',
            'quantities_invoiced.*' => 'nullable|integer|min:0',
            'unit_prices_invoiced' => 'nullable|array',
            'unit_prices_invoiced.*' => 'nullable|numeric|min:0',
        ]);

        $procurementRequest->load('items');

        // Build data maps
        $unitPrices = [];
        $quantitiesReceived = [];
        $invoiceData = [];

        foreach ($procurementRequest->items as $item) {
            if ($item->inventory_item_id === null) {
                return back()->withErrors([
                    'error' => "Inventory procurement item has null inventory_item_id. Data integrity error.",
                ]);
            }

            if (!isset($validated['unit_prices'][$item->id])) {
                return back()->withErrors(['error' => "Unit price missing for item {$item->id}"]);
            }

            $unitPrices[$item->id] = (float) $validated['unit_prices'][$item->id];
            $quantitiesReceived[$item->id] = (int) ($validated['quantities_received'][$item->id] ?? $item->quantity_requested);

            // Invoice data (from OCR + user verification)
            $inv = [];
            if (isset($validated['quantities_invoiced'][$item->id]) && $validated['quantities_invoiced'][$item->id] !== null) {
                $inv['quantity_invoiced'] = (int) $validated['quantities_invoiced'][$item->id];
            }
            if (isset($validated['unit_prices_invoiced'][$item->id]) && $validated['unit_prices_invoiced'][$item->id] !== null) {
                $inv['unit_price_invoiced'] = (float) $validated['unit_prices_invoiced'][$item->id];
            }
            if (!empty($inv)) {
                $invoiceData[$item->id] = $inv;
            }
        }

        try {
            $this->procurementService->receiveProcurement(
                $procurementRequest,
                $unitPrices,
                $quantitiesReceived,
                $invoiceData,
            );

            // Update received_at timestamp
            $procurementRequest->update(['received_at' => now()]);

            // Notify the requester
            $procurementRequest->requester?->notify(
                new ProcurementStatusUpdated($procurementRequest, ProcurementStatusUpdated::EVENT_RECEIVED)
            );

            return redirect()->route('procurement.show', $procurementRequest)
                ->with('success', 'Procurement received and stock updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Upload supplier invoice file via AJAX (Step 1 of wizard).
     */
    public function uploadInvoice(Request $request, ProcurementRequest $procurementRequest): JsonResponse
    {
        $this->authorize('receive', $procurementRequest);

        $validated = $request->validate([
            'receipt_invoice' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $path = $request->file('receipt_invoice')->store(
            'procurement-invoices/' . $procurementRequest->id,
            'public'
        );

        $procurementRequest->update(['receipt_invoice_path' => $path]);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }
}
