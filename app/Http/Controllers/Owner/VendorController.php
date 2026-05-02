<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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
}
