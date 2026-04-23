<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use App\Notifications\PatientSelfCheckedIn;
use App\Services\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PatientPortalController extends Controller
{
    /**
     * Patient dashboard — show linked patient profile and summary.
     */
    public function dashboard(): View
    {
        $patient = $this->getPatient();

        if (!$patient) {
            return view('patient.no-profile');
        }

        $patient->load(['doctor', 'triageVitals', 'prescriptions.items']);

        $invoices = Invoice::where('patient_id', $patient->id)
            ->with('items.serviceCatalog', 'prescribingDoctor')
            ->latest()
            ->get();

        $analyses = AiAnalysis::where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->with('requester')
            ->latest()
            ->get();

        return view('patient.dashboard', [
            'patient' => $patient,
            'invoices' => $invoices,
            'analyses' => $analyses,
        ]);
    }

    /**
     * View a specific invoice details (lab results, radiology images, reports).
     */
    public function invoice(Invoice $invoice): View
    {
        $patient = $this->getPatient();
        if (!$patient || $invoice->patient_id !== $patient->id) {
            abort(403, 'You do not have access to this record.');
        }

        $invoice->load(['items.serviceCatalog', 'prescribingDoctor', 'performer']);

        $analyses = AiAnalysis::where('invoice_id', $invoice->id)
            ->where('status', 'completed')
            ->with('requester')
            ->latest()
            ->get();

        return view('patient.invoice', [
            'patient' => $patient,
            'invoice' => $invoice,
            'analyses' => $analyses,
        ]);
    }

    /**
     * Download invoice PDF for the logged-in patient.
     */
    public function downloadInvoicePdf(Invoice $invoice, PdfService $pdfService): BinaryFileResponse
    {
        $patient = $this->getPatient();
        if (!$patient || $invoice->patient_id !== $patient->id) {
            abort(403, 'You do not have access to this record.');
        }

        $path = $pdfService->generateInvoicePdf($invoice);

        return response()->download(storage_path('app/public/' . $path), "invoice-{$invoice->id}.pdf")
            ->deleteFileAfterSend();
    }

    /**
     * Show check-in kiosk page.
     */
    public function checkin(): View
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $patient = Patient::where('user_id', $authUser->id)->first();

        if (!$patient) {
            return view('patient.no-profile');
        }

        $alreadyCheckedIn = Cache::has("checkin_{$patient->id}");

        return view('patient.checkin', compact('patient', 'alreadyCheckedIn'));
    }

    /**
     * Confirm patient arrival (self check-in).
     */
    public function confirmArrival(): RedirectResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $patient = Patient::where('user_id', $authUser->id)->firstOrFail();

        $cacheKey = "checkin_{$patient->id}";

        if (Cache::has($cacheKey)) {
            return redirect()->route('patient.checkin')
                ->with('info', 'You have already checked in. Please take a seat and wait to be called.');
        }

        Cache::put($cacheKey, true, now()->addHours(4));

        // Persist check-in to DB for audit trail
        PatientCheckin::create([
            'patient_id'     => $patient->id,
            'checked_in_by'  => null, // self check-in via kiosk
            'checked_in_at'  => now(),
            'checked_in_via' => 'kiosk',
        ]);

        User::role('Triage')->get()->each(function (User $triage) use ($patient): void {
            $triage->notify(new PatientSelfCheckedIn($patient));
        });

        return redirect()->route('patient.checkin')
            ->with('success', 'You are checked in! Please take a seat. The triage team has been notified.');
    }

    /**
     * Get the patient record linked to the logged-in user.
     * 
     * SECURITY: Only links via explicit user_id foreign key.
     * Email fallback removed to prevent PHI exposure when
     * multiple users share an email address.
     */
    private function getPatient(): ?Patient
    {
        $userId = Auth::id();
        return $userId ? Patient::where('user_id', $userId)->first() : null;
    }
}
