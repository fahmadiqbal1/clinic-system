@extends('layouts.app')
@section('title', 'Schedule Appointment — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-calendar-plus me-2" style="color:var(--accent-success);"></i>Schedule Appointment</h1>
            <p class="page-subtitle">Book a new appointment for a patient</p>
        </div>
        <a href="{{ route('receptionist.appointments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Appointments</a>
    </div>

    <div class="glass-card p-4 fade-in delay-1">
        <form action="{{ route('receptionist.appointments.store') }}" method="POST">
            @csrf

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="patient_id" class="form-label"><i class="bi bi-person me-1" style="color:var(--accent-primary);"></i>Patient <span class="text-danger">*</span></label>
                    <select class="form-select @error('patient_id') is-invalid @enderror" id="patient_id" name="patient_id" required>
                        <option value="">Select Patient</option>
                        @foreach($patients as $patient)
                            <option value="{{ $patient->id }}" {{ old('patient_id') == $patient->id ? 'selected' : '' }}>{{ $patient->first_name }} {{ $patient->last_name }}</option>
                        @endforeach
                    </select>
                    @error('patient_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="doctor_id" class="form-label"><i class="bi bi-person-badge me-1" style="color:var(--accent-info);"></i>Doctor <span class="text-danger">*</span></label>
                    <select class="form-select @error('doctor_id') is-invalid @enderror" id="doctor_id" name="doctor_id" required>
                        <option value="">Select Doctor</option>
                        @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}" {{ old('doctor_id') == $doctor->id ? 'selected' : '' }}>Dr. {{ $doctor->name }}</option>
                        @endforeach
                    </select>
                    @error('doctor_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="room_id" class="form-label"><i class="bi bi-door-open me-1" style="color:var(--accent-success);"></i>Room <span class="text-muted">(optional)</span></label>
                    <select class="form-select @error('room_id') is-invalid @enderror" id="room_id" name="room_id">
                        <option value="">— No room assigned —</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>
                                {{ $room->name }} ({{ ucfirst($room->type) }})
                            </option>
                        @endforeach
                    </select>
                    @error('room_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="scheduled_at" class="form-label"><i class="bi bi-clock me-1" style="color:var(--accent-warning);"></i>Date & Time <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control @error('scheduled_at') is-invalid @enderror" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at') }}" required>
                    {{-- Quick time slot chips --}}
                    <div class="d-flex flex-wrap gap-1 mt-2" id="timeSlotChips">
                        <small class="text-muted w-100 mb-1">Quick slots:</small>
                        <button type="button" class="btn btn-xs btn-outline-secondary timeslot-chip" data-offset="60">+1h today</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary timeslot-chip" data-offset="120">+2h today</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary timeslot-chip" data-day="1" data-hour="9">Tomorrow 9am</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary timeslot-chip" data-day="1" data-hour="10">Tomorrow 10am</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary timeslot-chip" data-day="1" data-hour="14">Tomorrow 2pm</button>
                    </div>
                    @error('scheduled_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-tag me-1" style="color:var(--accent-secondary);"></i>Type <span class="text-danger">*</span></label>
                    <input type="hidden" id="type" name="type" value="{{ old('type') }}" required>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="typeChips">
                        @foreach($types as $value => $label)
                        <button type="button"
                                class="btn btn-sm type-chip {{ old('type') === $value ? 'btn-primary' : 'btn-outline-secondary' }}"
                                data-value="{{ $value }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    @error('type')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="reason" class="form-label"><i class="bi bi-chat-text me-1" style="color:var(--accent-info);"></i>Reason</label>
                {{-- Common reason chips --}}
                <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach(['Follow-up visit','Routine checkup','Review test results','Prescription renewal','New complaint','Post-operative review','Vaccination','Referral consultation'] as $r)
                    <button type="button"
                            class="btn btn-xs btn-outline-info reason-chip"
                            data-reason="{{ $r }}">{{ $r }}</button>
                    @endforeach
                </div>
                <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="2" maxlength="500">{{ old('reason') }}</textarea>
                @error('reason')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="notes" class="form-label"><i class="bi bi-sticky me-1" style="color:var(--accent-warning);"></i>Notes</label>
                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('receptionist.appointments.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-check me-1"></i>Schedule Appointment</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Appointment type chips ──────────────────────────────────────────────
    document.querySelectorAll('.type-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.type-chip').forEach(function (b) {
                b.classList.remove('btn-primary'); b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary'); btn.classList.add('btn-primary');
            document.getElementById('type').value = btn.dataset.value;
        });
    });

    // ── Quick time slot chips ───────────────────────────────────────────────
    document.querySelectorAll('.timeslot-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var now = new Date();
            var target;
            if (btn.dataset.offset) {
                target = new Date(now.getTime() + parseInt(btn.dataset.offset) * 60000);
                target.setSeconds(0, 0);
            } else {
                var d = new Date(now);
                d.setDate(d.getDate() + (parseInt(btn.dataset.day) || 0));
                d.setHours(parseInt(btn.dataset.hour) || 9, 0, 0, 0);
                target = d;
            }
            // Format to datetime-local value (YYYY-MM-DDTHH:MM)
            var pad = function (n) { return n < 10 ? '0' + n : n; };
            var val = target.getFullYear() + '-' + pad(target.getMonth() + 1) + '-' + pad(target.getDate())
                    + 'T' + pad(target.getHours()) + ':' + pad(target.getMinutes());
            document.getElementById('scheduled_at').value = val;
        });
    });

    // ── Reason chips ────────────────────────────────────────────────────────
    document.querySelectorAll('.reason-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ta = document.getElementById('reason');
            ta.value = btn.dataset.reason;
        });
    });

});
</script>
@endpush
