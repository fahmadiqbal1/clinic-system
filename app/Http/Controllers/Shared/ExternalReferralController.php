<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\ExternalLab;
use App\Models\ExternalReferral;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ExternalReferralController extends Controller
{
    /** POST from lab or radiology show page */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id'      => 'required|exists:patients,id',
            'invoice_id'      => 'nullable|exists:invoices,id',
            'external_lab_id' => 'required|exists:external_labs,id',
            'test_name'       => 'required|string|max:255',
            'department'      => 'required|in:lab,radiology',
            'reason'          => 'nullable|string|max:255',
            'clinical_notes'  => 'nullable|string|max:1000',
        ]);

        $lab = ExternalLab::findOrFail($validated['external_lab_id']);

        ExternalReferral::create(array_merge($validated, [
            'referred_by_user_id' => Auth::id(),
            'commission_pct'      => $lab->mou_commission_pct,
            'status'              => 'pending',
        ]));

        $dept     = $validated['department'];
        $deptKey  = $dept === 'lab' ? 'laboratory' : 'radiology';
        $back = $validated['invoice_id']
            ? route("{$deptKey}.invoices.show", $validated['invoice_id'])
            : back()->getTargetUrl();

        return redirect($back)->with('success', 'External referral submitted for owner approval.');
    }

    /** Mark an approved referral as sent */
    public function markSent(ExternalReferral $referral): RedirectResponse
    {
        abort_unless($referral->referred_by_user_id === Auth::id() || Auth::user()->hasRole('Owner'), 403);
        $referral->update(['status' => 'sent']);
        return back()->with('success', 'Referral marked as sent to external lab.');
    }
}
