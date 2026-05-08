@extends('layouts.app')
@section('title', 'Performance Matrix — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-bar-chart-steps me-2" style="color:var(--accent-primary);"></i>Staff Performance Matrix</h2>
            <p class="page-subtitle mb-0">{{ $monthStart->format('F Y') }} — All active staff ranked by Net Profit Score</p>
        </div>
        <a href="{{ route('owner.attendance.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>Attendance Logs</a>
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
                                <span class="fw-bold" style="color:{{ $npsColor }}; font-size:1.05rem;">
                                    {{ $nps > 0 ? $nps . '%' : '—' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($row['gp_tier'])
                                    <span class="badge" style="background:rgba(var(--accent-primary-rgb),0.15);color:var(--accent-primary);">
                                        T{{ $row['gp_tier'] }}
                                    </span>
                                @else
                                    <span style="color:var(--text-muted);">—</span>
                                @endif
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
            <strong>NPS = Net Profit Score</strong>: ((Revenue Attributed − Compensation Cost) / Revenue Attributed) × 100.
            Green ≥ 70% &bull; Yellow 40–69% &bull; Red &lt; 40%.
            Revenue and compensation based on {{ $monthStart->format('1 F Y') }} – {{ now()->format('j F Y') }}.
        </small>
    </div>
</div>
@endsection
