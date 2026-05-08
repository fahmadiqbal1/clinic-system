@extends('layouts.app')
@section('title', 'Attendance — ' . $staff->name . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-person-lines-fill me-2" style="color:var(--accent-info);"></i>{{ $staff->name }}</h2>
            <p class="page-subtitle mb-0">
                {{ $staff->roles()->first()?->name ?? 'Staff' }} &mdash;
                Last 30 days attendance
            </p>
        </div>
        <a href="{{ route('owner.attendance.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All Staff
        </a>
    </div>

    {{-- Summary cards --}}
    <div class="row g-3 mb-4 fade-in delay-1">
        <div class="col-6 col-md-3">
            <div class="card hover-lift" style="border-left:3px solid var(--accent-primary);">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value mb-0" style="font-size:1.6rem;">{{ $totalShifts }}</div>
                    <div class="stat-label">Total Shifts</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card hover-lift" style="border-left:3px solid var(--accent-info);">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value mb-0" style="font-size:1.6rem;">{{ number_format($totalHours, 1) }}h</div>
                    <div class="stat-label">Total Hours</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card hover-lift" style="border-left:3px solid var(--accent-success);">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value mb-0" style="font-size:1.6rem;">{{ $totalShifts > 0 ? number_format($totalHours / $totalShifts, 1) : '—' }}h</div>
                    <div class="stat-label">Avg / Shift</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card hover-lift" style="border-left:3px solid {{ $openShift ? 'var(--accent-warning)' : 'var(--accent-secondary)' }};">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value mb-0" style="font-size:1rem;">
                        @if($openShift)
                            <span style="color:var(--accent-warning);">On Shift</span><br>
                            <small>since {{ $openShift->clocked_in_at->format('H:i') }}</small>
                        @else
                            <span style="color:var(--text-muted);">Not Clocked In</span>
                        @endif
                    </div>
                    <div class="stat-label">Status</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Shift log --}}
    <div class="card fade-in delay-2">
        <div class="card-header"><i class="bi bi-list-ul me-2" style="color:var(--accent-info);"></i>Shift History (Last 30 Days)</div>
        <div class="card-body p-0">
            @if($shifts->isEmpty())
                <div class="empty-state py-4">
                    <i class="bi bi-clock"></i>
                    <h5>No shifts recorded</h5>
                    <p class="mb-0">No shifts found in the last 30 days</p>
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clocked In</th>
                            <th>Clocked Out</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shifts as $shift)
                        @php
                            $mins     = $shift->durationMinutes();
                            $duration = $mins !== null ? floor($mins/60) . 'h ' . ($mins%60) . 'm' : '—';
                            $isOpen   = $shift->isOpen();
                            $isLong   = $mins !== null && $mins > 720;
                        @endphp
                        <tr @class(['table-warning' => $isOpen || $isLong])>
                            <td>{{ $shift->clocked_in_at->format('D, d M Y') }}</td>
                            <td>{{ $shift->clocked_in_at->format('H:i') }}</td>
                            <td>{{ $shift->clocked_out_at?->format('H:i') ?? '—' }}</td>
                            <td>{{ $duration }}</td>
                            <td>
                                @if($isOpen)
                                    <span class="badge bg-warning text-dark"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Open</span>
                                @elseif($isLong)
                                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Long shift</span>
                                @else
                                    <span class="badge bg-success">Closed</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
