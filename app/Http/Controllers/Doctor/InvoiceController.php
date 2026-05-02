<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * List invoices attributed to the authenticated doctor (read-only).
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Invoice::with(['patient'])
            ->where('prescribing_doctor_id', $user->id)
            ->orderByDesc('created_at');

        // Results-ready shortcut: completed lab + radiology invoices
        // Upfront-paid invoices stay status='paid' after work done, so we check both statuses
        // and require performed_by_user_id + (report_text OR lab_results) as work-done signals.
        if ($request->filled('results_ready')) {
            $query->whereIn('department', ['lab', 'radiology'])
                  ->whereIn('status', ['paid', 'completed'])
                  ->whereNotNull('performed_by_user_id')
                  ->where(function ($q) {
                      $q->whereNotNull('report_text')
                        ->orWhereNotNull('lab_results');
                  });
        } else {
            // Status filtering
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            // Department filtering
            if ($request->filled('department')) {
                $query->where('department', $request->query('department'));
            }
        }

        // Date filtering
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $invoices = $query->paginate(25)->withQueryString();

        return view('doctor.invoices.index', [
            'invoices'      => $invoices,
            'filters'       => $request->only(['status', 'department', 'from', 'to']),
            'resultsReady'  => $request->filled('results_ready'),
        ]);
    }

    /**
     * Show a single invoice detail (read-only).
     */
    public function show(Invoice $invoice): View
    {
        $user = Auth::user();

        // Only allow viewing own invoices
        if ($invoice->prescribing_doctor_id !== $user->id) {
            abort(403, 'You can only view invoices attributed to you.');
        }

        $invoice->load(['patient', 'revenueLedgers' => fn($q) => $q->where('user_id', $user->id), 'items.serviceCatalog']);

        return view('doctor.invoices.show', [
            'invoice' => $invoice,
        ]);
    }
}
