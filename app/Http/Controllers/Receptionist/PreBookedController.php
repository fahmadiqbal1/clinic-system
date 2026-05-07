<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PreBookedController extends Controller
{
    /**
     * Show today's pre-booked (phone / OmniDimension) appointments.
     *
     * GET /receptionist/pre-booked
     */
    public function index(): View
    {
        $appointments = Appointment::with(['doctor'])
            ->whereDate('scheduled_at', today())
            ->whereIn('source', [Appointment::SOURCE_PHONE, Appointment::SOURCE_OMNIDIMENSION])
            ->orderBy('scheduled_at')
            ->get();

        return view('receptionist.pre-booked.index', compact('appointments'));
    }

    /**
     * Quick-register a pre-booked appointment by finding or creating a patient.
     *
     * POST /receptionist/pre-booked/{appointment}/register
     */
    public function quickRegister(Appointment $appointment, Request $request): RedirectResponse
    {
        abort_unless(
            in_array($appointment->source, [Appointment::SOURCE_PHONE, Appointment::SOURCE_OMNIDIMENSION]),
            422,
            'Appointment is not a pre-booked phone appointment.'
        );

        abort_if($appointment->patient_id !== null, 422, 'Appointment already has a registered patient.');

        // Find or create patient by phone number
        $phone   = $appointment->pre_booked_phone;
        $patient = null;

        if ($phone) {
            // Encrypted phone — iterate to find match (small dataset expected)
            $patient = Patient::whereNotNull('phone')->get()->first(fn ($p) => $p->phone === $phone);
        }

        if (! $patient) {
            $nameParts = explode(' ', $appointment->pre_booked_name ?? 'Unknown', 2);
            $patient   = Patient::create([
                'first_name'        => $nameParts[0],
                'last_name'         => $nameParts[1] ?? '',
                'phone'             => $phone,
                'status'            => 'registered',
                'registration_type' => 'opd',
                'registered_at'     => now(),
            ]);
        }

        $appointment->update([
            'patient_id'      => $patient->id,
            'pre_booked_name' => null,
            'status'          => Appointment::STATUS_CONFIRMED,
        ]);

        AuditLog::log(
            action:        'appointment_quick_registered',
            auditableType: 'App\Models\Appointment',
            auditableId:   $appointment->id,
            beforeState:   ['patient_id' => null, 'pre_booked_name' => $appointment->getOriginal('pre_booked_name')],
            afterState:    ['patient_id' => $patient->id, 'status' => Appointment::STATUS_CONFIRMED],
        );

        return redirect()->route('receptionist.patients.show', $patient)
            ->with('success', 'Patient registered and appointment confirmed.');
    }
}
