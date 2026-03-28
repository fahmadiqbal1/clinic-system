<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentCancelled;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Appointment::with(['patient', 'doctor', 'bookedBy']);

        $filter = $request->query('filter', 'upcoming');

        if ($filter === 'today') {
            $query->today();
        } elseif ($filter === 'cancelled') {
            $query->where('status', Appointment::STATUS_CANCELLED);
        } elseif ($filter === 'all') {
            // no filter
        } else {
            // default: upcoming
            $query->upcoming();
        }

        $appointments = $query->latest('scheduled_at')->paginate(20);

        return view('receptionist.appointments.index', compact('appointments', 'filter'));
    }

    public function create(): View
    {
        $patients = Patient::orderBy('first_name')->get();
        $doctors = User::role('Doctor')->orderBy('name')->get();
        $types = [
            Appointment::TYPE_FIRST_VISIT => 'First Visit',
            Appointment::TYPE_FOLLOW_UP => 'Follow Up',
            Appointment::TYPE_CONSULTATION => 'Consultation',
            Appointment::TYPE_PROCEDURE => 'Procedure',
            Appointment::TYPE_EMERGENCY => 'Emergency',
        ];

        return view('receptionist.appointments.create', compact('patients', 'doctors', 'types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'required|exists:users,id',
            'scheduled_at' => 'required|date|after:now',
            'type' => 'required|in:' . implode(',', [
                Appointment::TYPE_FIRST_VISIT,
                Appointment::TYPE_FOLLOW_UP,
                Appointment::TYPE_CONSULTATION,
                Appointment::TYPE_PROCEDURE,
                Appointment::TYPE_EMERGENCY,
            ]),
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $appointment = Appointment::create([
            'patient_id' => $validated['patient_id'],
            'doctor_id' => $validated['doctor_id'],
            'scheduled_at' => $validated['scheduled_at'],
            'type' => $validated['type'],
            'status' => Appointment::STATUS_SCHEDULED,
            'reason' => $validated['reason'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'booked_by' => $user->id,
        ]);

        $appointment->load(['patient', 'doctor']);

        // Notify the assigned doctor
        $appointment->doctor->notify(new AppointmentBooked($appointment));

        return redirect()->route('receptionist.appointments.show', $appointment)
            ->with('success', 'Appointment scheduled successfully.');
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load(['patient', 'doctor', 'bookedBy', 'cancelledBy']);

        return view('receptionist.appointments.show', compact('appointment'));
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        if (!$appointment->canBeCancelled()) {
            return redirect()->back()->withErrors('This appointment cannot be cancelled.');
        }

        $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancellation_reason' => $request->input('cancellation_reason'),
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        $appointment->load(['patient', 'doctor']);

        // Notify the assigned doctor
        $appointment->doctor->notify(new AppointmentCancelled($appointment));

        return redirect()->route('receptionist.appointments.show', $appointment)
            ->with('success', 'Appointment cancelled.');
    }
}
