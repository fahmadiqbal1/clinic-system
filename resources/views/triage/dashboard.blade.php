@extends('layouts.app')
@section('title', 'Triage Dashboard — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-heart-pulse me-2" style="color:var(--accent-warning);"></i>Triage Dashboard</h2>
            <p class="page-subtitle mb-0">Assess patients and send them to doctors <span class="auto-poll-indicator ms-2" role="status" aria-label="Auto-refreshing"><span class="auto-poll-dot"></span>Live</span></p>
        </div>
        <a href="{{ route('triage.patients.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-people me-1"></i>All Patients</a>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-1">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-primary mb-1" style="font-size:1.6rem;">{{ $registeredCount }}</div>
                    <div class="stat-label"><i class="bi bi-hourglass-split me-1"></i>Waiting for Triage</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-2">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-warning mb-1" style="font-size:1.6rem;">{{ $triageCount }}</div>
                    <div class="stat-label"><i class="bi bi-clipboard2-pulse me-1"></i>In Triage Now</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-3">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-info mb-1" style="font-size:1.6rem;">{{ $readyForDoctorCount }}</div>
                    <div class="stat-label"><i class="bi bi-person-check me-1"></i>With Doctor</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-4">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-success mb-1" style="font-size:1.6rem;">{{ $completedTodayCount ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-check-circle me-1"></i>Completed Today</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pending Tasks --}}
    @php
        $tasks = collect();
        if ($registeredCount > 0) $tasks->push(['label' => 'Patients waiting for triage', 'count' => $registeredCount, 'icon' => 'bi-hourglass-split', 'color' => 'warning', 'url' => route('triage.patients.index')]);
        if ($triageCount > 0) $tasks->push(['label' => 'Currently in triage (finish assessment)', 'count' => $triageCount, 'icon' => 'bi-clipboard2-pulse', 'color' => 'primary', 'url' => route('triage.patients.index')]);
    @endphp
    @if($tasks->count() > 0)
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-list-check me-2" style="color:var(--accent-warning);"></i>Pending Tasks <span class="badge bg-warning text-dark ms-2">{{ $tasks->sum('count') }}</span></div>
        <div class="card-body py-2">
            @foreach($tasks as $task)
                <a href="{{ $task['url'] }}" class="d-flex align-items-center justify-content-between py-2 text-decoration-none" style="border-bottom:1px solid var(--glass-border);">
                    <span><i class="bi {{ $task['icon'] }} me-2" style="color:var(--accent-{{ $task['color'] }});"></i><span style="color:var(--text-primary);">{{ $task['label'] }}</span></span>
                    <span class="badge bg-{{ $task['color'] }}">{{ $task['count'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    <div class="row g-3">
        {{-- Main Content --}}
        <div class="col-lg-8">
            {{-- Waiting Queue --}}
            <div class="card mb-4 fade-in delay-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-people me-2" style="color:var(--accent-primary);"></i>Patients Waiting for Triage</span>
                    <a href="{{ route('triage.patients.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    @if(($waitingQueue ?? collect())->isEmpty())
                        <div class="empty-state py-4">
                            <i class="bi bi-check-circle"></i>
                            <h5>No patients waiting</h5>
                            <p class="mb-0">All patients have been assessed</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Patient Name</th>
                                        <th>Waiting Since</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($waitingQueue as $patient)
                                        <tr>
                                            <td style="color:var(--text-muted);">{{ $patient->id }}</td>
                                            <td class="fw-medium">{{ $patient->full_name }}</td>
                                            <td>
                                                <span class="wait-timer fw-semibold" data-since="{{ ($patient->registered_at ?? $patient->created_at)->toIso8601String() }}"></span>
                                            </td>
                                            <td>
                                                <a href="{{ route('triage.patients.vitals', $patient) }}" class="btn btn-sm btn-primary"><i class="bi bi-play-circle me-1"></i>Begin Triage</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Currently In Triage --}}
            @if(($inTriagePatients ?? collect())->count() > 0)
            <div class="card mb-4 fade-in delay-4">
                <div class="card-header">
                    <i class="bi bi-clipboard2-pulse me-2" style="color:var(--accent-warning);"></i>Currently In Triage
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Patient Name</th>
                                    <th>Priority</th>
                                    <th>Started</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($inTriagePatients as $patient)
                                    @php
                                        $triagePriority = $patient->triageVitals->first()?->priority ?? 'routine';
                                        $priorityBadge = match($triagePriority) {
                                            'emergency' => ['label' => 'Emergency', 'color' => 'var(--accent-danger)'],
                                            'urgent'    => ['label' => 'Urgent',    'color' => 'var(--accent-warning)'],
                                            default     => ['label' => 'Routine',   'color' => 'var(--accent-success)'],
                                        };
                                    @endphp
                                    <tr>
                                        <td style="color:var(--text-muted);">{{ $patient->id }}</td>
                                        <td class="fw-medium">{{ $patient->full_name }}</td>
                                        <td>
                                            <span class="badge-glass fw-semibold" style="background:rgba(0,0,0,0.1);color:{{ $priorityBadge['color'] }};">
                                                {{ $priorityBadge['label'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="wait-timer fw-semibold" data-since="{{ ($patient->triage_started_at ?? $patient->updated_at)->toIso8601String() }}"></span>
                                        </td>
                                        <td>
                                            <a href="{{ route('triage.patients.vitals', $patient) }}" class="btn btn-sm btn-warning"><i class="bi bi-arrow-repeat me-1"></i>Continue</a>
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

        {{-- Quick Actions Sidebar --}}
        <div class="col-lg-4">
            <div class="card mb-4 fade-in delay-4">
                <div class="card-header"><i class="bi bi-lightning-charge me-2" style="color:var(--accent-warning);"></i>Quick Actions</div>
                <div class="card-body">
                    <div class="quick-actions" style="grid-template-columns: 1fr;">
                        <a href="{{ route('triage.patients.index') }}" class="quick-action-btn">
                            <i class="bi bi-people" style="color:var(--accent-primary);"></i>All Patients
                        </a>
                        <a href="{{ route('triage.dashboard') }}" class="quick-action-btn">
                            <i class="bi bi-arrow-clockwise" style="color:var(--accent-info);"></i>Refresh Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function updateWaitTimers() {
    document.querySelectorAll('.wait-timer').forEach(function(el) {
        var since = new Date(el.dataset.since);
        var diff = Math.floor((Date.now() - since) / 1000);
        if (diff < 0) diff = 0;
        var h = Math.floor(diff / 3600);
        var m = Math.floor((diff % 3600) / 60);
        var s = diff % 60;
        el.textContent = (h > 0 ? h + 'h ' : '') + m + 'm ' + s + 's';
        // Color escalation: green < 3min, yellow < 10min, orange < 20min, red >= 20min
        if (diff < 180) { el.style.color = 'var(--accent-success)'; }
        else if (diff < 600) { el.style.color = 'var(--accent-warning)'; }
        else if (diff < 1200) { el.style.color = 'var(--accent-secondary)'; }
        else { el.style.color = 'var(--accent-danger)'; }
    });
}
updateWaitTimers();
setInterval(updateWaitTimers, 1000);
// Smart refresh: update stats without full page reload
(function() {
    var refreshInterval = 60000;
    setInterval(function() {
        if (document.hidden) return;
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newStats = doc.querySelectorAll('.glass-stat .stat-value');
                var oldStats = document.querySelectorAll('.glass-stat .stat-value');
                newStats.forEach(function(ns, i) {
                    if (oldStats[i] && oldStats[i].textContent !== ns.textContent) {
                        oldStats[i].textContent = ns.textContent;
                        oldStats[i].style.transition = 'color 0.3s';
                        oldStats[i].style.color = 'var(--accent-warning)';
                        setTimeout(function() { oldStats[i].style.color = ''; }, 1500);
                    }
                });
            })
            .catch(function() {});
    }, refreshInterval);
})();
</script>
@endpush
