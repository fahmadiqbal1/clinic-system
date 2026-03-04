<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\DiscountApprovalRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceDiscountController extends Controller
{
    /**
     * Request a discount (any authorized staff).
     * Triggers the request → Owner approval workflow.
     */
    public function requestDiscount(Request $request, Invoice $invoice): RedirectResponse
    {
        $user = $request->user();

        // Department staff (Lab/Radiology) may submit reason-only requests;
        // Receptionist or Owner provides the actual discount amount.
        $hasAmount = $request->filled('discount_amount');

        $rules = [
            'discount_reason' => ($hasAmount ? 'nullable' : 'required') . '|string|max:255',
        ];

        if ($hasAmount) {
            $rules['discount_amount'] = 'required|numeric|min:0.01|max:' . $invoice->total_amount;
        }

        $validated = $request->validate($rules, [
            'discount_amount.max' => 'Discount cannot exceed the invoice total amount.',
            'discount_reason.required' => 'Please provide a reason for the discount request.',
        ]);

        try {
            if ($hasAmount) {
                // Standard flow — staff specifies amount
                $invoice->requestDiscount(
                    (float) $validated['discount_amount'],
                    $user->id,
                    $validated['discount_reason'] ?? null
                );

                $discountAmount = (float) $validated['discount_amount'];
                $successMsg = 'Discount request of ' . number_format($discountAmount, 2) . ' submitted for Owner approval.';
            } else {
                // Reason-only flow — department staff can't see pricing
                // Set status to pending so the Owner can set the actual amount
                if ($invoice->isPaid()) {
                    throw new \RuntimeException('Cannot request discount on a paid invoice.');
                }
                if ($invoice->status === Invoice::STATUS_CANCELLED) {
                    throw new \RuntimeException('Cannot request discount on a cancelled invoice.');
                }
                if (($invoice->discount_status ?? Invoice::DISCOUNT_NONE) === Invoice::DISCOUNT_PENDING) {
                    throw new \RuntimeException('A discount request is already pending approval.');
                }

                $invoice->update([
                    'discount_reason' => $validated['discount_reason'],
                    'discount_status' => Invoice::DISCOUNT_PENDING,
                    'discount_requested_by' => $user->id,
                    'discount_requested_at' => now(),
                    'discount_approved_by' => null,
                ]);

                $discountAmount = 0;
                $successMsg = 'Discount request submitted for Owner review. The owner will set the discount amount.';
            }

            // Notify all Owners about the pending discount
            $owners = User::role('Owner')->get();
            $patientName = $invoice->patient
                ? $invoice->patient->first_name . ' ' . $invoice->patient->last_name
                : 'Patient #' . $invoice->patient_id;
            foreach ($owners as $owner) {
                $owner->notify(new DiscountApprovalRequested(
                    $invoice->id,
                    $patientName,
                    $discountAmount,
                    $user->name,
                ));
            }

            return redirect()->back()->with('success', $successMsg);
        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withErrors($e->getMessage());
        }
    }

    /**
     * Approve a pending discount request (Owner only).
     */
    public function approveDiscount(Request $request, Invoice $invoice): RedirectResponse
    {
        try {
            $invoice->approveDiscount($request->user()->id);

            return redirect()->back()
                ->with('success', 'Discount of ' . number_format($invoice->discount_amount, 2) . ' approved.');
        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withErrors($e->getMessage());
        }
    }

    /**
     * Reject a pending discount request (Owner only).
     */
    public function rejectDiscount(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        try {
            $invoice->rejectDiscount(
                $request->user()->id,
                $validated['rejection_reason'] ?? null
            );

            return redirect()->back()
                ->with('success', 'Discount request rejected.');
        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withErrors($e->getMessage());
        }
    }

    /**
     * Direct discount application by Owner (backward compatible).
     * Owner can still apply discounts directly without the request workflow.
     */
    public function applyDiscount(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'discount_amount' => 'required|numeric|min:0.01|max:' . $invoice->total_amount,
            'discount_reason' => 'nullable|string|max:255',
        ], [
            'discount_amount.max' => 'Discount cannot exceed the invoice total amount.',
        ]);

        try {
            $invoice->applyDiscount(
                (float) $validated['discount_amount'],
                $request->user()->id,
                $validated['discount_reason'] ?? null
            );

            return redirect()->back()
                ->with('success', 'Discount of ' . number_format($validated['discount_amount'], 2) . ' applied successfully.');
        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withErrors($e->getMessage());
        }
    }
}
