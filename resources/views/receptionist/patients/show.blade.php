@extends('layouts.app')
@section('title', $patient->first_name . ' ' . $patient->last_name . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="glass-card fade-in">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3" style="border-bottom:1px solid var(--glass-border);">
            <div>
                <h2 class="h4 fw-bold mb-1"><i class="bi bi-person-circle me-2" style="color:var(--accent-primary);"></i>{{ $patient->first_name }} {{ $patient->last_name }}</h2>
                <p class="page-subtitle mb-0">Patient <span class="code-tag">#{{ $patient->id }}</span></p>
            </div>
            <a href="{{ route('receptionist.patients.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Patients</a>
        </div>

            <!-- Basic Info -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <p class="small mb-1" style="color:var(--text-muted);">Phone</p>
                    <p class="fs-5 fw-semibold">{{ $patient->phone ?? 'N/A' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="small mb-1" style="color:var(--text-muted);">Gender</p>
                    <p class="fs-5 fw-semibold">{{ $patient->gender }}</p>
                </div>
                <div class="col-md-3">
                    <p class="small mb-1" style="color:var(--text-muted);">Date of Birth</p>
                    <p class="fs-5 fw-semibold">{{ $patient->date_of_birth?->format('d/m/Y') ?? 'N/A' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="small mb-1" style="color:var(--text-muted);">Status</p>
                    <p class="fs-5 fw-semibold">
                        @php
                            $badge = match($patient->status) {
                                'registered' => 'badge-glass-secondary',
                                'triage' => 'badge-glass-info',
                                'with_doctor' => 'badge-glass-warning',
                                'completed' => 'badge-glass-success',
                                default => 'badge-glass-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $patient->status)) }}</span>
                    </p>
                </div>
            </div>

            <!-- Doctor Assignment -->
            <div class="row mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                <div class="col-md-6">
                    <p class="small mb-1" style="color:var(--text-muted);">Assigned Doctor</p>
                    <p class="fs-5 fw-semibold">{{ $patient->doctor?->name ?? 'Not assigned' }}</p>
                </div>
                <div class="col-md-6">
                    <p class="small mb-1" style="color:var(--text-muted);">Registered At</p>
                    <p class="fs-5 fw-semibold">{{ $patient->registered_at?->format('M d, Y H:i') ?? $patient->created_at->format('M d, Y H:i') }}</p>
                </div>
            </div>

            <!-- Timeline -->
            <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2" style="color:var(--accent-warning);"></i>Timeline</h5>
                <div class="row">
                    <div class="col-md-3">
                        <p class="small mb-1" style="color:var(--text-muted);">Registered</p>
                        <p class="fw-semibold">{{ $patient->registered_at?->format('M d, Y H:i') ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-3">
                        <p class="small mb-1" style="color:var(--text-muted);">Triage Started</p>
                        <p class="fw-semibold">{{ $patient->triage_started_at?->format('M d, Y H:i') ?? '—' }}</p>
                    </div>
                    <div class="col-md-3">
                        <p class="small mb-1" style="color:var(--text-muted);">Doctor Started</p>
                        <p class="fw-semibold">{{ $patient->doctor_started_at?->format('M d, Y H:i') ?? '—' }}</p>
                    </div>
                    <div class="col-md-3">
                        <p class="small mb-1" style="color:var(--text-muted);">Completed</p>
                        <p class="fw-semibold">{{ $patient->completed_at?->format('M d, Y H:i') ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap pt-4" style="border-top:1px solid var(--glass-border);">
                <a href="{{ route('receptionist.patients.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>

                @if($patient->status === 'completed')
                    <form action="{{ route('receptionist.patients.revisit', $patient) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Re-register this patient for a new visit?')">
                            <i class="bi bi-arrow-repeat me-1"></i>New Visit
                        </button>
                    </form>
                @endif

                {{-- Reassign Doctor --}}
                <form action="{{ route('receptionist.patients.reassign', $patient) }}" method="POST" class="d-flex gap-2 align-items-center">
                    @csrf
                    <select name="doctor_id" class="form-select form-select-sm" style="min-width:180px;" required>
                        <option value="">Reassign to...</option>
                        @foreach($doctors as $doc)
                            <option value="{{ $doc->id }}" {{ $patient->doctor_id == $doc->id ? 'selected' : '' }}>
                                Dr. {{ $doc->name }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('Reassign this patient to a different doctor?')">
                        <i class="bi bi-arrow-left-right me-1"></i>Reassign
                    </button>
                </form>
            </div>
    </div>
</div>
@endsection
