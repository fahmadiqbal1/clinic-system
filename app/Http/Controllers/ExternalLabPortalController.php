<?php

namespace App\Http\Controllers;

use App\Models\ExternalLab;
use App\Models\VendorPriceList;
use App\Services\PriceExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExternalLabPortalController extends Controller
{
    private function lab(): ExternalLab
    {
        $lab = ExternalLab::findOrFail(Auth::user()->external_lab_id);
        return $lab;
    }

    public function dashboard(): View
    {
        $lab = $this->lab()->load('priceLists', 'referrals');
        $priceLists = $lab->priceLists()->orderByDesc('created_at')->get();
        $referrals  = $lab->referrals()->with('patient')->latest()->take(20)->get();

        return view('lab-portal.dashboard', compact('lab', 'priceLists', 'referrals'));
    }

    public function downloadMou(): StreamedResponse
    {
        $lab = $this->lab();

        if (! $lab->mou_document_path || ! Storage::disk('local')->exists($lab->mou_document_path)) {
            abort(404, 'MOU document not found.');
        }

        return Storage::disk('local')->download($lab->mou_document_path, 'MOU_' . $lab->short_name . '.pdf');
    }

    public function uploadPriceList(Request $request): RedirectResponse
    {
        $request->validate([
            'price_list_file' => 'required|file|mimes:pdf,jpg,jpeg,png,csv|max:20480',
        ]);

        $lab = $this->lab();

        $vendorId = $lab->vendor_id;
        if (! $vendorId) {
            $vendor = \App\Models\Vendor::firstOrCreate(
                ['name' => $lab->name . ' (External Lab)'],
                [
                    'category'     => 'external_lab',
                    'contact_name' => $lab->contact_name,
                    'email'        => $lab->contact_email,
                    'phone'        => $lab->contact_phone,
                    'is_approved'  => true,
                ]
            );
            $lab->update(['vendor_id' => $vendor->id]);
            $vendorId = $vendor->id;
        }

        $file      = $request->file('price_list_file');
        $ext       = strtolower($file->getClientOriginalExtension());
        $fileType  = match ($ext) { 'pdf' => 'pdf', 'csv' => 'csv', default => 'image' };
        $storedPath = $file->store("private/price-lists/external-labs/{$lab->id}", 'local');

        $priceList = VendorPriceList::create([
            'vendor_id'         => $vendorId,
            'external_lab_id'   => $lab->id,
            'uploaded_by'       => Auth::id(),
            'filename'          => basename($storedPath),
            'original_filename' => $file->getClientOriginalName(),
            'file_path'         => $storedPath,
            'file_type'         => $fileType,
            'status'            => 'pending',
        ]);

        app(PriceExtractionService::class)->queueExtraction($priceList);

        return redirect()->route('lab-portal.dashboard')->with('success', 'Price list uploaded. It will be processed and reflected shortly.');
    }
}
