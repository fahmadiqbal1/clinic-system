<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Prescription;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrescriptionQueueController extends Controller
{
    /**
     * Show prescriptions awaiting dispensing.
     */
    public function index(): View
    {
        $pending = Prescription::where('status', 'active')
            ->with(['patient', 'doctor', 'items'])
            ->latest()
            ->paginate(15);

        $dispensed = Prescription::where('status', 'dispensed')
            ->with(['patient', 'doctor', 'items'])
            ->latest()
            ->limit(20)
            ->get();

        return view('pharmacy.prescriptions.index', compact('pending', 'dispensed'));
    }

    /**
     * Show a single prescription detail.
     */
    public function show(Prescription $prescription): View
    {
        $prescription->load(['patient', 'doctor', 'items.inventoryItem', 'invoices']);

        $supplements = InventoryItem::where('department', 'pharmacy')
            ->where('requires_prescription', false)
            ->where('is_active', true)
            ->withSum('stockMovements as current_stock', 'quantity')
            ->orderBy('name')
            ->get()
            ->filter(fn ($item) => ($item->current_stock ?? 0) > 0)
            ->take(8)
            ->values();

        return view('pharmacy.prescriptions.show', compact('prescription', 'supplements'));
    }

    /**
     * Mark prescription as dispensed — deduct stock for items linked to inventory.
     */
    public function markDispensed(Prescription $prescription)
    {
        if ($prescription->status !== 'active') {
            return redirect()->back()->withErrors('Prescription is not in active status.');
        }

        $inventoryService = app(InventoryService::class);

        try {
            DB::transaction(function () use ($prescription, $inventoryService) {
                foreach ($prescription->items as $item) {
                    if ($item->inventory_item_id) {
                        $invItem = InventoryItem::findOrFail($item->inventory_item_id);
                        $inventoryService->recordOutbound(
                            $invItem,
                            $item->quantity,
                            'prescription',
                            $prescription->id,
                            Auth::user()
                        );
                    }
                }

                $prescription->update(['status' => 'dispensed']);
            });
        } catch (\Exception $e) {
            return redirect()->back()->withErrors('Dispensing failed: ' . $e->getMessage());
        }

        return redirect()->route('pharmacy.prescriptions.index')
            ->with('success', 'Prescription dispensed and stock deducted.');
    }
}
