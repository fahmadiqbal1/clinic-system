@extends('layouts.app')
@section('title', 'Patient Details — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-person me-2" style="color:var(--accent-info);"></i>Patient Details</h2>
            <p class="page-subtitle mb-0">{{ $patient->first_name }} {{ $patient->last_name }}</p>
        </div>
        <a href="{{ route('doctor.patients.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Patients</a>
    </div>

    {{-- Patient Info Card --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-person-badge me-2" style="color:var(--accent-primary);"></i>Personal Information</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">First Name</span>
                    <span class="info-value">{{ $patient->first_name }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Last Name</span>
                    <span class="info-value">{{ $patient->last_name }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value">{{ $patient->phone ?? 'Not provided' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Gender</span>
                    <span class="info-value">{{ $patient->gender }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value">{{ $patient->date_of_birth?->format('d/m/Y') ?? 'Not provided' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Status</span>
                    @php
                        $statusStyle = match($patient->status) {
                            'registered' => 'background:rgba(var(--accent-secondary-rgb),0.15);color:var(--accent-secondary);',
                            'triage' => 'background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);',
                            'with_doctor' => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                            'completed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            default => '',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $statusStyle }}">{{ ucfirst(str_replace('_', ' ', $patient->status)) }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Record Created</span>
                    <span class="info-value" style="color:var(--text-secondary);">{{ $patient->created_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Latest Triage Vitals --}}
    @php $latestVitals = $patient->triageVitals()->latest()->first(); @endphp
    @if($latestVitals)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-heart-pulse me-2" style="color:var(--accent-danger);"></i>Latest Triage Vitals</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px,1fr));">
                @if($latestVitals->blood_pressure)
                <div class="info-grid-item">
                    <span class="info-label">Blood Pressure</span>
                    <span class="info-value">{{ $latestVitals->blood_pressure }} mmHg</span>
                </div>
                @endif
                @if($latestVitals->temperature)
                <div class="info-grid-item">
                    <span class="info-label">Temperature</span>
                    <span class="info-value">{{ $latestVitals->temperature }}°C</span>
                </div>
                @endif
                @if($latestVitals->pulse_rate)
                <div class="info-grid-item">
                    <span class="info-label">Heart Rate</span>
                    <span class="info-value">{{ $latestVitals->pulse_rate }} bpm</span>
                </div>
                @endif
                @if($latestVitals->respiratory_rate)
                <div class="info-grid-item">
                    <span class="info-label">Resp. Rate</span>
                    <span class="info-value">{{ $latestVitals->respiratory_rate }} br/min</span>
                </div>
                @endif
                @if($latestVitals->oxygen_saturation)
                <div class="info-grid-item">
                    <span class="info-label">SpO₂</span>
                    <span class="info-value">{{ $latestVitals->oxygen_saturation }}%</span>
                </div>
                @endif
                @if($latestVitals->weight)
                <div class="info-grid-item">
                    <span class="info-label">Weight</span>
                    <span class="info-value">{{ $latestVitals->weight }} kg</span>
                </div>
                @endif
                @if($latestVitals->height)
                <div class="info-grid-item">
                    <span class="info-label">Height</span>
                    <span class="info-value">{{ $latestVitals->height }} cm</span>
                </div>
                @endif
                @if($latestVitals->chief_complaint)
                <div class="info-grid-item" style="grid-column: span 2;">
                    <span class="info-label">Chief Complaint</span>
                    <span class="info-value">{{ $latestVitals->chief_complaint }}</span>
                </div>
                @endif
                @if($latestVitals->priority)
                <div class="info-grid-item">
                    <span class="info-label">Priority</span>
                    @php
                        $prioStyle = match($latestVitals->priority) {
                            'low' => 'background:rgba(var(--accent-secondary-rgb),0.15);color:var(--accent-secondary);',
                            'normal' => 'background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);',
                            'high' => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                            'urgent','critical','emergency' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                            default => '',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $prioStyle }}">{{ ucfirst($latestVitals->priority) }}</span>
                </div>
                @endif
            </div>
            @if($latestVitals->notes)
                <div class="mt-2 p-2 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                    <small style="color:var(--text-muted);">Triage Notes:</small>
                    <div style="color:var(--text-secondary);">{{ $latestVitals->notes }}</div>
                </div>
            @endif
            <small class="d-block mt-1" style="color:var(--text-muted);">Recorded {{ $latestVitals->created_at->diffForHumans() }}</small>
        </div>
    </div>
    @endif

    {{-- Action Buttons --}}
    <div class="d-flex flex-wrap gap-2 fade-in delay-3">
        @if($patient->status === 'with_doctor')
            <a href="{{ route('doctor.consultation.show', $patient) }}" class="btn btn-primary btn-lg">
                <i class="bi bi-journal-medical me-1"></i>Open Consultation
            </a>
            <a href="{{ route('doctor.prescriptions.create', $patient) }}" class="btn btn-warning">
                <i class="bi bi-prescription2 me-1"></i>Prescribe Medicine
            </a>
        @endif
        @if($patient->consultation_notes)
            <div class="w-100 mt-2">
                <div class="card">
                    <div class="card-header"><i class="bi bi-journal-text me-2" style="color:var(--accent-info);"></i>Consultation Notes</div>
                    <div class="card-body">
                        <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                            {!! nl2br(e($patient->consultation_notes)) !!}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
