<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Notifications\PatientAwaitingTriage;
use App\Notifications\PatientRegistered;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PatientRegistrationController extends Controller
{
    /**
     * Show patient registration form.
     */
    public function create(): View
    {
        $doctors = User::role('Doctor')->get();

        // Consultation services for fee lookup (keyed by doctor)
        $consultationServices = ServiceCatalog::where('department', 'consultation')
            ->where('is_active', true)
            ->get();

        return view('receptionist.patients.create', [
            'doctors' => $doctors,
            'consultationServices' => $consultationServices,
        ]);
    }

    /**
     * Store a newly registered patient and collect consultation fee upfront.
     *
     * Workflow: register patient → create consultation invoice → mark as paid
     * → notify doctor.  The patient does NOT see the doctor until payment
     * is collected at the reception desk.
     */
    public function store(): RedirectResponse
    {
        $validated = request()->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'cnic' => 'nullable|string|max:15',
            'gender' => 'required|string|in:Male,Female,Other',
            'date_of_birth' => 'nullable|date',
            'doctor_id' => 'required|exists:users,id',
            'service_catalog_id' => 'required|exists:service_catalog,id',
            'consultation_fee' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,transfer',
        ]);

        // Verify selected doctor has Doctor role
        $doctor = User::findOrFail($validated['doctor_id']);
        if (!$doctor->hasRole('Doctor')) {
            return redirect()->back()
                ->withInput()
                ->withErrors('Selected user is not a doctor.');
        }

        // Verify service catalog entry belongs to consultation department
        $service = ServiceCatalog::findOrFail($validated['service_catalog_id']);
        if ($service->department !== 'consultation' || !$service->is_active) {
            return redirect()->back()
                ->withInput()
                ->withErrors('Invalid consultation service selected.');
        }

        try {
            $patient = DB::transaction(function () use ($validated, $doctor, $service) {
                // 1. Create the patient
                $patient = Patient::create([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'phone' => $validated['phone'],
                    'cnic' => $validated['cnic'] ?? null,
                    'gender' => $validated['gender'],
                    'date_of_birth' => $validated['date_of_birth'],
                    'doctor_id' => $validated['doctor_id'],
                    'status' => 'registered',
                    'registered_at' => now(),
                ]);

                // 2. Create consultation invoice (upfront payment)
                $fee = (float) $validated['consultation_fee'];
                if ($fee <= 0) {
                    $fee = (float) $service->price;
                }

                $invoice = Invoice::create([
                    'patient_id' => $patient->id,
                    'patient_type' => 'clinic',
                    'department' => 'consultation',
                    'service_name' => $service->name,
                    'total_amount' => $fee,
                    'net_amount' => $fee,
                    'prescribing_doctor_id' => $doctor->id,
                    'performed_by_user_id' => $doctor->id,
                    'created_by_user_id' => Auth::id(),
                    'status' => Invoice::STATUS_PENDING,
                    'service_catalog_id' => $service->id,
                ]);

                // 3. Mark as paid immediately (upfront collection)
                $invoice->markPaid($validated['payment_method'], Auth::id());

                AuditableService::logInvoicePayment($invoice->fresh(), $validated['payment_method']);

                // Async FBR submission (queued to avoid 30s blocking call)
                \App\Jobs\SubmitInvoiceToFbrJob::dispatch($invoice->id);

                return $patient;
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors('Payment failed: ' . $e->getMessage());
        }

        // Notify assigned doctor
        $doctor->notify(new PatientRegistered(
            $validated['first_name'] . ' ' . $validated['last_name'],
            $patient->id,
        ));

        // Notify all triage users — patient needs vitals
        $triageUsers = User::role('Triage')->get();
        foreach ($triageUsers as $triageUser) {
            $triageUser->notify(new PatientAwaitingTriage(
                $validated['first_name'] . ' ' . $validated['last_name'],
                $patient->id,
            ));
        }

        return redirect()->route('receptionist.patients.index')
            ->with('success', 'Patient registered and consultation fee collected (₨' . number_format($validated['consultation_fee'], 2) . ').');
    }

    /**
     * Show list of registered patients.
     */
    public function index(): View
    {
        $currentStatus = request('status');

        $query = Patient::query();
        if ($currentStatus && in_array($currentStatus, ['registered', 'triage', 'with_doctor', 'completed'])) {
            $query->where('status', $currentStatus);
        }
        $patients = $query->latest()->get();

        $statusCounts = Patient::selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return view('receptionist.patients.index', [
            'patients' => $patients,
            'currentStatus' => $currentStatus,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Show a specific patient's details.
     */
    public function show(Patient $patient): View
    {
        $patient->load(['doctor', 'visits', 'invoices', 'triageVitals', 'prescriptions']);
        $doctors = User::role('Doctor')->orderBy('name')->get();

        return view('receptionist.patients.show', [
            'patient' => $patient,
            'doctors' => $doctors,
        ]);
    }

    /**
     * Re-register a completed patient for a new visit.
     */
    public function revisit(Patient $patient): RedirectResponse
    {
        if ($patient->status !== 'completed') {
            return redirect()->back()->withErrors('Only completed patients can be re-registered for a new visit.');
        }

        $patient->update([
            'status' => 'registered',
            'consultation_notes' => null,
            'registered_at' => now(),
        ]);

        return redirect()->route('receptionist.patients.show', $patient)
            ->with('success', 'Patient re-registered for a new visit.');
    }

    /**
     * Reassign a patient to a different doctor.
     */
    public function reassign(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:users,id',
        ]);

        $doctor = User::findOrFail($validated['doctor_id']);
        if (!$doctor->hasRole('Doctor')) {
            return redirect()->back()->withErrors('Selected user is not a doctor.');
        }

        $patient->update(['doctor_id' => $validated['doctor_id']]);

        return redirect()->route('receptionist.patients.show', $patient)
            ->with('success', 'Patient reassigned to Dr. ' . $doctor->name . '.');
    }
}
