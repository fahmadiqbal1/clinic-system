<?php

namespace App\Http\Controllers\Laboratory;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceStatusChanged;
use App\Notifications\ResultsReady;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * List laboratory invoices.
     *
     * Work queue includes:
     *  - Pending invoices (legacy flow)
     *  - Paid invoices awaiting work (upfront-payment flow, no distribution yet)
     */
    public function index(): View
    {
        $invoices = Invoice::where('department', 'lab')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->latest()
            ->paginate(10);

        return view('laboratory.invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Show a specific laboratory invoice.
     */
    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load('items.serviceCatalog', 'patient', 'prescribingDoctor');

        return view('laboratory.invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Start work on a laboratory invoice.
     *
     * Invoice must be paid before work can begin.
     */
    public function startWork(Invoice $invoice): RedirectResponse
    {
        $this->authorize('transitionStatus', $invoice);

        if (!$invoice->isPaid()) {
            return redirect()->back()
                ->withErrors('Invoice must be paid before work can begin.');
        }

        if ($invoice->performed_by_user_id) {
            return redirect()->back()
                ->withErrors('Work has already been started on this test.');
        }

        $invoice->update(['performed_by_user_id' => Auth::id()]);

        AuditableService::logInvoiceStatusChange($invoice, 'paid', 'paid (work started)');

        return redirect()->route('laboratory.invoices.show', $invoice)
            ->with('success', 'Work started on this test.');
    }

    /**
     * Mark invoice work as completed.
     *
     * Invoice must be paid. Triggers deferred financial distribution.
     */
    public function markComplete(Invoice $invoice): RedirectResponse
    {
        $this->authorize('transitionStatus', $invoice);

        if (!$invoice->isPaid()) {
            return redirect()->back()
                ->withErrors('Invoice must be paid before work can be completed.');
        }

        // Require report text before completion
        if (!$invoice->report_text || empty(trim($invoice->report_text))) {
            return redirect()->back()
                ->withErrors('Test report is required before marking work as completed.');
        }

        if (!$invoice->performed_by_user_id) {
            return redirect()->back()
                ->withErrors('You must start work before completing it.');
        }

        if ($invoice->isWorkCompleted()) {
            return redirect()->back()
                ->withErrors('Work on this test has already been completed.');
        }

        try {
            $invoice->completeAndDistribute();
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }

        // Notify prescribing doctor that lab results are ready
        $this->notifyDoctorResultsReady($invoice);

        AuditableService::logInvoiceStatusChange($invoice, 'paid', 'paid (work completed)');

        return redirect()->route('laboratory.invoices.show', $invoice)
            ->with('success', 'Test completed and revenue distributed.');
    }

    /**
     * Save lab report text.
     *
     * Works on both in_progress and paid invoices.
     */
    public function saveReport(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        request()->validate([
            'report' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        $invoice->update([
            'report_text' => request()->input('report'),
        ]);

        AuditableService::logReportSave($invoice);

        return redirect()->back()
            ->with('success', 'Report saved successfully.');
    }

    /**
     * Save structured lab results (grouped by invoice item).
     *
     * Accepts: results[<item_id>][<index>][test_name|result|unit|reference_range]
     * Stores JSON keyed by item ID: {"42": [{test_name, result, unit, reference_range}, ...]}
     * Falls back to a flat array under key "general" when invoice has no items.
     */
    public function saveResults(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'results'                          => ['required', 'array', 'min:1'],
            'results.*'                        => ['required', 'array', 'min:1'],
            'results.*.*.test_name'            => ['required', 'string', 'max:255'],
            'results.*.*.result'               => ['required', 'string', 'max:255'],
            'results.*.*.unit'                 => ['nullable', 'string', 'max:50'],
            'results.*.*.reference_range'      => ['nullable', 'string', 'max:100'],
        ]);

        // Build grouped structure — keyed by item ID (or "general")
        $grouped = [];
        $totalCount = 0;

        foreach ($validated['results'] as $itemKey => $rows) {
            $filtered = collect($rows)
                ->filter(fn ($row) => filled($row['test_name'] ?? null) && filled($row['result'] ?? null))
                ->map(fn ($row) => [
                    'test_name'       => $row['test_name'],
                    'result'          => $row['result'],
                    'unit'            => $row['unit'] ?? '',
                    'reference_range' => $row['reference_range'] ?? '',
                ])
                ->values()
                ->toArray();

            if (count($filtered) > 0) {
                $grouped[(string) $itemKey] = $filtered;
                $totalCount += count($filtered);
            }
        }

        $invoice->update(['lab_results' => $grouped]);

        return redirect()->back()->with('success', $totalCount . ' test parameter(s) saved.');
    }

    /**
     * Notify the prescribing doctor that lab results are ready.
     */
    private function notifyDoctorResultsReady(Invoice $invoice): void
    {
        $invoice->loadMissing(['prescribingDoctor', 'patient', 'items.serviceCatalog']);

        $doctor = $invoice->prescribingDoctor;
        if (!$doctor) {
            return;
        }

        $patientName = $invoice->patient
            ? $invoice->patient->first_name . ' ' . $invoice->patient->last_name
            : 'Patient #' . $invoice->patient_id;

        $serviceDesc = $invoice->items->first()?->serviceCatalog?->name
            ?? $invoice->service_name
            ?? 'Lab Test';

        $doctor->notify(new ResultsReady(
            $invoice->id,
            'lab',
            $patientName,
            $serviceDesc,
        ));
    }
}
