<?php

namespace App\Http\Controllers\Radiology;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceStatusChanged;
use App\Notifications\ResultsReady;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * List radiology invoices.
     */
    public function index(): View
    {
        $invoices = Invoice::where('department', 'radiology')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->latest()
            ->paginate(10);

        return view('radiology.invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Show a specific radiology invoice.
     */
    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        return view('radiology.invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Start work on a radiology invoice.
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
                ->withErrors('Work has already been started on this imaging.');
        }

        $invoice->update(['performed_by_user_id' => Auth::id()]);

        AuditableService::logInvoiceStatusChange($invoice, 'paid', 'paid (work started)');

        return redirect()->route('radiology.invoices.show', $invoice)
            ->with('success', 'Imaging work started.');
    }

    /**
     * Mark imaging work as completed.
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
                ->withErrors('Imaging report is required before marking work as completed.');
        }

        if (!$invoice->performed_by_user_id) {
            return redirect()->back()
                ->withErrors('You must start work before completing it.');
        }

        if ($invoice->isWorkCompleted()) {
            return redirect()->back()
                ->withErrors('Work on this imaging has already been completed.');
        }

        try {
            $invoice->completeAndDistribute();
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }

        // Notify prescribing doctor that radiology results are ready
        $this->notifyDoctorResultsReady($invoice);

        AuditableService::logInvoiceStatusChange($invoice, 'paid', 'paid (work completed)');

        return redirect()->route('radiology.invoices.show', $invoice)
            ->with('success', 'Imaging completed and revenue distributed.');
    }

    /**
     * Save radiology report text.
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
     * Upload radiology images (JPG, PNG, PDF).
     */
    public function uploadImages(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $request->validate([
            'images'   => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'], // 10 MB each
        ]);

        $existing = $invoice->radiology_images ?? [];

        foreach ($request->file('images') as $file) {
            $path = $file->store('radiology/' . $invoice->id, 'public');
            $existing[] = $path;
        }

        $invoice->update(['radiology_images' => $existing]);

        return redirect()->back()->with('success', count($request->file('images')) . ' image(s) uploaded.');
    }

    /**
     * Delete a single radiology image by index.
     */
    public function deleteImage(Invoice $invoice, int $index): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $images = $invoice->radiology_images ?? [];

        if (!isset($images[$index])) {
            return redirect()->back()->withErrors('Image not found.');
        }

        // Remove from storage
        Storage::disk('public')->delete($images[$index]);

        // Remove from array and re-index
        array_splice($images, $index, 1);
        $invoice->update(['radiology_images' => $images]);

        return redirect()->back()->with('success', 'Image deleted.');
    }

    /**
     * Notify the prescribing doctor that radiology results are ready.
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
            ?? 'Imaging';

        $doctor->notify(new ResultsReady(
            $invoice->id,
            'radiology',
            $patientName,
            $serviceDesc,
        ));
    }
}
