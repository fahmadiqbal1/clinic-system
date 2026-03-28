@extends('layouts.app')
@section('title', 'Appointments — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-calendar-check me-2" style="color:var(--accent-primary);"></i>Appointments</h1>
            <p class="page-subtitle">Manage patient appointment scheduling</p>
        </div>
        <a href="{{ route('receptionist.appointments.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>New Appointment</a>
    </div>

    <!-- Status Filter Tabs -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 fade-in delay-1">
        <div class="me-auto d-flex flex-wrap gap-1">
            <a href="{{ route('receptionist.appointments.index', ['filter' => 'upcoming']) }}" class="btn btn-sm {{ ($filter ?? 'upcoming') === 'upcoming' ? 'btn-primary' : 'btn-outline-primary' }}">Upcoming</a>
            <a href="{{ route('receptionist.appointments.index', ['filter' => 'today']) }}" class="btn btn-sm {{ ($filter ?? '') === 'today' ? 'btn-info' : 'btn-outline-info' }}">Today</a>
            <a href="{{ route('receptionist.appointments.index', ['filter' => 'all']) }}" class="btn btn-sm {{ ($filter ?? '') === 'all' ? 'btn-secondary' : 'btn-outline-secondary' }}">All</a>
            <a href="{{ route('receptionist.appointments.index', ['filter' => 'cancelled']) }}" class="btn btn-sm {{ ($filter ?? '') === 'cancelled' ? 'btn-danger' : 'btn-outline-danger' }}">Cancelled</a>
        </div>
    </div>

    @if($appointments->isEmpty())
        <div class="glass-card fade-in delay-2">
            <div class="text-center py-5">
                <i class="bi bi-calendar" style="font-size:3rem; color:var(--text-muted);"></i>
                <p class="mt-3" style="color:var(--text-muted);">No appointments found.</p>
            </div>
        </div>
    @else
        <div class="glass-card fade-in delay-2">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th">#</th>
                            <th class="sortable-th">Patient</th>
                            <th class="sortable-th">Doctor</th>
                            <th class="sortable-th">Date/Time</th>
                            <th class="sortable-th">Type</th>
                            <th class="sortable-th">Status</th>
                            <th class="sortable-th">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($appointments as $appointment)
                            <tr>
                                <td style="color:var(--text-muted);">{{ $appointment->id }}</td>
                                <td class="fw-medium">{{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}</td>
                                <td>Dr. {{ $appointment->doctor->name ?? 'N/A' }}</td>
                                <td>{{ $appointment->scheduled_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $appointment->type_label }}</td>
                                <td>
                                    @php
                                        $badge = match($appointment->status) {
                                            'scheduled' => 'badge-glass-primary',
                                            'confirmed' => 'badge-glass-success',
                                            'cancelled' => 'badge-glass-danger',
                                            'completed' => 'badge-glass-success',
                                            'in_progress' => 'badge-glass-warning',
                                            'no_show' => 'badge-glass-secondary',
                                            default => 'badge-glass-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ $appointment->status_label }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('receptionist.appointments.show', $appointment) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $appointments->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection
