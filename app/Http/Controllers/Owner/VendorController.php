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
        ]);

        $validated['auto_send_po'] = $request->boolean('auto_send_po');
        Vendor::create($validated);

        return redirect()->route('owner.vendors.index')->with('success', 'Vendor added to approved list.');
    }

    public function edit(Vendor $vendor): View
    {
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
     * Show extracted price items for human review.
     */
    public function reviewPriceList(VendorPriceList $priceList): View
    {
        $priceList->load(['vendor', 'items.inventoryItem']);
        return view('owner.vendors.price-list-review', compact('priceList'));
    }

    /**
     * Apply selected price items after human approval.
     */
    public function applyPrices(Request $request, VendorPriceList $priceList): RedirectResponse
    {
        $request->validate([
            'approved_items'   => 'required|array|min:1',
            'approved_items.*' => 'integer|exists:vendor_price_items,id',
        ]);

        $count = app(PriceExtractionService::class)
            ->applyExtractedPrices($priceList, $request->approved_items);

        return back()->with('success', "{$count} price(s) applied successfully.");
    }
}
