@extends('layouts.app')
@section('title', 'Prescription Queue — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-prescription2 me-2" style="color:var(--accent-warning);"></i>Prescription Queue</h2>
            <p class="page-subtitle mb-0">Manage prescriptions awaiting dispensing</p>
        </div>
    </div>

    {{-- Active Prescriptions --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-hourglass-split me-2" style="color:var(--accent-warning);"></i>Awaiting Dispensing</span>
            <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);">{{ $pending->total() }}</span>
        </div>
        @if($pending->count() > 0)
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th>Medications</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pending as $rx)
                                <tr>
                                    <td class="fw-medium">{{ $rx->patient->first_name ?? '' }} {{ $rx->patient->last_name ?? '' }}</td>
                                    <td style="color:var(--text-secondary);">{{ $rx->doctor->name ?? 'N/A' }}</td>
                                    <td style="color:var(--text-secondary);">{{ Str::limit($rx->diagnosis, 40) }}</td>
                                    <td>
                                        @foreach($rx->items as $item)
                                            <span class="badge-glass me-1">{{ $item->medication_name }}</span>
                                        @endforeach
                                    </td>
                                    <td style="color:var(--text-muted);">{{ $rx->created_at->format('M d, H:i') }}</td>
                                    <td>
                                        <a href="{{ route('pharmacy.prescriptions.show', $rx) }}" class="btn btn-primary btn-sm"><i class="bi bi-eye me-1"></i>View & Dispense</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-center">{{ $pending->links() }}</div>
        @else
            <div class="card-body">
                <div class="empty-state py-4">
                    <i class="bi bi-check-circle"></i>
                    <h5>All caught up</h5>
                    <p class="mb-0">No prescriptions awaiting dispensing.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Recently Dispensed --}}
    <div class="card fade-in delay-2">
        <div class="card-header">
            <i class="bi bi-check2-all me-2" style="color:var(--accent-success);"></i>Recently Dispensed
        </div>
        @if($dispensed->count() > 0)
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th>Medications</th>
                                <th>Dispensed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dispensed as $rx)
                                <tr>
                                    <td class="fw-medium">{{ $rx->patient->first_name ?? '' }} {{ $rx->patient->last_name ?? '' }}</td>
                                    <td style="color:var(--text-secondary);">{{ $rx->doctor->name ?? 'N/A' }}</td>
                                    <td style="color:var(--text-secondary);">{{ Str::limit($rx->diagnosis, 40) }}</td>
                                    <td>
                                        @foreach($rx->items as $item)
                                            <span class="badge-glass me-1" style="background:rgba(var(--accent-success-rgb),0.12);color:var(--accent-success);">{{ $item->medication_name }}</span>
                                        @endforeach
                                    </td>
                                    <td style="color:var(--text-muted);">{{ $rx->updated_at->format('M d, H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="card-body">
                <div class="empty-state py-4">
                    <i class="bi bi-clock-history"></i>
                    <h5>No recent dispensing</h5>
                    <p class="mb-0">No recently dispensed prescriptions.</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
