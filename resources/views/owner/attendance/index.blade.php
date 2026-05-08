@extends('layouts.app')
@section('title', 'Staff Attendance — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-clock-history me-2" style="color:var(--accent-info);"></i>Staff Attendance</h2>
            <p class="page-subtitle mb-0">Shift logs for all staff members</p>
        </div>
        <a href="{{ route('owner.performance-matrix') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-bar-chart me-1"></i>Performance Matrix</a>
    </div>

    {{-- Filters --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-body">
            <form method="GET" action="{{ route('owner.attendance.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Staff Member</label>
                    <select name="user_id" class="form-select">
                        <option value="">All Staff</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" @selected($userId == $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary per staff --}}
    @if($summary->count() > 0)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-people me-2" style="color:var(--accent-primary);"></i>Hours Summary</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Staff</th><th>Shifts</th><th>Total Hours</th></tr></thead>
                    <tbody>
                        @foreach($summary as $row)
                        <tr>
                            <td>{{ $row->user?->name ?? 'Unknown' }}</td>
                            <td>{{ $row->shift_count }}</td>
                            <td>{{ number_format($row->total_minutes / 60, 1) }}h</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Shift log --}}
    <div class="card fade-in delay-3">
        <div class="card-header"><i class="bi bi-list-ul me-2" style="color:var(--accent-info);"></i>Shift Log</div>
        <div class="card-body p-0">
            @if($shifts->isEmpty())
                <div class="empty-state py-4"><i class="bi bi-clock"></i><h5>No shifts found</h5><p class="mb-0">Adjust the date range or staff filter</p></div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Clocked In</th>
                            <th>Clocked Out</th>
                            <th>Duration</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shifts as $shift)
                        @php
                            $tz       = $shift->user?->timezone ?: 'Asia/Karachi';
                            $inAt     = $shift->clocked_in_at->copy()->setTimezone($tz);
                            $outAt    = $shift->clocked_out_at?->copy()->setTimezone($tz);
                            $mins     = $shift->durationMinutes();
                            $duration = $mins !== null ? floor($mins/60) . 'h ' . ($mins%60) . 'm' : '—';
                            $isOpen   = $shift->isOpen();
                            $isLong   = $mins !== null && $mins > 720;
                        @endphp
                        <tr @class(['table-warning' => $isOpen || $isLong])>
                            <td>{{ $inAt->format('d M Y') }}</td>
                            <td>
                                <a href="{{ route('owner.attendance.show', $shift->user_id) }}" class="text-decoration-none fw-medium">
                                    {{ $shift->user?->name ?? 'Unknown' }}
                                </a>
                                <div class="small" style="color:var(--text-muted); font-size:0.72rem;">{{ $tz }}</div>
                            </td>
                            <td><small style="color:var(--text-muted);">{{ $shift->user?->getRoleNames()->first() ?? '—' }}</small></td>
                            <td>{{ $inAt->format('H:i') }}</td>
                            <td>{{ $outAt?->format('H:i') ?? '—' }}</td>
                            <td>{{ $duration }}</td>
                            <td><small style="color:var(--text-muted); font-family:monospace;">{{ $shift->clocked_in_ip ?? '—' }}</small></td>
                            <td>
                                @if($isOpen)
                                    <span class="badge bg-warning text-dark"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>On Shift</span>
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
            <div class="p-3">{{ $shifts->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
