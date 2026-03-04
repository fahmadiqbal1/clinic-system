<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * List invoices for receptionists — all consultation invoices + other completed ones.
     */
    public function index(Request $request): View
    {
        $query = Invoice::with(['patient', 'prescribingDoctor'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('department')) {
            $query->where('department', $request->query('department'));
        }

        $invoices = $query->paginate(25)->withQueryString();

        return view('receptionist.invoices.index', [
            'invoices' => $invoices,
            'filters' => $request->only(['status', 'department']),
        ]);
    }

    /**
     * Show form to create a new consultation invoice.
     */
    public function create(): View
    {
        $patients = Patient::orderBy('first_name')->get();
        $doctors = User::role('Doctor')->orderBy('name')->get();
        $services = ServiceCatalog::where('department', 'consultation')->active()->get();

        return view('receptionist.invoices.create', [
            'patients' => $patients,
            'doctors' => $doctors,
            'services' => $services,
        ]);
    }

    /**
     * Store a new consultation invoice.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'patient_type' => 'required|in:clinic,walk_in',
            'department' => 'required|in:lab,radiology,pharmacy,consultation',
            'service_name' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0.01',
            'prescribing_doctor_id' => 'nullable|exists:users,id',
            'referrer_name' => 'nullable|string|max:255',
            'referrer_percentage' => 'nullable|numeric|min:0|max:100',
            'service_catalog_id' => 'nullable|exists:service_catalog,id',
        ]);

        $invoice = Invoice::create([
            'patient_id' => $validated['patient_id'],
            'patient_type' => $validated['patient_type'] ?? 'clinic',
            'department' => $validated['department'],
            'service_name' => $validated['service_name'],
            'total_amount' => $validated['total_amount'],
            'net_amount' => $validated['total_amount'],
            'prescribing_doctor_id' => $validated['prescribing_doctor_id'] ?? null,
            'referrer_name' => $validated['referrer_name'] ?? null,
            'referrer_percentage' => $validated['referrer_percentage'] ?? null,
            'created_by_user_id' => Auth::id(),
            'status' => Invoice::STATUS_PENDING,
            'service_catalog_id' => $validated['service_catalog_id'] ?? null,
        ]);

        return redirect()->route('receptionist.invoices.show', $invoice)
            ->with('success', 'Invoice #' . $invoice->id . ' created.');
    }

    /**
     * Show a specific invoice.
     */
    public function show(Invoice $invoice): View
    {
        $invoice->load(['patient', 'prescribingDoctor', 'items', 'creator', 'performer', 'payer']);

        return view('receptionist.invoices.show', compact('invoice'));
    }

    /**
     * Start work on a consultation invoice.
     */
    public function startWork(Invoice $invoice): RedirectResponse
    {
        if (!$invoice->canTransitionTo(Invoice::STATUS_IN_PROGRESS)) {
            return redirect()->back()
                ->withErrors('Invoice cannot be moved to in-progress from current status.');
        }

        $oldStatus = $invoice->status;
        $invoice->update([
            'status' => Invoice::STATUS_IN_PROGRESS,
            'performed_by_user_id' => Auth::id(),
        ]);

        AuditableService::logInvoiceStatusChange($invoice, $oldStatus, Invoice::STATUS_IN_PROGRESS);

        return redirect()->route('receptionist.invoices.show', $invoice)
            ->with('success', 'Work started on this invoice.');
    }

    /**
     * Mark invoice as completed.
     */
    public function markComplete(Invoice $invoice): RedirectResponse
    {
        if (!$invoice->canTransitionTo(Invoice::STATUS_COMPLETED)) {
            return redirect()->back()
                ->withErrors('Invoice cannot be marked as completed from current status.');
        }

        $oldStatus = $invoice->status;
        $invoice->update(['status' => Invoice::STATUS_COMPLETED]);

        AuditableService::logInvoiceStatusChange($invoice, $oldStatus, Invoice::STATUS_COMPLETED);

        return redirect()->route('receptionist.invoices.show', $invoice)
            ->with('success', 'Invoice marked as completed.');
    }

    /**
     * Mark invoice as paid.
     * Delegates to Invoice::markPaid() which handles net_amount recalculation,
     * DB transaction wrapping, and financial distribution atomically.
     */
    public function markPaid(Invoice $invoice): RedirectResponse
    {
        // Allow both pending (upfront) and completed (traditional) invoices to be paid
        if ($invoice->isPaid()) {
            return redirect()->back()
                ->withErrors('Invoice is already paid.');
        }

        if (!in_array($invoice->status, [Invoice::STATUS_PENDING, Invoice::STATUS_IN_PROGRESS, Invoice::STATUS_COMPLETED], true)) {
            return redirect()->back()
                ->withErrors('Invoice cannot be marked as paid from current status.');
        }

        $paymentMethod = request()->validate([
            'payment_method' => 'required|in:cash,card,transfer',
        ])['payment_method'];

        try {
            $invoice->markPaid($paymentMethod, Auth::id());
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }

        AuditableService::logInvoicePayment($invoice->fresh(), $paymentMethod);

        return redirect()->route('receptionist.invoices.show', $invoice)
            ->with('success', 'Invoice marked as paid.');
    }

    /**
     * Cancel an invoice (only pending or in_progress invoices).
     */
    public function cancel(Invoice $invoice): RedirectResponse
    {
        if (!$invoice->canTransitionTo(Invoice::STATUS_CANCELLED)) {
            return redirect()->back()
                ->withErrors('This invoice cannot be cancelled from its current status.');
        }

        $oldStatus = $invoice->status;
        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        AuditableService::logInvoiceStatusChange($invoice, $oldStatus, Invoice::STATUS_CANCELLED);

        return redirect()->route('receptionist.invoices.show', $invoice)
            ->with('success', 'Invoice #' . $invoice->id . ' has been cancelled.');
    }
}
