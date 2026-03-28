@extends('layouts.app')
@section('title', 'My Schedule — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-calendar-check me-2" style="color:var(--accent-primary);"></i>My Schedule</h1>
            <p class="page-subtitle">Your upcoming and past appointments</p>
        </div>
    </div>

    {{-- Status Filter Tabs --}}
    <div class="glass-card p-0 mb-3 fade-in delay-1" style="overflow:hidden;">
        <ul class="nav nav-tabs mb-0" style="border-bottom:1px solid var(--glass-border); padding:0 1rem;">
            @php
                $tabs = [
                    'all'         => ['label' => 'All',        'icon' => 'bi-calendar3',           'color' => ''],
                    'scheduled'   => ['label' => 'Scheduled',  'icon' => 'bi-calendar-event',      'color' => 'var(--accent-primary)'],
                    'confirmed'   => ['label' => 'Confirmed',  'icon' => 'bi-calendar-check',      'color' => 'var(--accent-success)'],
                    'in_progress' => ['label' => 'In Progress','icon' => 'bi-arrow-clockwise',     'color' => 'var(--accent-warning)'],
                    'completed'   => ['label' => 'Completed',  'icon' => 'bi-check-circle',        'color' => 'var(--accent-success)'],
                    'cancelled'   => ['label' => 'Cancelled',  'icon' => 'bi-x-circle',            'color' => 'var(--accent-danger)'],
                    'no_show'     => ['label' => 'No Show',    'icon' => 'bi-person-slash',        'color' => 'var(--text-muted)'],
                ];
            @endphp
            @foreach($tabs as $key => $tab)
                @php
                    $count = $key === 'all' ? $totalCount : ($counts[$key] ?? 0);
                    $isActive = $status === $key;
                @endphp
                <li class="nav-item">
                    <a class="nav-link py-3 px-3 d-flex align-items-center gap-1 {{ $isActive ? 'active' : '' }}"
                       href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => 1]) }}"
                       style="{{ $isActive && $tab['color'] ? 'color:'.$tab['color'].';' : '' }} font-size:0.85rem;">
                        <i class="bi {{ $tab['icon'] }}"></i>
                        <span class="d-none d-md-inline">{{ $tab['label'] }}</span>
                        @if($count > 0)
                            <span class="badge rounded-pill ms-1"
                                  style="font-size:0.65rem; background:{{ $tab['color'] ?: 'var(--accent-primary)' }}; color:#fff;">{{ $count }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    @if($appointments->isEmpty())
        <div class="glass-card fade-in delay-2">
            <div class="text-center py-5">
                <i class="bi bi-calendar" style="font-size:3rem; color:var(--text-muted);"></i>
                <p class="mt-3" style="color:var(--text-muted);">No appointments found for this filter.</p>
            </div>
        </div>
    @else
        <div class="glass-card fade-in delay-2">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th">Patient Name</th>
                            <th class="sortable-th">Date/Time</th>
                            <th class="sortable-th">Type</th>
                            <th class="sortable-th">Status</th>
                            <th class="sortable-th">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($appointments as $appointment)
                            <tr>
                                <td class="fw-medium">{{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}</td>
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
                                <td style="color:var(--text-muted);">{{ \Illuminate\Support\Str::limit($appointment->reason ?? 'N/A', 50) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $appointments->links() }}</div>
    @endif
</div>
@endsection
