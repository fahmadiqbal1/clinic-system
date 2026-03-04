@extends('layouts.app')
@section('title', 'My Health — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-heart-pulse me-2" style="color:var(--accent-primary);"></i>My Health Profile</h2>
            <p class="page-subtitle mb-0">{{ $patient->first_name }} {{ $patient->last_name }}</p>
        </div>
    </div>

    {{-- Patient Info --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-person-badge me-2" style="color:var(--accent-info);"></i>Personal Details</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value">{{ $patient->phone ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Gender</span>
                    <span class="info-value">{{ $patient->gender ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value">{{ $patient->date_of_birth?->format('d/m/Y') ?? 'N/A' }}</span>
                </div>
                @if($patient->doctor)
                <div class="info-grid-item">
                    <span class="info-label">Assigned Doctor</span>
                    <span class="info-value">{{ $patient->doctor->name }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Latest Vitals --}}
    @php $latestVitals = $patient->triageVitals->first(); @endphp
    @if($latestVitals)
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-activity me-2" style="color:var(--accent-danger);"></i>Latest Vitals</div>
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
            </div>
            <small class="d-block mt-2" style="color:var(--text-muted);">Recorded {{ $latestVitals->created_at->diffForHumans() }}</small>
        </div>
    </div>
    @endif

    {{-- Consultation Notes --}}
    @if($patient->consultation_notes)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-journal-medical me-2" style="color:var(--accent-warning);"></i>Doctor's Notes</div>
        <div class="card-body">
            <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                {!! nl2br(e($patient->consultation_notes)) !!}
            </div>
        </div>
    </div>
    @endif

    {{-- Prescriptions --}}
    @if($patient->prescriptions->count() > 0)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-prescription2 me-2" style="color:var(--accent-success);"></i>Prescriptions</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Medications</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($patient->prescriptions as $rx)
                        <tr>
                            <td style="color:var(--text-muted);">{{ $rx->created_at->format('M d, Y') }}</td>
                            <td>
                                @foreach($rx->items as $item)
                                    <span class="badge badge-glass-secondary me-1 mb-1" title="{{ $item->dosage }} — {{ $item->frequency }}">
                                        {{ $item->medication_name }}
                                    </span>
                                @endforeach
                            </td>
                            <td>
                                @php
                                    $rxStyle = match($rx->status) {
                                        'dispensed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                                        'cancelled' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                                        default => 'background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);',
                                    };
                                @endphp
                                <span class="badge-glass" style="{{ $rxStyle }}">{{ ucfirst($rx->status) }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Invoices / Tests / Imaging --}}
    @if($invoices->count() > 0)
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-file-earmark-medical me-2" style="color:var(--accent-info);"></i>Tests &amp; Imaging</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Department</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices->whereIn('department', ['lab', 'radiology']) as $inv)
                        <tr>
                            <td style="color:var(--text-muted);">{{ $inv->created_at->format('M d, Y') }}</td>
                            <td>{{ ucfirst($inv->department) }}</td>
                            <td>{{ $inv->service_name }}</td>
                            <td>
                                @php
                                    $wc = $inv->isPaid() && $inv->isWorkCompleted();
                                    $iStyle = $wc
                                        ? 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);'
                                        : 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);';
                                @endphp
                                <span class="badge-glass" style="{{ $iStyle }}">{{ $wc ? 'Completed' : 'In Progress' }}</span>
                            </td>
                            <td>
                                @if($inv->report_text || $inv->lab_results || ($inv->radiology_images && count($inv->radiology_images) > 0))
                                    <a href="{{ route('patient.invoice', $inv) }}" class="btn btn-outline-info btn-sm"><i class="bi bi-eye me-1"></i>View</a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- AI Analyses --}}
    @if($analyses->count() > 0)
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-robot me-2" style="color:var(--accent-secondary);"></i>AI Health Insights</div>
        <div class="card-body">
            @foreach($analyses as $analysis)
            <div class="mb-3 p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge badge-glass-secondary">{{ ucfirst($analysis->context_type) }}</span>
                    <small style="color:var(--text-muted);">{{ $analysis->created_at->format('M d, Y H:i') }}</small>
                </div>
                <div style="color:var(--text-secondary); white-space:pre-line; font-size:0.9rem;">
                    {!! nl2br(e($analysis->ai_response)) !!}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
