<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\RevenueLedger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RevenueLedgerController extends Controller
{
    /**
     * Browse revenue ledger entries (read-only).
     */
    public function index(Request $request): View
    {
        $query = RevenueLedger::with(['invoice', 'user', 'payout'])
            ->orderByDesc('created_at');

        // Date filtering
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        // Department filtering (via invoice relationship)
        if ($request->filled('department')) {
            $query->whereHas('invoice', function ($q) use ($request) {
                $q->where('department', $request->query('department'));
            });
        }

        // Role type filtering
        if ($request->filled('role_type')) {
            $query->where('role_type', $request->query('role_type'));
        }

        // Payout status filtering
        if ($request->filled('payout_status')) {
            if ($request->query('payout_status') === 'unpaid') {
                $query->whereNull('payout_id');
            } elseif ($request->query('payout_status') === 'paid') {
                $query->whereNotNull('payout_id');
            }
        }

        $entries = $query->paginate(25)->withQueryString();

        $totalAmount = (clone $query)->sum('amount');

        return view('owner.revenue-ledger.index', [
            'entries' => $entries,
            'totalAmount' => $totalAmount,
            'filters' => $request->only(['from', 'to', 'department', 'role_type', 'payout_status']),
        ]);
    }
}
