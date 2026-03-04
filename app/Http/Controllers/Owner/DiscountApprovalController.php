<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\View\View;

class DiscountApprovalController extends Controller
{
    /**
     * Show all invoices with pending discount requests for Owner to review.
     */
    public function index(): View
    {
        $pendingDiscounts = Invoice::where('discount_status', Invoice::DISCOUNT_PENDING)
            ->with(['patient', 'discountRequester', 'prescribingDoctor'])
            ->latest('discount_requested_at')
            ->paginate(20);

        $recentlyProcessed = Invoice::whereIn('discount_status', [Invoice::DISCOUNT_APPROVED, Invoice::DISCOUNT_REJECTED])
            ->whereNotNull('discount_approved_at')
            ->with(['patient', 'discountRequester', 'discountApprover'])
            ->latest('discount_approved_at')
            ->take(20)
            ->get();

        return view('owner.discount-approvals.index', [
            'pendingDiscounts' => $pendingDiscounts,
            'recentlyProcessed' => $recentlyProcessed,
        ]);
    }
}
