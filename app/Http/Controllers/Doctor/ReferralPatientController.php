<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\ServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReferralPatientController extends Controller
{
    /**
     * List referral patients for this independent doctor.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        abort_unless($user->is_independent, 403, 'Access restricted to independent doctors.');

        $status = $request->query('status');

        $query = Patient::where('referred_by_user_id', $user->id)
            ->with(['invoices' => fn ($q) => $q->whereIn('department', ['lab', 'radiology', 'pharmacy'])]);

        if ($status) {
            $query->where('status', $status);
        }

        $patients = $query->latest()->paginate(25);

        return view('independent-doctor.patients.index', [
            'patients' => $patients,
            'status'   => $status,
        ]);
    }

    /**
     * Show quick-registration form for a new referral patient.
     */
    public function create(): View
    {
        abort_unless(Auth::user()->is_independent, 403, 'Access restricted to independent doctors.');

        $labServices = ServiceCatalog::active()
            ->where('department', 'lab')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $radiologyServices = ServiceCatalog::active()
            ->where('department', 'radiology')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $pharmacyServices = ServiceCatalog::active()
            ->where('department', 'pharmacy')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('independent-doctor.patients.create', [
            'labServices'      => $labServices,
            'radiologyServices' => $radiologyServices,
            'pharmacyServices' => $pharmacyServices,
        ]);
    }

    /**
     * Store a new referral patient and create the requested service invoices.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->is_independent, 403, 'Access restricted to independent doctors.');

        $validated = $request->validate([
            'first_name'       => ['required', 'string', 'max:255'],
            'last_name'        => ['required', 'string', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'gender'           => ['required', 'string', 'in:Male,Female,Other'],
            'date_of_birth'    => ['nullable', 'date'],
            'services'         => ['required', 'array', 'min:1'],
            'services.*'       => ['required', 'integer', 'exists:service_catalog,id'],
            'payment_method'   => ['required', 'in:cash,card,transfer'],
        ]);

        try {
            $patient = DB::transaction(function () use ($validated, $user) {
                // 1. Create the referral patient
                $patient = Patient::create([
                    'first_name'         => $validated['first_name'],
                    'last_name'          => $validated['last_name'],
                    'phone'              => $validated['phone'] ?? null,
                    'gender'             => $validated['gender'],
                    'date_of_birth'      => $validated['date_of_birth'] ?? null,
                    'status'             => 'registered',
                    'registration_type'  => 'referral',
                    'referred_by_user_id' => $user->id,
                    'registered_at'      => now(),
                ]);

                // 2. Group selected services by department and create one invoice per department
                $services = ServiceCatalog::whereIn('id', $validated['services'])->get();
                $grouped  = $services->groupBy('department');

                foreach ($grouped as $department => $deptServices) {
                    // Validate department is one of the allowed ones
                    if (!in_array($department, ['lab', 'radiology', 'pharmacy'], true)) {
                        continue;
                    }

                    $totalAmount = $deptServices->sum('price');

                    $invoice = Invoice::create([
                        'patient_id'           => $patient->id,
                        'department'           => $department,
                        'service_name'         => $deptServices->count() === 1
                            ? $deptServices->first()->name
                            : "{$deptServices->count()} {$department} services",
                        'total_amount'         => $totalAmount,
                        'net_amount'           => $totalAmount,
                        'prescribing_doctor_id' => $user->id,
                        'created_by_user_id'   => $user->id,
                        'payment_method'       => $validated['payment_method'],
                        'status'               => Invoice::STATUS_PENDING,
                        'has_prescribed_items' => true,
                    ]);

                    // Create line items
                    foreach ($deptServices as $service) {
                        InvoiceItem::create([
                            'invoice_id'       => $invoice->id,
                            'service_catalog_id' => $service->id,
                            'description'      => $service->name,
                            'quantity'         => 1,
                            'unit_price'       => $service->price,
                            'cost_price'       => 0,
                            'line_total'       => $service->price,
                            'line_cogs'        => 0,
                        ]);
                    }
                }

                AuditLog::log(
                    action: 'referral_patient_registered',
                    auditableType: 'App\Models\Patient',
                    auditableId: $patient->id,
                    afterState: ['referred_by_user_id' => $user->id, 'name' => $patient->full_name]
                );

                return $patient;
            });

            return redirect()->route('independent-doctor.patients.show', $patient)
                ->with('success', "Referral patient {$patient->full_name} registered successfully.");
        } catch (\Exception $e) {
            Log::error('Referral patient registration failed', [
                'doctor_id' => $user->id,
                'error'     => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }

    /**
     * Show a referral patient's details and their invoices.
     */
    public function show(Patient $patient): View
    {
        $user = Auth::user();
        abort_unless($user->is_independent, 403, 'Access restricted to independent doctors.');
        abort_unless($patient->referred_by_user_id === $user->id, 403, 'This patient was not referred by you.');

        $patient->load('referredBy');

        $invoices = Invoice::where('patient_id', $patient->id)
            ->whereIn('department', ['lab', 'radiology', 'pharmacy'])
            ->with('items.serviceCatalog')
            ->latest()
            ->get();

        $labServices = ServiceCatalog::active()
            ->where('department', 'lab')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $radiologyServices = ServiceCatalog::active()
            ->where('department', 'radiology')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $pharmacyServices = ServiceCatalog::active()
            ->where('department', 'pharmacy')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('independent-doctor.patients.show', [
            'patient'           => $patient,
            'invoices'          => $invoices,
            'labServices'       => $labServices,
            'radiologyServices' => $radiologyServices,
            'pharmacyServices'  => $pharmacyServices,
        ]);
    }

    /**
     * Add more service invoices to an existing referral patient.
     */
    public function addInvoice(Request $request, Patient $patient): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->is_independent, 403, 'Access restricted to independent doctors.');
        abort_unless($patient->referred_by_user_id === $user->id, 403, 'This patient was not referred by you.');

        $validated = $request->validate([
            'services'       => ['required', 'array', 'min:1'],
            'services.*'     => ['required', 'integer', 'exists:service_catalog,id'],
            'payment_method' => ['required', 'in:cash,card,transfer'],
        ]);

        try {
            DB::transaction(function () use ($validated, $patient, $user) {
                $services = ServiceCatalog::whereIn('id', $validated['services'])->get();
                $grouped  = $services->groupBy('department');

                foreach ($grouped as $department => $deptServices) {
                    if (!in_array($department, ['lab', 'radiology', 'pharmacy'], true)) {
                        continue;
                    }

                    $totalAmount = $deptServices->sum('price');

                    $invoice = Invoice::create([
                        'patient_id'           => $patient->id,
                        'department'           => $department,
                        'service_name'         => $deptServices->count() === 1
                            ? $deptServices->first()->name
                            : "{$deptServices->count()} {$department} services",
                        'total_amount'         => $totalAmount,
                        'net_amount'           => $totalAmount,
                        'prescribing_doctor_id' => $user->id,
                        'created_by_user_id'   => $user->id,
                        'payment_method'       => $validated['payment_method'],
                        'status'               => Invoice::STATUS_PENDING,
                        'has_prescribed_items' => true,
                    ]);

                    foreach ($deptServices as $service) {
                        InvoiceItem::create([
                            'invoice_id'        => $invoice->id,
                            'service_catalog_id' => $service->id,
                            'description'       => $service->name,
                            'quantity'          => 1,
                            'unit_price'        => $service->price,
                            'cost_price'        => 0,
                            'line_total'        => $service->price,
                            'line_cogs'         => 0,
                        ]);
                    }
                }
            });

            return redirect()->route('independent-doctor.patients.show', $patient)
                ->with('success', 'Additional services ordered successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to add referral invoice', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Failed to add services. Please try again.']);
        }
    }
}
