@extends('layouts.app')
@section('title', 'My Availability — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-calendar-check me-2" style="color:var(--accent-primary);"></i>My Availability</h2>
            <p class="page-subtitle mb-0">Manage your consultation slots for the next 30 days</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSlotModal">
            <i class="bi bi-plus-circle me-1"></i>Add Slot
        </button>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4 mt-1">
        {{-- Upcoming (date-specific) slots --}}
        <div class="col-lg-7">
            <div class="glass-card p-4 fade-in delay-1">
                <h5 class="mb-3"><i class="bi bi-calendar3 me-2" style="color:var(--accent-primary);"></i>Upcoming Slots (Next 30 Days)</h5>
                @if($upcomingSlots->isEmpty())
                    <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>No specific date slots scheduled.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Duration</th>
                                <th>Slots</th>
                                <th>Room</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($upcomingSlots as $slot)
                            <tr>
                                <td>{{ $slot->date->format('d M Y') }}</td>
                                <td>{{ \Carbon\Carbon::createFromTimeString($slot->start_time)->format('H:i') }} – {{ \Carbon\Carbon::createFromTimeString($slot->end_time)->format('H:i') }}</td>
                                <td>{{ $slot->slot_duration_mins }} min</td>
                                <td><span class="badge bg-primary">{{ $slot->slot_count }}</span></td>
                                <td>{{ $slot->room?->name ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('doctor.availability.destroy', $slot) }}" onsubmit="return confirm('Remove this slot?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-danger btn-xs py-0 px-2">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        {{-- Recurring slots --}}
        <div class="col-lg-5">
            <div class="glass-card p-4 fade-in delay-2">
                <h5 class="mb-3"><i class="bi bi-arrow-repeat me-2" style="color:var(--accent-primary);"></i>Recurring Weekly Slots</h5>
                @if($recurringSlots->isEmpty())
                    <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>No recurring slots configured.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Slots</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recurringSlots as $slot)
                            <tr>
                                <td>{{ $dayNames[$slot->day_of_week] ?? '—' }}</td>
                                <td>{{ \Carbon\Carbon::createFromTimeString($slot->start_time)->format('H:i') }} – {{ \Carbon\Carbon::createFromTimeString($slot->end_time)->format('H:i') }}</td>
                                <td><span class="badge bg-info">{{ $slot->slot_count }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('doctor.availability.destroy', $slot) }}" onsubmit="return confirm('Remove this recurring slot?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-danger btn-xs py-0 px-2">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Add Slot Modal --}}
<div class="modal fade" id="addSlotModal" tabindex="-1" aria-labelledby="addSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('doctor.availability.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addSlotModalLabel"><i class="bi bi-calendar-plus me-2"></i>Add Availability Slot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- Recurring toggle --}}
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring" value="1"
                               onchange="toggleRecurring(this.checked)">
                        <label class="form-check-label" for="is_recurring">Recurring weekly slot</label>
                    </div>

                    {{-- Date (non-recurring) --}}
                    <div class="mb-3" id="dateField">
                        <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date" name="date"
                               min="{{ today()->toDateString() }}" value="{{ old('date') }}">
                    </div>

                    {{-- Day of week (recurring) --}}
                    <div class="mb-3 d-none" id="dowField">
                        <label for="day_of_week" class="form-label">Day of Week <span class="text-danger">*</span></label>
                        <select class="form-select" id="day_of_week" name="day_of_week">
                            @foreach($dayNames as $i => $day)
                                <option value="{{ $i }}" {{ old('day_of_week') == $i ? 'selected' : '' }}>{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="start_time" name="start_time"
                                   value="{{ old('start_time', '09:00') }}" required>
                        </div>
                        <div class="col-6">
                            <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="end_time" name="end_time"
                                   value="{{ old('end_time', '13:00') }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="slot_duration_mins" class="form-label">Slot Duration (minutes)</label>
                        <select class="form-select" id="slot_duration_mins" name="slot_duration_mins">
                            @foreach([15, 20, 30, 45, 60, 90, 120] as $mins)
                                <option value="{{ $mins }}" {{ old('slot_duration_mins', 30) == $mins ? 'selected' : '' }}>{{ $mins }} min</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="room_id" class="form-label">Room (optional)</label>
                        <select class="form-select" id="room_id" name="room_id">
                            <option value="">— No specific room —</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>
                                    {{ $room->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i>Save Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleRecurring(checked) {
    document.getElementById('dateField').classList.toggle('d-none', checked);
    document.getElementById('dowField').classList.toggle('d-none', !checked);
}

// Re-open modal with errors if validation failed
@if($errors->any())
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('addSlotModal'));
    modal.show();
    // Restore recurring state from old input
    @if(old('is_recurring'))
    toggleRecurring(true);
    document.getElementById('is_recurring').checked = true;
    @endif
});
@endif
</script>
@endpush
