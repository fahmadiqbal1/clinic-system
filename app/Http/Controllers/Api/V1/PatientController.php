<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\AiAnalysisResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PatientController extends Controller
{
    /**
     * Display a listing of patients.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::query()
            ->when($request->search, function ($query, $search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->doctor_id, function ($query, $doctorId) {
                $query->where('doctor_id', $doctorId);
            })
            ->latest()
            ->paginate($request->per_page ?? 15);

        return PatientResource::collection($patients);
    }

    /**
     * Store a newly created patient.
     */
    public function store(Request $request): PatientResource
    {
        $this->authorize('create', Patient::class);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
            'doctor_id' => 'nullable|exists:users,id',
            'registration_type' => 'required|in:walk_in,appointment,emergency,referral',
        ]);

        $patient = Patient::create($validated);

        return new PatientResource($patient);
    }

    /**
     * Display the specified patient.
     */
    public function show(Patient $patient): PatientResource
    {
        $this->authorize('view', $patient);

        $patient->load(['doctor', 'triageVitals', 'prescriptions']);

        return new PatientResource($patient);
    }

    /**
     * Update the specified patient.
     */
    public function update(Request $request, Patient $patient): PatientResource
    {
        $this->authorize('update', $patient);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'nullable|email|max:255',
            'gender' => 'sometimes|required|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
            'doctor_id' => 'nullable|exists:users,id',
            'consultation_notes' => 'nullable|string',
        ]);

        $patient->update($validated);

        return new PatientResource($patient);
    }

    /**
     * Remove the specified patient.
     */
    public function destroy(Patient $patient)
    {
        $this->authorize('delete', $patient);

        $patient->delete();

        return response()->noContent();
    }

    /**
     * Get patient's invoices.
     */
    public function invoices(Patient $patient): AnonymousResourceCollection
    {
        $this->authorize('view', $patient);

        $invoices = $patient->invoices()
            ->with(['items.serviceCatalog', 'prescribingDoctor'])
            ->latest()
            ->paginate(15);

        return InvoiceResource::collection($invoices);
    }

    /**
     * Get patient's AI analyses.
     */
    public function analyses(Patient $patient): AnonymousResourceCollection
    {
        $this->authorize('view', $patient);

        $analyses = $patient->aiAnalyses()
            ->where('status', 'completed')
            ->with('requester')
            ->latest()
            ->paginate(15);

        return AiAnalysisResource::collection($analyses);
    }
}
