<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\User;
use App\Notifications\InvoiceStatusChanged;
use App\Services\AuditableService;
use App\Services\FinancialDistributionService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * List pharmacy invoices.
     */
    public function index(): View
    {
        $invoices = Invoice::where('department', 'pharmacy')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->with(['patient', 'items'])
            ->latest()
            ->paginate(10);

        return view('pharmacy.invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Show a specific pharmacy invoice.
     */
    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['patient', 'items.inventoryItem', 'prescribingDoctor', 'prescription.items']);

        // Load all active pharmacy items with current stock in one query
        $pharmacyItems = InventoryItem::where('department', 'pharmacy')
            ->where('is_active', true)
            ->withSum('stockMovements as current_stock', 'quantity')
            ->orderBy('name')
            ->get();

        $pharmacyById = $pharmacyItems->keyBy('id');

        // Build prescription availability data — match each prescribed item to inventory
        $prescriptionItems = collect();
        if ($invoice->prescription) {
            $prescriptionItems = $invoice->prescription->items->map(function ($presItem) use ($pharmacyItems, $pharmacyById) {
                // 1) Direct ID link
                $invItem = $presItem->inventory_item_id
                    ? $pharmacyById->get($presItem->inventory_item_id)
                    : null;

                // 2) Fuzzy name match fallback
                if (!$invItem) {
                    $needle = strtolower($presItem->medication_name);
                    $invItem = $pharmacyItems->first(
                        fn ($i) => str_contains(strtolower($i->name), $needle)
                            || str_contains($needle, strtolower($i->name))
                    );
                }

                $currentStock = (int) ($invItem?->current_stock ?? 0);

                return [
                    'medication_name' => $presItem->medication_name,
                    'dosage'          => $presItem->dosage,
                    'frequency'       => $presItem->frequency,
                    'duration'        => $presItem->duration,
                    'quantity'        => $presItem->quantity,
                    'instructions'    => $presItem->instructions,
                    'inventory_item'  => $invItem,
                    'in_stock'        => $invItem && $currentStock >= $presItem->quantity,
                    'current_stock'   => $currentStock,
                ];
            });
        }

        return view('pharmacy.invoices.show', [
            'invoice'           => $invoice,
            'pharmacyItems'     => $pharmacyItems,
            'prescriptionItems' => $prescriptionItems,
        ]);
    }

    /**
     * Start work on a pharmacy invoice.
     *
     * Invoice must be paid before work can begin.
     */
    public function startWork(Invoice $invoice): RedirectResponse
    {
        $this->authorize('transitionStatus', $invoice);

        if (!$invoice->isPaid()) {
            return redirect()->back()
                ->withErrors('Invoice must be paid before work can begin.');
        }

        if ($invoice->performed_by_user_id) {
            return redirect()->back()
                ->withErrors('Work has already been started on this order.');
        }

        $invoice->update(['performed_by_user_id' => Auth::id()]);

        AuditableService::logInvoiceStatusChange($invoice, 'paid', 'paid (work started)');

        return redirect()->route('pharmacy.invoices.show', $invoice)
            ->with('success', 'Work started on this order.');
    }

    /**
     * Dispense items on a paid pharmacy invoice.
     *
     * Creates InvoiceItems, deducts stock, updates totals, and triggers
     * deferred performer commission distribution.
     */
    public function markComplete(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('transitionStatus', $invoice);

        if (!$invoice->isPaid()) {
            return redirect()->back()
                ->withErrors('Invoice must be paid before items can be dispensed.');
        }

        if (!$invoice->performed_by_user_id) {
            return redirect()->back()
                ->withErrors('You must start work before dispensing items.');
        }

        if ($invoice->items()->exists()) {
            return redirect()->back()
                ->withErrors('Items have already been dispensed for this order.');
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $inventoryService = app(InventoryService::class);

        try {
            DB::transaction(function () use ($invoice, $validated, $inventoryService) {
                $totalAmount = 0;
                $totalCogs = 0;

                foreach ($validated['items'] as $itemData) {
                    $invItem = InventoryItem::findOrFail($itemData['inventory_item_id']);
                    $qty = $itemData['quantity'];
                    $lineTotal = $invItem->selling_price * $qty;
                    $lineCogs = $invItem->weighted_avg_cost * $qty;

                    // Create invoice item
                    $invoice->items()->create([
                        'inventory_item_id' => $invItem->id,
                        'description' => $invItem->name,
                        'quantity' => $qty,
                        'unit_price' => $invItem->selling_price,
                        'cost_price' => $invItem->weighted_avg_cost,
                        'line_total' => $lineTotal,
                        'line_cogs' => $lineCogs,
                    ]);

                    // Deduct stock via InventoryService
                    $inventoryService->recordOutbound(
                        $invItem,
                        $qty,
                        'invoice',
                        $invoice->id,
                        Auth::user()
                    );

                    $totalAmount += $lineTotal;
                    $totalCogs += $lineCogs;
                }

                // Update invoice totals from actual dispensed items
                // Note: status stays 'paid' — no transition needed
                $invoice->update([
                    'total_amount' => $totalAmount,
                    'net_amount' => $totalAmount - ($invoice->discount_amount ?? 0),
                ]);
            });

            // Distribute performer commission now that work is done
            $fresh = $invoice->fresh();
            (new FinancialDistributionService())->distributePerformerCommission($fresh);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors('Dispensing failed: ' . $e->getMessage());
        }

        AuditableService::logInvoiceStatusChange($invoice, 'paid', 'paid (dispensed)');

        return redirect()->route('pharmacy.invoices.show', $invoice)
            ->with('success', 'Items dispensed successfully.');
    }

    /**
     * Collect payment at the pharmacy counter (pending → paid).
     *
     * Payment must be collected before dispensing can begin.
     */
    public function markPaid(Invoice $invoice): RedirectResponse
    {
        $this->authorize('transitionStatus', $invoice);

        if ($invoice->isPaid()) {
            return redirect()->back()
                ->withErrors('Invoice is already paid.');
        }

        if (!$invoice->canTransitionTo(Invoice::STATUS_PAID)) {
            return redirect()->back()
                ->withErrors('Invoice cannot be marked as paid from current status.');
        }

        $paymentMethod = request()->validate([
            'payment_method' => 'required|string|in:cash,card,transfer',
        ])['payment_method'];

        try {
            $invoice->markPaid($paymentMethod, Auth::id());
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }

        AuditableService::logInvoiceStatusChange($invoice->fresh(), 'pending', Invoice::STATUS_PAID);

        // Async FBR submission (queued to avoid 30s blocking call)
        \App\Jobs\SubmitInvoiceToFbrJob::dispatch($invoice->id);

        return redirect()->route('pharmacy.invoices.show', $invoice)
            ->with('success', 'Payment collected successfully.');
    }

    /**
     * Cancel an invoice (only pending or in_progress).
     */
    public function cancel(Invoice $invoice): RedirectResponse
    {
        $this->authorize('transitionStatus', $invoice);

        if (!$invoice->canTransitionTo(Invoice::STATUS_CANCELLED)) {
            return redirect()->back()
                ->withErrors('This invoice cannot be cancelled from its current status.');
        }

        $oldStatus = $invoice->status;
        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        AuditableService::logInvoiceStatusChange($invoice, $oldStatus, Invoice::STATUS_CANCELLED);

        return redirect()->route('pharmacy.invoices.show', $invoice)
            ->with('success', 'Order #' . $invoice->id . ' has been cancelled.');
    }
}
