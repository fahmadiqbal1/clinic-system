@extends('layouts.app')
@section('title', 'Pre-Booked Appointments — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-telephone-plus me-2" style="color:var(--accent-primary);"></i>Pre-Booked Appointments</h2>
            <p class="page-subtitle mb-0">Today's phone and OmniDimension bookings awaiting registration</p>
        </div>
        <a href="{{ route('receptionist.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="glass-card p-4 mt-3 fade-in delay-1">
        @if($appointments->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-calendar-x" style="font-size:2.5rem; color:var(--accent-primary);"></i>
                <p class="mt-3 text-muted">No pre-booked appointments for today.</p>
            </div>
        @else
        <div class="row g-3">
            @foreach($appointments as $appointment)
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 hover-lift h-100">
                    <div class="card-body">
                        {{-- Source badge --}}
                        @if($appointment->source === 'omnidimension')
                            <span class="badge bg-info mb-2"><i class="bi bi-robot me-1"></i>OmniDimension AI</span>
                        @else
                            <span class="badge bg-secondary mb-2"><i class="bi bi-telephone me-1"></i>Phone</span>
                        @endif

                        {{-- Caller info --}}
                        <h6 class="card-title mb-1">
                            <i class="bi bi-person me-1"></i>{{ $appointment->pre_booked_name ?? 'Unknown Caller' }}
                        </h6>
                        @if($appointment->pre_booked_phone)
                        <p class="text-muted small mb-1">
                            <i class="bi bi-telephone me-1"></i>{{ $appointment->pre_booked_phone }}
                        </p>
                        @endif

                        {{-- Doctor --}}
                        <p class="small mb-1">
                            <i class="bi bi-person-badge me-1" style="color:var(--accent-primary);"></i>
                            {{ $appointment->doctor?->name ?? 'Doctor not assigned' }}
                        </p>

                        {{-- Time --}}
                        <p class="small mb-2">
                            <i class="bi bi-clock me-1" style="color:var(--accent-primary);"></i>
                            {{ $appointment->scheduled_at?->format('H:i') ?? '—' }}
                        </p>

                        {{-- Notes --}}
                        @if($appointment->reason)
                        <p class="small text-muted mb-3">
                            <i class="bi bi-chat-left-text me-1"></i>{{ Str::limit($appointment->reason, 80) }}
                        </p>
                        @endif

                        {{-- Quick Register --}}
                        <form method="POST" action="{{ route('receptionist.pre-booked.register', $appointment) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm w-100"
                                    onclick="return confirm('Register patient and confirm this appointment?')">
                                <i class="bi bi-person-check me-1"></i>Quick Register
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
