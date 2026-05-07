<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorPriceList;
use App\Services\PriceExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VendorPriceListController extends Controller
{
    public function __construct(private PriceExtractionService $extractor) {}

    private function resolveVendor(): Vendor
    {
        return Vendor::where('vendor_user_id', Auth::id())->firstOrFail();
    }

    public function index(): View
    {
        $vendor     = $this->resolveVendor();
        $priceLists = VendorPriceList::where('vendor_id', $vendor->id)->latest()->paginate(15);

        return view('vendor.price-lists.index', compact('vendor', 'priceLists'));
    }

    public function create(): View
    {
        $vendor = $this->resolveVendor();
        return view('vendor.price-lists.create', compact('vendor'));
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->resolveVendor();

        $request->validate([
            'price_file' => 'required|file|mimes:pdf,jpg,jpeg,png,csv|max:20480',
            'notes'      => 'nullable|string|max:500',
        ]);

        $file          = $request->file('price_file');
        $originalName  = $file->getClientOriginalName();
        $extension     = $file->getClientOriginalExtension();
        $storedPath    = $file->store("price-lists/vendors/{$vendor->id}", 'local');

        $fileType = match (strtolower($extension)) {
            'pdf'  => 'pdf',
            'csv'  => 'csv',
            default => 'image',
        };

        $priceList = VendorPriceList::create([
            'vendor_id'         => $vendor->id,
            'uploaded_by'       => Auth::id(),
            'filename'          => basename($storedPath),
            'original_filename' => $originalName,
            'file_path'         => $storedPath,
            'file_type'         => $fileType,
            'status'            => 'pending',
        ]);

        $this->extractor->queueExtraction($priceList);

        return redirect()->route('vendor.price-lists.index')
            ->with('success', 'Price list uploaded successfully. AI extraction has been queued — you will be notified when it is ready for review.');
    }

    public function show(VendorPriceList $priceList): View
    {
        $vendor = $this->resolveVendor();
        abort_unless($priceList->vendor_id === $vendor->id, 403);

        $priceList->load('items');

        return view('vendor.price-lists.show', compact('vendor', 'priceList'));
    }
}
