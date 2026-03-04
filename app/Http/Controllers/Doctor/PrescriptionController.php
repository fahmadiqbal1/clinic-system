<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Notifications\PrescriptionCreated;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    /**
     * Show prescription creation form for a patient.
     */
    public function create(Patient $patient): View
    {
        if ($patient->doctor_id !== Auth::id()) {
            abort(403, 'This patient is not assigned to you.');
        }

        $medications = InventoryItem::where('department', 'pharmacy')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $services = ServiceCatalog::where('is_active', true)
            ->orderBy('department')
            ->orderBy('name')
            ->get();

        return view('doctor.prescriptions.create', [
            'patient' => $patient,
            'medications' => $medications,
            'services' => $services,
        ]);
    }

    /**
     * Store a new prescription with items.
     */
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        if ($patient->doctor_id !== Auth::id()) {
            abort(403, 'This patient is not assigned to you.');
        }

        $validated = $request->validate([
            'diagnosis' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'nullable|exists:inventory_items,id',
            'items.*.medication_name' => 'required|string|max:255',
            'items.*.dosage' => 'nullable|string|max:255',
            'items.*.frequency' => 'nullable|string|max:255',
            'items.*.duration' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.instructions' => 'nullable|string|max:1000',
        ]);

        $prescription = DB::transaction(function () use ($validated, $patient) {
            $prescription = Prescription::create([
                'patient_id' => $patient->id,
                'doctor_id' => Auth::id(),
                'diagnosis' => $validated['diagnosis'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'active',
            ]);

            foreach ($validated['items'] as $itemData) {
                $prescription->items()->create([
                    'inventory_item_id' => $itemData['inventory_item_id'] ?? null,
                    'medication_name' => $itemData['medication_name'],
                    'dosage' => $itemData['dosage'] ?? null,
                    'frequency' => $itemData['frequency'] ?? null,
                    'duration' => $itemData['duration'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'instructions' => $itemData['instructions'] ?? null,
                ]);
            }

            return $prescription;
        });

        // Notify all pharmacy users
        $pharmacyUsers = User::role('Pharmacy')->get();
        foreach ($pharmacyUsers as $pharmUser) {
            $pharmUser->notify(new PrescriptionCreated(
                $prescription->id,
                $patient->first_name . ' ' . $patient->last_name,
                Auth::user()->name,
            ));
        }

        return redirect()->route('doctor.consultation.show', $patient)
            ->with('success', 'Prescription #' . $prescription->id . ' created with ' . count($validated['items']) . ' item(s). Sent to pharmacy queue.');
    }

    /**
     * Show prescriptions list for the logged-in doctor.
     */
    public function index(): View
    {
        $prescriptions = Prescription::where('doctor_id', Auth::id())
            ->with(['patient', 'items'])
            ->latest()
            ->paginate(20);

        return view('doctor.prescriptions.index', [
            'prescriptions' => $prescriptions,
        ]);
    }
}
