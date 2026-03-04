@extends('layouts.app')
@section('title', 'My Prescriptions — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-prescription2 me-2" style="color:var(--accent-success);"></i>My Prescriptions</h2>
            <p class="page-subtitle mb-0">All prescriptions you have written</p>
        </div>
        <a href="{{ route('doctor.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    @if($prescriptions->isEmpty())
        <div class="glass-card fade-in delay-1">
            <div class="text-center py-5">
                <i class="bi bi-prescription2" style="font-size:3rem; color:var(--text-muted);"></i>
                <p class="mt-3" style="color:var(--text-muted);">No prescriptions written yet.</p>
            </div>
        </div>
    @else
        <div class="glass-card fade-in delay-1">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Diagnosis</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prescriptions as $rx)
                            <tr>
                                <td style="color:var(--text-muted);">{{ $rx->id }}</td>
                                <td class="fw-medium">{{ $rx->patient->first_name ?? '' }} {{ $rx->patient->last_name ?? '' }}</td>
                                <td>{{ Str::limit($rx->diagnosis, 50) ?? '—' }}</td>
                                <td>{{ $rx->items->count() }} item(s)</td>
                                <td>
                                    @php
                                        $rxStyle = match($rx->status) {
                                            'active' => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                                            'dispensed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                                            'cancelled' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                                            default => '',
                                        };
                                    @endphp
                                    <span class="badge-glass" style="{{ $rxStyle }}">{{ ucfirst($rx->status) }}</span>
                                </td>
                                <td style="color:var(--text-muted);">{{ $rx->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $prescriptions->links() }}</div>
    @endif
</div>
@endsection
