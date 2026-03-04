@extends('layouts.app')
@section('title', 'Patients — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-people me-2" style="color:var(--accent-primary);"></i>Patients</h1>
            <p class="page-subtitle">All registered patients and their current status</p>
        </div>
        <a href="{{ route('receptionist.patients.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Register Patient</a>
    </div>

    <!-- Status Filter Tabs -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 fade-in delay-1">
        <div class="me-auto d-flex flex-wrap gap-1">
            <a href="{{ route('receptionist.patients.index') }}" class="btn btn-sm {{ !($currentStatus ?? null) ? 'btn-primary' : 'btn-outline-primary' }}">
                All <span class="badge" style="background:rgba(255,255,255,0.2);">{{ array_sum($statusCounts ?? []) }}</span>
            </a>
            <a href="{{ route('receptionist.patients.index', ['status' => 'registered']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'registered' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                Registered <span class="badge" style="background:rgba(255,255,255,0.2);">{{ $statusCounts['registered'] ?? 0 }}</span>
            </a>
            <a href="{{ route('receptionist.patients.index', ['status' => 'triage']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'triage' ? 'btn-info' : 'btn-outline-info' }}">
                In Triage <span class="badge" style="background:rgba(255,255,255,0.2);">{{ $statusCounts['triage'] ?? 0 }}</span>
            </a>
            <a href="{{ route('receptionist.patients.index', ['status' => 'with_doctor']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'with_doctor' ? 'btn-warning' : 'btn-outline-warning' }}">
                With Doctor <span class="badge" style="background:rgba(255,255,255,0.2);">{{ $statusCounts['with_doctor'] ?? 0 }}</span>
            </a>
            <a href="{{ route('receptionist.patients.index', ['status' => 'completed']) }}" class="btn btn-sm {{ ($currentStatus ?? '') === 'completed' ? 'btn-success' : 'btn-outline-success' }}">
                Completed <span class="badge" style="background:rgba(255,255,255,0.2);">{{ $statusCounts['completed'] ?? 0 }}</span>
            </a>
        </div>
        <div class="search-glass-wrapper" style="min-width:220px;">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="patientSearch" class="form-control form-control-sm search-glass" placeholder="Search patients..." aria-label="Search patients">
        </div>
    </div>

    @if($patients->isEmpty())
        <div class="glass-card fade-in delay-2">
            <div class="text-center py-5">
                <i class="bi bi-people" style="font-size:3rem; color:var(--text-muted);"></i>
                <p class="mt-3" style="color:var(--text-muted);">
                    @if($currentStatus ?? null)
                        No patients with status "{{ ucfirst(str_replace('_', ' ', $currentStatus)) }}".
                    @else
                        No patients registered yet.
                    @endif
                </p>
            </div>
        </div>
    @else
        <div class="glass-card fade-in delay-2">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th">#</th>
                            <th class="sortable-th">Name</th>
                            <th class="sortable-th">Phone</th>
                            <th class="sortable-th">Gender</th>
                            <th class="sortable-th">Assigned Doctor</th>
                            <th class="sortable-th">Status</th>
                            <th class="sortable-th">Registered At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($patients as $patient)
                            <tr>
                                <td style="color:var(--text-muted);">{{ $patient->id }}</td>
                                <td class="fw-medium">{{ $patient->first_name }} {{ $patient->last_name }}</td>
                                <td>{{ $patient->phone ?? 'N/A' }}</td>
                                <td>{{ $patient->gender }}</td>
                                <td>{{ $patient->doctor->name ?? 'N/A' }}</td>
                                <td>
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
                                </td>
                                <td style="color:var(--text-muted);">{{ $patient->registered_at?->format('d/m/Y H:i') ?? $patient->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var search = document.getElementById('patientSearch');
    if (search) {
        search.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('table tbody tr').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
});
</script>
@endpush
@endsection
