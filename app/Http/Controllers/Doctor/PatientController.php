<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    /**
     * Display the list of patients assigned to the doctor.
     * Only show patients with status = 'with_doctor'
     */
    public function index(): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Independent doctors manage referrals through their own portal
        abort_if($user->is_independent, 403, 'Independent doctors manage patients via the referral portal.');

        $currentStatus = request('status');

        $query = $user->patients();
        if ($currentStatus && in_array($currentStatus, ['registered', 'triage', 'with_doctor', 'completed'])) {
            $query->where('status', $currentStatus);
        }
        $patients = $query->latest()->get();

        $statusCounts = $user->patients()
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return view('doctor.patients.index', [
            'patients' => $patients,
            'currentStatus' => $currentStatus,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Display the specified patient.
     */
    public function show(Patient $patient): View
    {
        abort_if(Auth::user()->is_independent, 403, 'Independent doctors manage patients via the referral portal.');

        if ($patient->doctor_id !== Auth::user()->id) {
            abort(403);
        }

        return view('doctor.patients.show', [
            'patient' => $patient,
        ]);
    }

    /**
     * Complete patient consultation.
     */
    public function complete(Request $request, Patient $patient): RedirectResponse
    {
        abort_if(Auth::user()->is_independent, 403, 'Independent doctors manage patients via the referral portal.');

        if ($patient->doctor_id !== Auth::user()->id) {
            abort(403);
        }

        if ($patient->status !== 'with_doctor') {
            abort(403);
        }

        // Notes are saved via the separate save-notes route; use those if no notes in request
        $notes = $request->input('consultation_notes', $patient->consultation_notes);

        if (empty($notes) || strlen(trim($notes)) < 3) {
            return back()->withErrors(['consultation_notes' => 'Please save consultation notes before completing.']);
        }

        $patient->update([
            'status' => 'completed',
            'completed_at' => now(),
            'consultation_notes' => $notes,
        ]);

        return redirect()->route('doctor.patients.index')->with('success', 'Patient consultation completed.');
    }
}
