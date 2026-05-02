<?php

namespace App\Http\Controllers\Triage;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\TriageVital;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TriageController extends Controller
{
    /**
     * Show list of registered patients waiting for triage.
     */
    public function index(): View
    {
        $patients = Patient::whereIn('status', ['registered', 'triage'])->get();

        return view('triage.patients.index', [
            'patients' => $patients,
        ]);
    }

    /**
     * Show vitals form for a patient.
     */
    public function showVitals(Patient $patient): View
    {
        // Allow triage for registered or in-triage patients
        if (!in_array($patient->status, ['registered', 'triage'])) {
            abort(403);
        }

        return view('triage.patients.vitals', [
            'patient' => $patient,
        ]);
    }

    /**
     * Save patient vitals and update status to triage.
     */
    public function saveVitals(Patient $patient): RedirectResponse
    {
        if (!in_array($patient->status, ['registered', 'triage'])) {
            abort(403);
        }

        $validated = request()->validate([
            'blood_pressure' => 'nullable|string',
            'temperature' => 'nullable|numeric',
            'heart_rate' => 'nullable|numeric',
            'respiratory_rate' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'oxygen_saturation' => 'nullable|numeric',
            'chief_complaint' => 'nullable|string|max:500',
            'priority' => 'nullable|string|in:low,normal,high,urgent,critical,emergency',
            'notes' => 'nullable|string',
            'send_to_doctor' => 'nullable|in:0,1',
        ]);

        TriageVital::create([
            'patient_id' => $patient->id,
            'blood_pressure' => $validated['blood_pressure'] ?? null,
            'temperature' => $validated['temperature'] ?? null,
            'pulse_rate' => $validated['heart_rate'] ?? null,
            'respiratory_rate' => $validated['respiratory_rate'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'oxygen_saturation' => $validated['oxygen_saturation'] ?? null,
            'chief_complaint' => $validated['chief_complaint'] ?? null,
            'priority' => $validated['priority'] ?? 'normal',
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        $sendNow = ($validated['send_to_doctor'] ?? '0') === '1';

        if ($sendNow) {
            $patient->update([
                'status' => 'with_doctor',
                'triage_started_at' => $patient->triage_started_at ?? now(),
                'doctor_started_at' => now(),
            ]);
            return redirect()->route('triage.patients.index')->with('success', 'Vitals recorded and patient sent to doctor.');
        }

        $patient->update([
            'status' => 'triage',
            'triage_started_at' => now(),
        ]);

        return redirect()->route('triage.patients.index')->with('success', 'Vitals recorded. Patient is in triage queue.');
    }

    /**
     * Send patient from triage to doctor.
     */
    public function sendToDoctor(Patient $patient): RedirectResponse
    {
        if ($patient->status !== 'triage') {
            abort(403);
        }

        $patient->update([
            'status' => 'with_doctor',
            'doctor_started_at' => now(),
        ]);

        return redirect()->route('triage.patients.index')->with('success', 'Patient sent to assigned doctor.');
    }
}
