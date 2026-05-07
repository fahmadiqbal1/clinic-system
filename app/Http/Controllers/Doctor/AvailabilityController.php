<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\ClinicRoom;
use App\Models\DoctorAvailabilitySlot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    /**
     * Show the doctor's availability calendar.
     *
     * GET /doctor/availability
     */
    public function index(): View
    {
        $doctorId = auth()->id();

        // Slots for the next 30 days (non-recurring with a specific date)
        $upcomingSlots = DoctorAvailabilitySlot::forDoctor($doctorId)
            ->active()
            ->where('is_recurring', false)
            ->whereBetween('date', [today(), today()->addDays(30)])
            ->orderBy('date')
            ->orderBy('start_time')
            ->with('room')
            ->get();

        // Recurring slots
        $recurringSlots = DoctorAvailabilitySlot::forDoctor($doctorId)
            ->active()
            ->where('is_recurring', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->with('room')
            ->get();

        $rooms = ClinicRoom::active()->get();

        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return view('doctor.availability.index', compact(
            'upcomingSlots',
            'recurringSlots',
            'rooms',
            'dayNames'
        ));
    }

    /**
     * Store a new availability slot.
     *
     * POST /doctor/availability
     */
    public function store(Request $request): RedirectResponse
    {
        $isRecurring = (bool) $request->boolean('is_recurring');

        $rules = [
            'start_time'        => ['required', 'date_format:H:i'],
            'end_time'          => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration_mins' => ['required', 'integer', 'min:15', 'max:120'],
            'is_recurring'      => ['boolean'],
            'room_id'           => ['nullable', 'exists:clinic_rooms,id'],
        ];

        if ($isRecurring) {
            $rules['day_of_week'] = ['required', 'integer', 'min:0', 'max:6'];
            $rules['date']        = ['nullable', 'date'];
        } else {
            $rules['date']        = ['required', 'date', 'after_or_equal:today'];
            $rules['day_of_week'] = ['nullable', 'integer', 'min:0', 'max:6'];
        }

        $validated = $request->validate($rules);

        $doctorId = auth()->id();

        // Check for overlapping slots on the same doctor / date (or recurring day)
        $overlap = DoctorAvailabilitySlot::forDoctor($doctorId)
            ->active()
            ->where('is_recurring', $isRecurring)
            ->when(! $isRecurring && isset($validated['date']), fn ($q) => $q->whereDate('date', $validated['date']))
            ->when($isRecurring && isset($validated['day_of_week']), fn ($q) => $q->where('day_of_week', $validated['day_of_week']))
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                  ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                  ->orWhere(function ($inner) use ($validated) {
                      $inner->where('start_time', '<=', $validated['start_time'])
                            ->where('end_time', '>=', $validated['end_time']);
                  });
            })
            ->exists();

        if ($overlap) {
            return back()->withErrors(['start_time' => 'This time range overlaps with an existing slot.'])->withInput();
        }

        DoctorAvailabilitySlot::create([
            'doctor_id'          => $doctorId,
            'room_id'            => $validated['room_id'] ?? null,
            'date'               => $isRecurring ? null : ($validated['date'] ?? null),
            'day_of_week'        => $isRecurring ? ($validated['day_of_week'] ?? null) : null,
            'start_time'         => $validated['start_time'],
            'end_time'           => $validated['end_time'],
            'slot_duration_mins' => $validated['slot_duration_mins'],
            'is_recurring'       => $isRecurring,
            'is_active'          => true,
        ]);

        return redirect()->route('doctor.availability.index')
            ->with('success', 'Availability slot added successfully.');
    }

    /**
     * Soft-deactivate an availability slot.
     *
     * DELETE /doctor/availability/{slot}
     */
    public function destroy(DoctorAvailabilitySlot $slot): RedirectResponse
    {
        abort_unless($slot->doctor_id === auth()->id(), 403, 'You do not own this slot.');

        $slot->update(['is_active' => false]);

        return redirect()->route('doctor.availability.index')
            ->with('success', 'Availability slot removed.');
    }

    /**
     * Return available time windows for a doctor on a given date.
     * Public — no auth required (for city-facing booking feature).
     *
     * GET /api/doctor-availability?doctor_id=&date=
     */
    public function available(Request $request): JsonResponse
    {
        $request->validate([
            'doctor_id' => ['required', 'integer'],
            'date'      => ['required', 'date'],
        ]);

        $doctorId    = (int) $request->input('doctor_id');
        $parsedDate  = Carbon::parse($request->input('date'));
        $dow         = $parsedDate->dayOfWeek;

        $slots = DoctorAvailabilitySlot::forDoctor($doctorId)
            ->active()
            ->where(function ($q) use ($parsedDate, $dow) {
                $q->where(function ($inner) use ($parsedDate) {
                    $inner->where('is_recurring', false)
                          ->whereDate('date', $parsedDate->toDateString());
                })->orWhere(function ($inner) use ($dow) {
                    $inner->where('is_recurring', true)
                          ->where('day_of_week', $dow);
                });
            })
            ->orderBy('start_time')
            ->get()
            ->map(fn ($slot) => [
                'start_time'         => $slot->start_time,
                'end_time'           => $slot->end_time,
                'slot_duration_mins' => $slot->slot_duration_mins,
                'slot_count'         => $slot->slot_count,
                'room_id'            => $slot->room_id,
            ]);

        return response()->json(['date' => $parsedDate->toDateString(), 'slots' => $slots]);
    }
}
