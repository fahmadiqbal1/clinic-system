@extends('layouts.app')
@section('title', 'Triage Patients — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-people me-2" style="color:var(--accent-primary);"></i>Triage Patients</h2>
            <p class="page-subtitle mb-0">Patients awaiting vitals assessment</p>
        </div>
        <a href="{{ route('triage.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
    </div>

    @if($patients->isEmpty())
        <div class="card fade-in delay-1">
            <div class="card-body">
                <div class="empty-state py-5">
                    <i class="bi bi-clipboard-check"></i>
                    <h5>No patients waiting</h5>
                    <p class="mb-0">All patients have been triaged</p>
                </div>
            </div>
        </div>
    @else
        <div class="card fade-in delay-1">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-2" style="color:var(--accent-info);"></i>Patient Queue</span>
                <span class="badge-glass" style="background:rgba(var(--accent-primary-rgb),0.15); color:var(--accent-primary);">{{ $patients->count() }} patient(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="sortable-th">Name</th>
                                <th class="sortable-th">Gender</th>
                                <th class="sortable-th">Assigned Doctor</th>
                                <th class="sortable-th">Status</th>
                                <th class="sortable-th">Registered At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($patients as $patient)
                                <tr>
                                    <td class="fw-medium">{{ $patient->first_name }} {{ $patient->last_name }}</td>
                                    <td style="color:var(--text-secondary);">{{ $patient->gender }}</td>
                                    <td style="color:var(--text-secondary);">{{ $patient->doctor->name }}</td>
                                    <td>
                                        @if($patient->status === 'registered')
                                            <span class="badge-glass" style="background:rgba(var(--accent-secondary-rgb),0.15); color:var(--accent-secondary);">{{ ucfirst($patient->status) }}</span>
                                        @else
                                            <span class="badge-glass" style="background:rgba(var(--accent-info-rgb),0.15); color:var(--accent-info);">{{ ucfirst($patient->status) }}</span>
                                        @endif
                                    </td>
                                    <td style="color:var(--text-muted);">{{ $patient->registered_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if($patient->status === 'registered')
                                            <a href="{{ route('triage.patients.vitals', $patient->id) }}" class="btn btn-sm btn-warning"><i class="bi bi-clipboard2-pulse me-1"></i>Record Vitals</a>
                                        @elseif($patient->status === 'triage')
                                            <form action="{{ route('triage.patients.send-to-doctor', $patient->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Send this patient to their assigned doctor?')"><i class="bi bi-person-check me-1"></i>Send to Doctor</button>
                                            </form>
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
</div>
@endsection
