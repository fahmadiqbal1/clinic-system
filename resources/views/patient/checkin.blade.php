@extends('layouts.app')
@section('title', 'Check In — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="text-center fade-in">
                <i class="bi bi-hospital me-2" style="font-size:3rem; color:var(--accent-primary);"></i>
                <div class="kiosk-name mt-3">{{ $patient->first_name }} {{ $patient->last_name }}</div>
                <p class="page-subtitle fs-5 mt-2">Welcome to Aviva HealthCare</p>
            </div>

            @if(session('success'))
                <div class="alert alert-success mt-4 fade-in delay-1 text-center fs-5">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info mt-4 fade-in delay-1 text-center fs-5">
                    <i class="bi bi-info-circle me-2"></i>{{ session('info') }}
                </div>
            @endif

            @if($alreadyCheckedIn)
                <div class="glass-card p-5 mt-4 fade-in delay-1 text-center" style="border:2px solid var(--accent-success);">
                    <i class="bi bi-check-circle-fill" style="font-size:4rem; color:var(--accent-success);"></i>
                    <h3 class="mt-3" style="color:var(--accent-success);">You're checked in</h3>
                    <p class="fs-5 mt-2" style="color:var(--text-muted);">Please wait to be called. The triage team has been notified.</p>
                </div>
            @else
                <div class="glass-card p-5 mt-4 fade-in delay-1 text-center">
                    <p class="fs-5 mb-4" style="color:var(--text-muted);">Tap the button below to let us know you've arrived.</p>
                    <form action="{{ route('patient.checkin.confirm') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success kiosk-btn">
                            <i class="bi bi-check-circle me-2"></i>I'm Here — Check In
                        </button>
                    </form>
                </div>
            @endif

            <div class="text-center mt-4 fade-in delay-2">
                <a href="{{ route('patient.dashboard') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.kiosk-name {
    font-size: 3rem;
    font-weight: 800;
}
.kiosk-btn {
    font-size: 1.5rem;
    padding: 1.5rem 3rem;
}
</style>
@endpush
