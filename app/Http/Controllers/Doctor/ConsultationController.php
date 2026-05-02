<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\PlatformSetting;
use App\Models\ServiceCatalog;
use App\Models\SoapKeyword;
use App\Models\User;
use App\Notifications\InvoiceStatusChanged;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    /**
     * Show consultation view.
     */
    public function show(Patient $patient): View
    {
        $user = Auth::user();
        abort_if($user->is_independent, 403, 'Independent doctors do not have access to consultation features.');
        if ($patient->doctor_id !== $user->id) {
            abort(403, 'This patient is not assigned to you.');
        }

        $patient->load('doctor');

        // Latest triage vitals
        $latestVitals = $patient->triageVitals()->latest()->first();

        // Existing prescriptions for this patient
        $prescriptions = $patient->prescriptions()->with('items')->latest()->get();

        // Get all invoices for this patient (with items for multi-test display)
        $invoices = Invoice::where('patient_id', $patient->id)
            ->with('items.serviceCatalog')
            ->latest()
            ->get();

        // Load active service catalog grouped by department
        $serviceCatalog = ServiceCatalog::active()
            ->orderBy('department')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('department');

        // AI analyses for this patient
        $aiAnalyses = \App\Models\AiAnalysis::where('patient_id', $patient->id)
            ->with('requester')
            ->latest()
            ->get();

        // Previous completed visits (excluding current open visit)
        $previousVisits = \App\Models\Visit::where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->with(['doctor', 'prescriptions.items', 'invoices.items.serviceCatalog'])
            ->latest('completed_at')
            ->limit(10)
            ->get();

        // Preload SOAP chip library for the chip builder component.
        // Includes this doctor's private chips + all global chips, ordered by popularity.
        $soapKeywords = SoapKeyword::forDoctor($user->id)
            ->orderBy('usage_count', 'desc')
            ->get()
            ->groupBy('section');

        $aiEnabled = PlatformSetting::isEnabled('ai.chat.enabled.doctor');

        return view('doctor.consultation.show', [
            'patient'        => $patient,
            'latestVitals'   => $latestVitals,
            'prescriptions'  => $prescriptions,
            'invoices'       => $invoices,
            'serviceCatalog' => $serviceCatalog,
            'aiAnalyses'     => $aiAnalyses,
            'previousVisits' => $previousVisits,
            'soapKeywords'   => $soapKeywords,
            'aiEnabled'      => $aiEnabled,
        ]);
    }

    /**
     * Save consultation notes.
     */
    public function saveNotes(Request $request, Patient $patient): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user->is_independent, 403, 'Independent doctors do not have access to consultation features.');
        if ($patient->doctor_id !== $user->id) {
            abort(403, 'This patient is not assigned to you.');
        }

        $validated = $request->validate([
            'consultation_notes' => 'required|string|min:3|max:5000',
        ]);

        $patient->update([
            'consultation_notes' => $validated['consultation_notes'],
        ]);

        AuditableService::logConsultationNotesSave($patient);

        return redirect()->back()->with('success', 'Consultation notes saved.');
    }

    /**
     * Create consolidated invoice(s) for selected services.
     *
     * Groups selected catalog items by department and creates ONE invoice
     * per department, each with multiple InvoiceItem rows.
     * Also supports a manual "consultation" entry (free-text + amount).
     */
    public function createInvoice(Request $request, Patient $patient): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user->is_independent, 403, 'Independent doctors do not have access to consultation features.');
        if ($patient->doctor_id !== $user->id) {
            abort(403, 'This patient is not assigned to you.');
        }

        $validated = $request->validate([
            // Catalog-based services (array of service_catalog IDs)
            'services' => 'nullable|array',
            'services.*' => 'exists:service_catalog,id',
            // Manual consultation entry
            'manual_department' => 'nullable|in:lab,radiology,pharmacy,consultation',
            'manual_service_name' => 'nullable|string|max:255',
            'manual_amount' => 'nullable|numeric|min:0.01',
        ]);

        $catalogIds = $validated['services'] ?? [];
        $hasManual = !empty($validated['manual_service_name']);

        if (empty($catalogIds) && !$hasManual) {
            return redirect()->back()->withErrors('Please select at least one service or enter a custom service name.');
        }

        $createdInvoices = [];

        DB::transaction(function () use ($catalogIds, $hasManual, $validated, $patient, $user, &$createdInvoices) {
            // --- Catalog-based services: group by department → one invoice each ---
            if (!empty($catalogIds)) {
                $services = ServiceCatalog::whereIn('id', $catalogIds)->get();
                $grouped = $services->groupBy('department');

                foreach ($grouped as $department => $deptServices) {
                    $totalAmount = $deptServices->sum('price');
                    $serviceNames = $deptServices->pluck('name')->implode(', ');

                    $invoice = Invoice::create([
                        'patient_id' => $patient->id,
                        'patient_type' => 'clinic',
                        'department' => $department,
                        'service_name' => $serviceNames,
                        'total_amount' => $totalAmount,
                        'net_amount' => $totalAmount,
                        'prescribing_doctor_id' => $user->id,
                        'created_by_user_id' => $user->id,
                        'status' => Invoice::STATUS_PENDING,
                    ]);

                    // Create InvoiceItem rows for each service
                    foreach ($deptServices as $svc) {
                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'service_catalog_id' => $svc->id,
                            'description' => $svc->name,
                            'quantity' => 1,
                            'unit_price' => $svc->price,
                            'line_total' => $svc->price,
                        ]);
                    }

                    $createdInvoices[] = $invoice;
                }
            }

            // --- Manual (consultation/custom) entry ---
            if ($hasManual) {
                $dept = $validated['manual_department'] ?? 'consultation';
                $manualAmount = $validated['manual_amount'] ?? 0;
                $invoice = Invoice::create([
                    'patient_id' => $patient->id,
                    'patient_type' => 'clinic',
                    'department' => $dept,
                    'service_name' => $validated['manual_service_name'],
                    'total_amount' => $manualAmount,
                    'net_amount' => $manualAmount,
                    'prescribing_doctor_id' => $user->id,
                    'created_by_user_id' => $user->id,
                    'status' => Invoice::STATUS_PENDING,
                ]);

                $createdInvoices[] = $invoice;
            }
        });

        // Notify department staff for each created invoice
        $deptRoleMap = [
            'lab' => 'Laboratory',
            'radiology' => 'Radiology',
            'pharmacy' => 'Pharmacy',
        ];

        foreach ($createdInvoices as $inv) {
            $roleName = $deptRoleMap[$inv->department] ?? null;
            if ($roleName) {
                $deptUsers = User::role($roleName)->get();
                foreach ($deptUsers as $deptUser) {
                    $deptUser->notify(new InvoiceStatusChanged(
                        $inv->id,
                        'none',
                        'pending',
                        $inv->department,
                    ));
                }
            }

            // Notify receptionists for lab/radiology invoices (upfront payment collection needed)
            if (in_array($inv->department, ['lab', 'radiology'])) {
                $receptionists = User::role('Receptionist')->get();
                foreach ($receptionists as $receptionist) {
                    $receptionist->notify(new InvoiceStatusChanged(
                        $inv->id,
                        'none',
                        'pending',
                        $inv->department,
                    ));
                }
            }
        }

        $count = count($createdInvoices);
        $ids = collect($createdInvoices)->pluck('id')->implode(', ');
        $msg = $count === 1
            ? "Invoice #{$ids} created successfully."
            : "{$count} invoices created (#{$ids}) — one per department.";

        return redirect()->back()->with('success', $msg);
    }
}
