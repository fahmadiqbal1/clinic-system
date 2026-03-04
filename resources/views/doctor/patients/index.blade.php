@extends('layouts.app')
@section('title', 'My Patients — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-people me-2" style="color:var(--accent-primary);"></i>My Patients</h2>
            <p class="page-subtitle mb-0">All patients assigned to you</p>
        </div>
        <a href="{{ route('doctor.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
    </div>

    {{-- Status Filter Tabs --}}
    <div class="mb-3 fade-in delay-1 d-flex flex-wrap gap-1">
        <a href="{{ route('doctor.patients.index') }}" class="btn btn-sm {{ !($currentStatus ?? null) ? 'btn-primary' : 'btn-outline-primary' }}">
            All <span class="badge-glass ms-1">{{ array_sum($statusCounts ?? []) }}</span>
        </a>
        <a href="{{ route('doctor.patients.index', ['status' => 'with_doctor']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'with_doctor' ? 'btn-warning' : 'btn-outline-warning' }}">
            With Me <span class="badge-glass ms-1">{{ $statusCounts['with_doctor'] ?? 0 }}</span>
        </a>
        <a href="{{ route('doctor.patients.index', ['status' => 'registered']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'registered' ? 'btn-secondary' : 'btn-outline-secondary' }}">
            Registered <span class="badge-glass ms-1">{{ $statusCounts['registered'] ?? 0 }}</span>
        </a>
        <a href="{{ route('doctor.patients.index', ['status' => 'triage']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'triage' ? 'btn-info' : 'btn-outline-info' }}">
            In Triage <span class="badge-glass ms-1">{{ $statusCounts['triage'] ?? 0 }}</span>
        </a>
        <a href="{{ route('doctor.patients.index', ['status' => 'completed']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'completed' ? 'btn-success' : 'btn-outline-success' }}">
            Completed <span class="badge-glass ms-1">{{ $statusCounts['completed'] ?? 0 }}</span>
        </a>
    </div>

    @if($patients->isEmpty())
        <div class="card fade-in delay-2">
            <div class="card-body">
                <div class="empty-state py-5">
                    <i class="bi bi-people"></i>
                    <h5>No patients found</h5>
                    <p class="mb-0">
                        @if($currentStatus ?? null)
                            No patients with status "{{ ucfirst(str_replace('_', ' ', $currentStatus)) }}".
                        @else
                            You have no patients yet.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="card fade-in delay-2">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($patients as $patient)
                                <tr>
                                    <td style="color:var(--text-muted);">{{ $patient->id }}</td>
                                    <td class="fw-medium">{{ $patient->first_name }} {{ $patient->last_name }}</td>
                                    <td style="color:var(--text-secondary);">{{ $patient->phone ?? 'N/A' }}</td>
                                    <td style="color:var(--text-secondary);">{{ $patient->gender }}</td>
                                    <td>
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
                                    </td>
                                    <td style="color:var(--text-muted);">{{ $patient->registered_at?->format('d/m/Y H:i') ?? $patient->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('doctor.patients.show', $patient->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
                                            @if($patient->status === 'with_doctor')
                                                <a href="{{ route('doctor.consultation.show', $patient) }}" class="btn btn-sm btn-primary"><i class="bi bi-journal-medical me-1"></i>Consult</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
