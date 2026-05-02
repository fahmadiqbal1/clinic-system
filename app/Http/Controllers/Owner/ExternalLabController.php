<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ExternalLab;
use App\Models\ExternalReferral;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class ExternalLabController extends Controller
{
    public function index(): View
    {
        $labs = ExternalLab::withCount(['referrals', 'referrals as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->orderBy('name')->get();

        $pendingReferrals = ExternalReferral::with(['patient', 'externalLab', 'referredBy'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return view('owner.external-labs.index', compact('labs', 'pendingReferrals'));
    }

    public function create(): View
    {
        return view('owner.external-labs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'short_name'         => 'nullable|string|max:50',
            'city'               => 'nullable|string|max:100',
            'contact_name'       => 'nullable|string|max:255',
            'contact_phone'      => 'nullable|string|max:50',
            'contact_email'      => 'nullable|email|max:255',
            'address'            => 'nullable|string|max:500',
            'mou_commission_pct' => 'nullable|numeric|min:0|max:100',
            'pricing_notes'      => 'nullable|string|max:1000',
            'mou_document'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('mou_document')) {
            $validated['mou_document_path'] = $request->file('mou_document')->store('external-labs/mou', 'public');
        }
        unset($validated['mou_document']);

        ExternalLab::create($validated);

        return redirect()->route('owner.external-labs.index')->with('success', 'External lab added successfully.');
    }

    public function edit(ExternalLab $externalLab): View
    {
        return view('owner.external-labs.edit', ['lab' => $externalLab]);
    }

    public function update(Request $request, ExternalLab $externalLab): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'short_name'         => 'nullable|string|max:50',
            'city'               => 'nullable|string|max:100',
            'contact_name'       => 'nullable|string|max:255',
            'contact_phone'      => 'nullable|string|max:50',
            'contact_email'      => 'nullable|email|max:255',
            'address'            => 'nullable|string|max:500',
            'mou_commission_pct' => 'nullable|numeric|min:0|max:100',
            'pricing_notes'      => 'nullable|string|max:1000',
            'is_active'          => 'boolean',
            'mou_document'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('mou_document')) {
            if ($externalLab->mou_document_path) {
                Storage::disk('public')->delete($externalLab->mou_document_path);
            }
            $validated['mou_document_path'] = $request->file('mou_document')->store('external-labs/mou', 'public');
        }
        $validated['is_active'] = $request->has('is_active');
        unset($validated['mou_document']);

        $externalLab->update($validated);

        return redirect()->route('owner.external-labs.index')->with('success', 'Lab updated.');
    }

    /** Approve or reject a referral */
    public function decideReferral(Request $request, ExternalReferral $referral): RedirectResponse
    {
        $request->validate([
            'decision'       => 'required|in:approved,rejected',
            'owner_notes'    => 'nullable|string|max:500',
            'patient_price'  => 'nullable|numeric|min:0',
            'commission_pct' => 'nullable|numeric|min:0|max:100',
        ]);

        $referral->update([
            'status'         => $request->decision,
            'owner_notes'    => $request->owner_notes,
            'patient_price'  => $request->patient_price ?? $referral->externalLab->mou_commission_pct,
            'commission_pct' => $request->commission_pct ?? $referral->externalLab->mou_commission_pct,
            'approved_by_id' => auth()->id(),
            'approved_at'    => now(),
        ]);

        $label = $request->decision === 'approved' ? 'approved' : 'rejected';
        return back()->with('success', "Referral {$label}.");
    }

    /** Mark referral as sent or completed */
    public function updateStatus(Request $request, ExternalReferral $referral): RedirectResponse
    {
        $request->validate(['status' => 'required|in:sent,completed']);
        $referral->update(['status' => $request->status]);
        return back()->with('success', 'Referral status updated.');
    }
}
