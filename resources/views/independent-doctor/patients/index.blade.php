@extends('layouts.app')
@section('title', 'Referral Patients — ' . config('app.name'))

@section('content')
<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1">
                <i class="bi bi-people me-2" style="color:var(--accent-primary);"></i>My Referral Patients
            </h1>
            <p class="page-subtitle mb-0">All patients you have referred for services</p>
        </div>
        <a href="{{ route('independent-doctor.patients.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>New Referral
        </a>
    </div>

    {{-- Status Filter --}}
    <div class="glass-card p-3 mb-4 fade-in delay-1">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('independent-doctor.patients.index') }}"
               class="btn btn-sm {{ !$status ? 'btn-primary' : 'btn-outline-secondary' }}">
                All
            </a>
            <a href="{{ route('independent-doctor.patients.index', ['status' => 'registered']) }}"
               class="btn btn-sm {{ $status === 'registered' ? 'btn-warning' : 'btn-outline-secondary' }}">
                Registered
            </a>
            <a href="{{ route('independent-doctor.patients.index', ['status' => 'completed']) }}"
               class="btn btn-sm {{ $status === 'completed' ? 'btn-success' : 'btn-outline-secondary' }}">
                Completed
            </a>
        </div>
    </div>

    <div class="glass-card p-4 fade-in delay-2">
        @if($patients->isEmpty())
            <div class="text-center py-5" style="color:var(--text-muted);">
                <i class="bi bi-people" style="font-size:3rem; opacity:0.3;"></i>
                <p class="mt-3 mb-0">No referral patients found.</p>
                <a href="{{ route('independent-doctor.patients.create') }}" class="btn btn-primary mt-3">
                    <i class="bi bi-person-plus me-1"></i>Register Your First Referral
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Orders</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($patients as $patient)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $patient->full_name }}</div>
                                @if($patient->date_of_birth)
                                    <small style="color:var(--text-muted);">{{ $patient->date_of_birth->format('d M Y') }}</small>
                                @endif
                            </td>
                            <td>{{ $patient->phone ?? '—' }}</td>
                            <td>{{ $patient->gender }}</td>
                            <td>
                                <span class="badge" style="background:rgba(var(--accent-primary-rgb),0.15); color:var(--accent-primary);">
                                    {{ $patient->invoices->count() }} order(s)
                                </span>
                            </td>
                            <td><small style="color:var(--text-muted);">{{ $patient->created_at->format('d M Y') }}</small></td>
                            <td>
                                <a href="{{ route('independent-doctor.patients.show', $patient) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $patients->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
