<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorPriceList;
use App\Services\PriceExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(): View
    {
        $vendors = Vendor::withCount('inventoryItems')
            ->orderBy('name')->get();
        return view('owner.vendors.index', compact('vendors'));
    }

    public function create(): View
    {
        return view('owner.vendors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'short_name'    => 'nullable|string|max:50',
            'contact_name'  => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:500',
            'payment_terms' => 'nullable|string|max:100',
            'po_email'      => 'nullable|email|max:255',
            'notes'         => 'nullable|string|max:1000',
            'auto_send_po'  => 'boolean',
            'category'      => 'required|in:pharmaceutical,lab_supplies,external_lab,general',
        ]);

        $validated['auto_send_po'] = $request->boolean('auto_send_po');
        Vendor::create($validated);

        return redirect()->route('owner.vendors.index')->with('success', 'Vendor added to approved list.');
    }

    public function edit(Vendor $vendor): View
    {
        $vendor->load('priceLists');
        return view('owner.vendors.edit', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'short_name'    => 'nullable|string|max:50',
            'contact_name'  => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:500',
            'payment_terms' => 'nullable|string|max:100',
            'po_email'      => 'nullable|email|max:255',
            'notes'         => 'nullable|string|max:1000',
            'auto_send_po'  => 'boolean',
            'is_approved'   => 'boolean',
            'category'      => 'required|in:pharmaceutical,lab_supplies,external_lab,general',
        ]);

        $validated['auto_send_po'] = $request->boolean('auto_send_po');
        $validated['is_approved']  = $request->boolean('is_approved');
        $vendor->update($validated);

        return redirect()->route('owner.vendors.index')->with('success', 'Vendor updated.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $vendor->delete();
        return redirect()->route('owner.vendors.index')->with('success', 'Vendor removed.');
    }

    /**
     * Upload a new MOU document for the vendor.
     */
    public function uploadMou(Request $request, Vendor $vendor): RedirectResponse
    {
        $request->validate([
            'mou_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file     = $request->file('mou_file');
        $path     = $file->store("private/mou/vendors/{$vendor->id}", 'local');

        $vendor->update(['mou_document_path' => $path]);

        return back()->with('success', 'MOU document uploaded successfully.');
    }

    /**
     * Upload a price list file for AI extraction.
     */
    public function uploadPriceList(Request $request, Vendor $vendor): RedirectResponse
    {
        $request->validate([
            'price_list_file' => 'required|file|mimes:pdf,jpg,jpeg,png,csv|max:20480',
        ]);

        $file         = $request->file('price_list_file');
        $originalName = $file->getClientOriginalName();
        $extension    = strtolower($file->getClientOriginalExtension());
        $fileType     = match ($extension) {
            'pdf'  => 'pdf',
            'csv'  => 'csv',
            default => 'image',
        };

        $storedPath = $file->store("private/price-lists/vendors/{$vendor->id}", 'local');

        $priceList = VendorPriceList::create([
            'vendor_id'         => $vendor->id,
            'uploaded_by'       => auth()->id(),
            'filename'          => basename($storedPath),
            'original_filename' => $originalName,
            'file_path'         => $storedPath,
            'file_type'         => $fileType,
            'status'            => 'pending',
        ]);

        app(PriceExtractionService::class)->queueExtraction($priceList);

        return back()->with('success', 'Price list uploaded and queued for AI extraction.');
    }

    /**
     * Retry extraction for a failed or pending_sidecar price list.
     */
    public function retryPriceList(VendorPriceList $priceList): RedirectResponse
    {
        if (! in_array($priceList->status, ['failed', 'pending_sidecar'], true)) {
            return back()->with('error', 'Only failed or pending price lists can be retried.');
        }

        $priceList->items()->delete();
        $priceList->update([
            'status'        => 'pending',
            'item_count'    => null,
            'flagged_count' => null,
            'extracted_at'  => null,
            'flag_reasons'  => null,
        ]);

        app(\App\Services\PriceExtractionService::class)->queueExtraction($priceList);

        return back()->with('success', 'Price list re-queued for extraction.');
    }

    /**
     * Reject a price list — marks it as rejected so it disappears from the review queue.
     */
    public function rejectPriceList(Request $request, VendorPriceList $priceList): RedirectResponse
    {
        $priceList->update([
            'status'       => 'rejected',
            'flag_reasons' => [$request->input('reason') ?: 'Rejected by owner'],
        ]);

        return redirect()->route('owner.vendors.edit', $priceList->vendor_id)
            ->with('success', 'Price list rejected and removed from review queue.');
    }

    /**
     * Show extracted price items for human review, with previous price list comparison.
     */
    public function reviewPriceList(VendorPriceList $priceList): View
    {
        $priceList->load(['vendor', 'items.inventoryItem']);

        // Build a lookup from the most recently APPLIED price list for this vendor
        // so the review page can show old vs. new prices for each item.
        $previousPrices = collect();
        $previousList = VendorPriceList::where('vendor_id', $priceList->vendor_id)
            ->where('id', '!=', $priceList->id)
            ->where('status', 'applied')
            ->latest()
            ->first();

        if ($previousList) {
            $prevItems = $previousList->items()->where('applied', true)->get();
            // Index by SKU (primary) and normalised name (fallback)
            foreach ($prevItems as $pi) {
                $key = $pi->sku_detected ? strtoupper(trim($pi->sku_detected)) : null;
                if ($key) {
                    $previousPrices->put('sku:' . $key, $pi->detected_price);
                }
                $nameKey = 'name:' . strtolower(trim($pi->item_name));
                if (!$previousPrices->has($nameKey)) {
                    $previousPrices->put($nameKey, $pi->detected_price);
                }
            }
        }

        return view('owner.vendors.price-list-review', compact('priceList', 'previousPrices'));
    }

    /**
     * Apply selected price items after human approval.
     * Accepts JSON body: { approved_items: [id,...], item_departments: {id: dept,...} }
     */
    public function applyPrices(Request $request, VendorPriceList $priceList): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'approved_items'        => 'required|array|min:1',
            'approved_items.*'      => 'integer|exists:vendor_price_items,id',
            'item_departments'      => 'nullable|array',
            'item_departments.*'    => 'nullable|string|in:pharmacy,lab,radiology,general',
            'item_prices'           => 'nullable|array',
            'item_prices.*'         => 'nullable|numeric|min:0',
            'denied_items'          => 'nullable|array',
            'denied_items.*'        => 'integer|exists:vendor_price_items,id',
        ]);

        $itemDepartments = [];
        foreach ($request->input('item_departments', []) as $itemId => $dept) {
            if ($dept) {
                $itemDepartments[(int) $itemId] = $dept;
            }
        }

        $itemPrices = [];
        foreach ($request->input('item_prices', []) as $itemId => $price) {
            if ($price !== null && $price > 0) {
                $itemPrices[(int) $itemId] = (float) $price;
            }
        }

        $deniedItems = array_map('intval', $request->input('denied_items', []));

        $count = app(PriceExtractionService::class)
            ->applyExtractedPrices($priceList, $request->approved_items, $itemDepartments, $itemPrices, $deniedItems);

        return response()->json([
            'success' => true,
            'count'   => $count,
            'message' => "{$count} item(s) applied — new entries created and existing prices updated.",
            'redirect' => route('owner.vendors.edit', $priceList->vendor_id),
        ]);
    }
}
