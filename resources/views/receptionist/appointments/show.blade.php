@extends('layouts.app')
@section('title', 'Appointment #' . $appointment->id . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-calendar-check me-2" style="color:var(--accent-primary);"></i>Appointment #{{ $appointment->id }}</h1>
            <p class="page-subtitle">Appointment details and management</p>
        </div>
        <a href="{{ route('receptionist.appointments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Appointments</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success fade-in">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger fade-in">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="glass-card p-4 fade-in delay-1">
        <div class="row">
            <div class="col-md-6">
                <h5 class="mb-3"><i class="bi bi-info-circle me-1" style="color:var(--accent-primary);"></i>Details</h5>
                <div class="info-grid" style="grid-template-columns: 1fr;">
                    <div class="info-grid-item">
                        <span class="info-label">Patient</span>
                        <span class="info-value">
                            <a href="{{ route('receptionist.patients.show', $appointment->patient) }}">{{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}</a>
                        </span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Doctor</span>
                        <span class="info-value">Dr. {{ $appointment->doctor->name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Scheduled At</span>
                        <span class="info-value">{{ $appointment->scheduled_at->format('d M Y, H:i') }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Type</span>
                        <span class="info-value">{{ $appointment->type_label }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            @php
                                $badge = match($appointment->status) {
                                    'scheduled' => 'badge-glass-primary',
                                    'confirmed' => 'badge-glass-success',
                                    'cancelled' => 'badge-glass-danger',
                                    'completed' => 'badge-glass-success',
                                    default => 'badge-glass-secondary',
                                };
                            @endphp
                            <span class="badge {{ $badge }}">{{ $appointment->status_label }}</span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <h5 class="mb-3"><i class="bi bi-chat-text me-1" style="color:var(--accent-info);"></i>Notes</h5>
                <div class="info-grid" style="grid-template-columns: 1fr;">
                    <div class="info-grid-item">
                        <span class="info-label">Reason</span>
                        <span class="info-value">{{ $appointment->reason ?? 'N/A' }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Notes</span>
                        <span class="info-value">{{ $appointment->notes ?? 'N/A' }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Booked By</span>
                        <span class="info-value">{{ $appointment->bookedBy->name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Created At</span>
                        <span class="info-value">{{ $appointment->created_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($appointment->status === 'cancelled')
        <div class="glass-card p-4 mt-4 fade-in delay-2">
            <h5 class="mb-3"><i class="bi bi-x-circle me-1" style="color:var(--accent-danger);"></i>Cancellation Details</h5>
            <div class="info-grid" style="grid-template-columns: 1fr;">
                <div class="info-grid-item">
                    <span class="info-label">Reason</span>
                    <span class="info-value">{{ $appointment->cancellation_reason ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Cancelled By</span>
                    <span class="info-value">{{ $appointment->cancelledBy->name ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Cancelled At</span>
                    <span class="info-value">{{ $appointment->cancelled_at?->format('d M Y, H:i') ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
    @endif

    @if($appointment->canBeCancelled())
        <div class="glass-card p-4 mt-4 fade-in delay-2">
            <h5 class="mb-3"><i class="bi bi-exclamation-triangle me-1" style="color:var(--accent-danger);"></i>Cancel Appointment</h5>
            <form action="{{ route('receptionist.appointments.cancel', $appointment) }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="cancellation_reason" class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('cancellation_reason') is-invalid @enderror" id="cancellation_reason" name="cancellation_reason" rows="3" required maxlength="500">{{ old('cancellation_reason') }}</textarea>
                    @error('cancellation_reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?')"><i class="bi bi-x-circle me-1"></i>Cancel Appointment</button>
            </form>
        </div>
    @endif
</div>
@endsection
