<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResultsController extends Controller
{
    /**
     * List completed lab & radiology results for the authenticated doctor's patients.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Invoice::with(['patient'])
            ->where('prescribing_doctor_id', $user->id)
            ->whereIn('department', ['lab', 'radiology'])
            ->whereIn('status', ['paid', 'completed'])
            ->whereNotNull('performed_by_user_id')
            ->where(function ($q) {
                $q->whereNotNull('report_text')
                  ->orWhereNotNull('lab_results');
            })
            ->orderByDesc('updated_at');

        if ($request->filled('department')) {
            $query->where('department', $request->query('department'));
        }

        if ($request->filled('from')) {
            $query->whereDate('updated_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('updated_at', '<=', $request->query('to'));
        }

        $results = $query->paginate(25)->withQueryString();

        return view('doctor.results.index', [
            'results' => $results,
            'filters' => $request->only(['department', 'from', 'to']),
        ]);
    }

    /**
     * Show a single clinical result (read-only, no financial data).
     */
    public function show(Invoice $invoice): View
    {
        $user = Auth::user();

        if ($invoice->prescribing_doctor_id !== $user->id) {
            abort(403, 'This result belongs to a different doctor.');
        }

        if (!in_array($invoice->department, ['lab', 'radiology'])) {
            abort(404, 'No clinical result found.');
        }

        $invoice->load(['patient', 'items.serviceCatalog', 'performer']);

        return view('doctor.results.show', [
            'invoice' => $invoice,
        ]);
    }
}
