@extends('layouts.app')
@section('title', 'Doctor Dashboard — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-stethoscope me-2" style="color:var(--accent-primary);"></i>Doctor Dashboard</h2>
            <p class="page-subtitle mb-0">Welcome back, {{ auth()->user()->name }}</p>
        </div>
    </div>

    {{-- Pending Payout Alert --}}
    @if($pendingPayouts > 0)
    <div class="alert-banner-success mb-4 fade-in delay-1">
        <div class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-wallet2 me-2"></i><strong>{{ $pendingPayouts }}</strong> payout{{ $pendingPayouts > 1 ? 's' : '' }} awaiting your confirmation</span>
            <a href="{{ route('reception.payouts.index') }}" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Review Payouts</a>
        </div>
    </div>
    @endif

    {{-- Patient & Work Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-1">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-warning mb-1" style="font-size:1.6rem;">{{ $activePatients }}</div>
                    <div class="stat-label"><i class="bi bi-hourglass-split me-1"></i>Waiting Patients</div>
                    <a href="{{ route('doctor.patients.index', ['status' => 'with_doctor']) }}" class="small" style="color:var(--accent-primary);">View queue &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-2">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-success mb-1" style="font-size:1.6rem;">{{ $completedToday }}</div>
                    <div class="stat-label"><i class="bi bi-check-circle me-1"></i>Completed Today</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-3">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-info mb-1" style="font-size:1.6rem;">{{ $patientCount }}</div>
                    <div class="stat-label"><i class="bi bi-people me-1"></i>Total Patients</div>
                    <a href="{{ route('doctor.patients.index') }}" class="small" style="color:var(--accent-primary);">View all &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-4">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-primary mb-1" style="font-size:1.6rem;">{{ $todayInvoices }}</div>
                    <div class="stat-label"><i class="bi bi-receipt me-1"></i>Invoices Today</div>
                    <a href="{{ route('doctor.invoices.index') }}" class="small" style="color:var(--accent-primary);">All ({{ $invoiceCount }}) &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Earnings Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 hover-lift fade-in delay-2 accent-left-success">
                <div class="card-body py-3">
                    <div class="stat-label mb-1"><i class="bi bi-cash-stack me-1"></i>Total Earnings</div>
                    <div class="stat-value glow-success" style="font-size:1.4rem;">{{ currency($totalEarnings) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 hover-lift fade-in delay-3 accent-left-warning">
                <div class="card-body py-3">
                    <div class="stat-label mb-1"><i class="bi bi-clock-history me-1"></i>Unpaid Earnings</div>
                    <div class="stat-value glow-warning" style="font-size:1.4rem;">{{ currency($unpaidEarnings) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 hover-lift fade-in delay-4 accent-left-info">
                <div class="card-body py-3">
                    <div class="stat-label mb-1"><i class="bi bi-wallet2 me-1"></i>Paid Earnings</div>
                    <div class="stat-value glow-info" style="font-size:1.4rem;">{{ currency($paidEarnings) }}</div>
                    @if($pendingPayouts > 0)
                        <small style="color:var(--text-muted);">{{ $pendingPayouts }} payout(s) pending</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Pending Tasks --}}
    @php
        $tasks = collect();
        if ($activePatients > 0) $tasks->push(['label' => 'Patients waiting for consultation', 'count' => $activePatients, 'icon' => 'bi-hourglass-split', 'color' => 'warning', 'url' => route('doctor.patients.index', ['status' => 'with_doctor'])]);
        if ($pendingPayouts > 0) $tasks->push(['label' => 'Payouts pending confirmation', 'count' => $pendingPayouts, 'icon' => 'bi-wallet2', 'color' => 'info', 'url' => route('reception.payouts.index')]);
        if ($resultsReadyCount > 0) $tasks->push(['label' => 'Lab / Radiology results ready', 'count' => $resultsReadyCount, 'icon' => 'bi-file-earmark-check', 'color' => 'success', 'url' => route('doctor.invoices.index')]);
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
        {{-- Recent Prescriptions --}}
        <div class="col-12 fade-in delay-3 mb-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-prescription2 me-2" style="color:var(--accent-warning);"></i>Recent Prescriptions</span>
                    <a href="{{ route('doctor.prescriptions.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    @if($recentPrescriptions->isEmpty())
                        <div class="empty-state py-4">
                            <i class="bi bi-prescription2"></i>
                            <h5>No prescriptions yet</h5>
                            <p class="mb-0">Prescriptions you create during consultations will appear here.</p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($recentPrescriptions as $rx)
                                <div class="list-group-item d-flex justify-content-between align-items-center" style="background:transparent; border-color:var(--glass-border);">
                                    <div>
                                        <div class="fw-medium" style="color:var(--text-primary);">{{ $rx->patient?->first_name ?? 'Unknown' }} {{ $rx->patient?->last_name ?? '' }}</div>
                                        <small style="color:var(--text-muted);">{{ $rx->diagnosis ?? 'No diagnosis' }} &middot; {{ $rx->items->count() }} item(s)</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge-glass @if($rx->status === 'dispensed') badge-glass-success @elseif($rx->status === 'cancelled') badge-glass-danger @else badge-glass-warning @endif">
                                            {{ ucfirst($rx->status) }}
                                        </span>
                                        <br><small style="color:var(--text-muted);">{{ $rx->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Waiting Patients Queue --}}
        <div class="col-lg-7">
            <div class="card mb-4 fade-in delay-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-people me-2" style="color:var(--accent-warning);"></i>Patient Queue</span>
                    <a href="{{ route('doctor.patients.index', ['status' => 'with_doctor']) }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    @if($waitingPatients->isEmpty())
                        <div class="empty-state py-4">
                            <i class="bi bi-check-circle"></i>
                            <h5>No patients waiting</h5>
                            <p class="mb-0">Your queue is clear</p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($waitingPatients as $patient)
                                <a href="{{ route('doctor.consultation.show', $patient) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="background:transparent; border-color:var(--glass-border);">
                                    <div>
                                        <div class="fw-medium">{{ $patient->first_name }} {{ $patient->last_name }}</div>
                                        <small style="color:var(--text-muted);">{{ $patient->gender }} &middot; {{ $patient->phone ?? 'No phone' }}</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);">Waiting</span>
                                        <br>
                                        <small class="wait-timer fw-semibold" data-since="{{ ($patient->doctor_started_at ?? $patient->created_at)->toIso8601String() }}"></small>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Today's Appointment Timeline --}}
        @if(isset($todayAppointments) && $todayAppointments->count() > 0)
        <div class="col-12 mb-2">
            <div class="card fade-in delay-3" style="border-left:3px solid var(--accent-primary);">
                <div class="card-header d-flex align-items-center justify-content-between py-2">
                    <span style="font-size:0.88rem;"><i class="bi bi-clock-history me-2" style="color:var(--accent-primary);"></i>Today's Schedule
                        <span class="badge ms-1" style="background:rgba(129,140,248,0.2);color:var(--accent-primary);font-size:0.7rem;">{{ $todayAppointments->count() }}</span>
                    </span>
                    <a href="{{ route('doctor.appointments.index') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.72rem; padding:2px 8px;">View all</a>
                </div>
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap" style="position:relative; padding-bottom:4px;">
                        {{-- Timeline line --}}
                        <div style="position:absolute; left:0; bottom:4px; width:100%; height:2px; background:linear-gradient(90deg,var(--accent-primary),rgba(129,140,248,0.1)); border-radius:1px; z-index:0;"></div>
                        @foreach($todayAppointments as $appt)
                            @php
                                $chipColor = match($appt->status) {
                                    'confirmed'   => 'var(--accent-success)',
                                    'in_progress' => 'var(--accent-warning)',
                                    default       => 'var(--accent-primary)',
                                };
                                $chipBg = match($appt->status) {
                                    'confirmed'   => 'rgba(52,211,153,0.15)',
                                    'in_progress' => 'rgba(251,191,36,0.15)',
                                    default       => 'rgba(129,140,248,0.15)',
                                };
                            @endphp
                            <div class="d-flex flex-column align-items-center" style="position:relative; z-index:1; flex:0 0 auto;">
                                <div class="rounded-pill px-2 py-1 hover-lift"
                                     title="{{ $appt->patient?->first_name ?? 'Unknown' }} {{ $appt->patient?->last_name ?? '' }} — {{ ucfirst($appt->status) }}"
                                     style="background:{{ $chipBg }}; border:1px solid {{ $chipColor }}; color:{{ $chipColor }}; font-size:0.72rem; white-space:nowrap; cursor:default; transition:transform 0.15s ease, box-shadow 0.15s ease;">
                                    <i class="bi bi-person-fill me-1"></i>{{ \Illuminate\Support\Str::limit($appt->patient?->first_name ?? 'Unknown', 8) }}
                                </div>
                                <small style="color:var(--text-muted); font-size:0.65rem; margin-top:2px;">{{ $appt->scheduled_at->format('H:i') }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Quick Actions & Recent Earnings --}}
        <div class="col-lg-5">
            <div class="card mb-4 fade-in delay-4">
                <div class="card-header"><i class="bi bi-lightning-charge me-2" style="color:var(--accent-warning);"></i>Quick Actions</div>
                <div class="card-body">
                    <div class="quick-actions" style="grid-template-columns: 1fr;">
                        <a href="{{ route('doctor.patients.index') }}" class="quick-action-btn">
                            <i class="bi bi-people" style="color:var(--accent-primary);"></i>All My Patients
                        </a>
                        <a href="{{ route('doctor.prescriptions.index') }}" class="quick-action-btn">
                            <i class="bi bi-prescription2" style="color:var(--accent-warning);"></i>Prescriptions
                        </a>
                        <a href="{{ route('doctor.invoices.index') }}" class="quick-action-btn">
                            <i class="bi bi-receipt" style="color:var(--accent-info);"></i>My Invoices
                        </a>
                        <a href="{{ route('contracts.show') }}" class="quick-action-btn">
                            <i class="bi bi-file-earmark-text" style="color:var(--accent-secondary);"></i>My Contract
                        </a>
                        <a href="{{ route('reception.payouts.index') }}" class="quick-action-btn">
                            <i class="bi bi-wallet2" style="color:var(--accent-warning);"></i>My Payouts
                        </a>
                    </div>
                </div>
            </div>

            @if($recentTransactions->count() > 0)
            <div class="card fade-in delay-5">
                <div class="card-header"><i class="bi bi-graph-up-arrow me-2" style="color:var(--accent-success);"></i>Recent Earnings</div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($recentTransactions->take(5) as $txn)
                            <div class="list-group-item d-flex justify-content-between" style="background:transparent; border-color:var(--glass-border);">
                                <div>
                                    <small style="color:var(--text-muted);">Invoice #{{ $txn->invoice_id }}</small>
                                    <br>
                                    <small style="color:var(--text-secondary);">{{ $txn->created_at->format('M d H:i') }}</small>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold" style="color:var(--accent-success);">{{ currency($txn->amount) }}</span>
                                    <br>
                                    <small style="color:var(--text-muted);">{{ number_format($txn->percentage, 1) }}%</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
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
        if (diff < 180) { el.style.color = 'var(--accent-success)'; }
        else if (diff < 600) { el.style.color = 'var(--accent-warning)'; }
        else if (diff < 1200) { el.style.color = 'var(--accent-secondary)'; }
        else { el.style.color = 'var(--accent-danger)'; }
    });
}
updateWaitTimers();
setInterval(updateWaitTimers, 1000);
</script>
@endpush
