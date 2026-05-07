<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ClinicRoom;
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
        $filter = $request->query('filter', 'upcoming');

        $query = Appointment::with(['patient', 'doctor', 'bookedBy', 'room']);

        if ($filter === 'today') {
            $query->today();
        } elseif ($filter === 'cancelled') {
            $query->where('status', Appointment::STATUS_CANCELLED);
        } elseif ($filter === 'all') {
            // no filter
        } else {
            $query->upcoming();
        }

        $appointments = $query->latest('scheduled_at')->paginate(20);

        // Build calendar events for the current visible month (+/- 1 month buffer)
        $monthStart = now()->startOfMonth()->subMonth();
        $monthEnd   = now()->endOfMonth()->addMonth();

        $calendarEvents = Appointment::with(['patient', 'doctor', 'room'])
            ->whereBetween('scheduled_at', [$monthStart, $monthEnd])
            ->get()
            ->map(fn (Appointment $a) => [
                'id'    => $a->id,
                'title' => $a->patient
                    ? ($a->patient->first_name . ' ' . $a->patient->last_name)
                    : ($a->pre_booked_name ?? 'Walk-in'),
                'start' => $a->scheduled_at->toIso8601String(),
                'end'   => $a->ended_at?->toIso8601String(),
                'color' => match ($a->status) {
                    'confirmed'   => '#22c55e',
                    'scheduled'   => '#3b82f6',
                    'in_progress' => '#f59e0b',
                    'cancelled'   => '#ef4444',
                    'no_show'     => '#6b7280',
                    default       => '#8b5cf6',
                },
                'classNames' => $a->patient_id === null && in_array($a->source, ['phone', 'omnidimension'])
                    ? ['pre-booked-event']
                    : [],
                'extendedProps' => [
                    'doctor'     => $a->doctor?->name,
                    'room'       => $a->room?->name,
                    'status'     => $a->status_label,
                    'type'       => $a->type_label,
                    'source'     => $a->source,
                    'preBooked'  => $a->patient_id === null,
                    'detailUrl'  => route('receptionist.appointments.show', $a),
                ],
            ]);

        return view('receptionist.appointments.index', compact('appointments', 'filter', 'calendarEvents'));
    }

    public function create(): View
    {
        $patients = Patient::orderBy('first_name')->get();
        $doctors  = User::role('Doctor')->orderBy('name')->get();
        $rooms    = ClinicRoom::active()->orderBy('sort_order')->get();
        $types = [
            Appointment::TYPE_FIRST_VISIT  => 'First Visit',
            Appointment::TYPE_FOLLOW_UP    => 'Follow Up',
            Appointment::TYPE_CONSULTATION => 'Consultation',
            Appointment::TYPE_PROCEDURE    => 'Procedure',
            Appointment::TYPE_EMERGENCY    => 'Emergency',
        ];

        return view('receptionist.appointments.create', compact('patients', 'doctors', 'rooms', 'types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id'  => 'required|exists:patients,id',
            'doctor_id'   => 'required|exists:users,id',
            'room_id'     => 'nullable|exists:clinic_rooms,id',
            'scheduled_at' => 'required|date|after:now',
            'type' => 'required|in:' . implode(',', [
                Appointment::TYPE_FIRST_VISIT,
                Appointment::TYPE_FOLLOW_UP,
                Appointment::TYPE_CONSULTATION,
                Appointment::TYPE_PROCEDURE,
                Appointment::TYPE_EMERGENCY,
            ]),
            'reason' => 'nullable|string|max:500',
            'notes'  => 'nullable|string|max:1000',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $appointment = Appointment::create([
            'patient_id'   => $validated['patient_id'],
            'doctor_id'    => $validated['doctor_id'],
            'room_id'      => $validated['room_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'type'         => $validated['type'],
            'status'       => Appointment::STATUS_SCHEDULED,
            'reason'       => $validated['reason'] ?? null,
            'notes'        => $validated['notes'] ?? null,
            'booked_by'    => $user->id,
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
