@extends('layouts.app')
@section('title', 'Performance Matrix — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-bar-chart-steps me-2" style="color:var(--accent-primary);"></i>Staff Performance Matrix</h2>
            <p class="page-subtitle mb-0">{{ $monthStart->format('d M Y') }} – {{ $monthEnd->format('d M Y') }} &mdash; All active staff ranked by Net Profit Score</p>
        </div>
        <a href="{{ route('owner.attendance.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>Attendance Logs</a>
    </div>

    <div class="card mb-3 fade-in">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('owner.performance-matrix') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $monthStart->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $monthEnd->format('Y-m-d') }}">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ route('owner.performance-matrix') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <a href="{{ route('owner.performance-matrix', ['from' => now()->subDays(29)->format('Y-m-d'), 'to' => now()->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-secondary">Last 30d</a>
                    <a href="{{ route('owner.performance-matrix', ['from' => now()->subMonths(3)->startOfMonth()->format('Y-m-d'), 'to' => now()->endOfMonth()->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-secondary">3 Months</a>
                    <a href="{{ route('owner.performance-matrix', ['from' => now()->subYear()->format('Y-m-d'), 'to' => now()->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-secondary">12 Months</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card fade-in delay-1">
        <div class="card-body p-0">
            @if($matrix->isEmpty())
                <div class="empty-state py-5"><i class="bi bi-people"></i><h5>No active staff</h5></div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Staff Member</th>
                            <th>Role</th>
                            <th>Type</th>
                            <th class="text-end">Revenue (PKR)</th>
                            <th class="text-end">Compensation (PKR)</th>
                            <th class="text-center">NPS</th>
                            <th class="text-center">Tier</th>
                            <th class="text-center">Shifts</th>
                            <th class="text-center">Hours</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matrix as $rank => $row)
                        @php
                            $nps      = $row['nps'];
                            $npsColor = $nps >= 70 ? 'var(--accent-success)' : ($nps >= 40 ? 'var(--accent-warning)' : 'var(--accent-danger)');
                            $typeLabel = match($row['employee_type']) {
                                'gp'        => 'GP',
                                'specialist' => 'Specialist',
                                default     => 'Staff',
                            };
                        @endphp
                        <tr>
                            <td style="color:var(--text-muted);">{{ $rank + 1 }}</td>
                            <td class="fw-medium">{{ $row['name'] }}</td>
                            <td><small style="color:var(--text-muted);">{{ $row['role'] }}</small></td>
                            <td><span class="badge badge-glass-secondary">{{ $typeLabel }}</span></td>
                            <td class="text-end fw-medium">{{ number_format($row['revenue'], 0) }}</td>
                            <td class="text-end" style="color:var(--text-muted);">{{ number_format($row['compensation'], 0) }}</td>
                            <td class="text-center">
                                @if($row['nps_applicable'])
                                    <span class="fw-bold" style="color:{{ $npsColor }}; font-size:1.05rem;">{{ $nps }}%</span>
                                @else
                                    <span class="badge badge-glass-secondary" style="font-size:0.72rem;" title="NPS not applicable — support role without direct revenue attribution">N/A</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    $tier = $row['gp_tier'];
                                    $tierColor = match($tier) { 3 => 'var(--accent-success)', 2 => 'var(--accent-warning)', default => 'var(--text-muted)' };
                                    // Determine tier basis label for tooltip
                                    $tierBasis = in_array($row['role'], ['Triage', 'Receptionist'])
                                        ? 'Based on shifts (' . $row['shifts'] . ' shifts)'
                                        : 'Based on revenue (PKR ' . number_format($row['revenue'], 0) . ')';
                                @endphp
                                <span class="badge" style="background:rgba(var(--accent-primary-rgb),0.15);color:{{ $tierColor }};" title="{{ $tierBasis }}">
                                    T{{ $tier }}
                                </span>
                            </td>
                            <td class="text-center">{{ $row['shifts'] ?: '—' }}</td>
                            <td class="text-center">{{ $row['hours'] ? $row['hours'] . 'h' : '—' }}</td>
                            <td>
                                <a href="{{ route('owner.payouts.performance', $row['id']) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <div class="mt-3 fade-in delay-2">
        <small style="color:var(--text-muted);">
            <strong>NPS = Net Profit Score</strong>: ((Revenue − Compensation) / Revenue) × 100. Green ≥ 70% &bull; Yellow 40–69% &bull; Red &lt; 40%. Support roles (Triage, Receptionist) show N/A — they don't carry direct revenue.
            <br>
            <strong>Tier (T1–T3)</strong>: Doctors &amp; clinical staff — revenue bands (T1 &lt; 50k / T2 50–150k / T3 &gt; 150k PKR). Support staff — shifts attended (T1 &lt; 15 / T2 15–19 / T3 ≥ 20). GP doctors: patient-count thresholds.
        </small>
    </div>
</div>
@endsection
