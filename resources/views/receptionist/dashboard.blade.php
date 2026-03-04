@extends('layouts.app')
@section('title', 'Receptionist Dashboard — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-clipboard-check me-2" style="color:var(--accent-primary);"></i>Receptionist Dashboard</h2>
            <p class="page-subtitle mb-0">Patient flow, invoices, and payment collection <span class="auto-poll-indicator ms-2" role="status" aria-label="Auto-refreshing"><span class="auto-poll-dot"></span>Live</span></p>
        </div>
        <a href="{{ route('receptionist.patients.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Register Patient</a>
    </div>

    {{-- Patient Flow Status Pipeline --}}
    <div class="glass-card p-3 mb-4 fade-in delay-1">
        <div class="status-pipeline flex-wrap">
            <div class="pipeline-step {{ $registeredCount > 0 ? 'active' : '' }}">
                <i class="bi bi-person-plus"></i>
                <span>Registered</span>
                <span class="badge bg-primary ms-1">{{ $registeredCount }}</span>
            </div>
            <span class="pipeline-arrow"><i class="bi bi-chevron-right"></i></span>
            <div class="pipeline-step {{ $triageCount > 0 ? 'active' : '' }}">
                <i class="bi bi-heart-pulse"></i>
                <span>Triage</span>
                <span class="badge bg-info ms-1">{{ $triageCount }}</span>
            </div>
            <span class="pipeline-arrow"><i class="bi bi-chevron-right"></i></span>
            <div class="pipeline-step {{ $withDoctorCount > 0 ? 'active' : '' }}">
                <i class="bi bi-person-badge"></i>
                <span>Doctor</span>
                <span class="badge bg-warning ms-1">{{ $withDoctorCount }}</span>
            </div>
            <span class="pipeline-arrow"><i class="bi bi-chevron-right"></i></span>
            <div class="pipeline-step {{ $unpaidInvoicesCount > 0 ? 'active' : '' }}">
                <i class="bi bi-cash-coin"></i>
                <span>Ready to Pay</span>
                <span class="badge bg-danger ms-1">{{ $unpaidInvoicesCount }}</span>
            </div>
            <span class="pipeline-arrow"><i class="bi bi-chevron-right"></i></span>
            <div class="pipeline-step completed">
                <i class="bi bi-check-circle"></i>
                <span>Paid Today</span>
                <span class="badge bg-success ms-1">{{ $paidTodayCount ?? 0 }}</span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Main Content --}}
        <div class="col-lg-8">
            {{-- Pending Tasks --}}
            @php
                $tasks = collect();
                if (($unpaidInvoicesCount ?? 0) > 0) $tasks->push(['icon' => 'bi-cash-coin', 'color' => 'success', 'count' => $unpaidInvoicesCount, 'label' => 'Invoices ready for payment', 'url' => route('receptionist.invoices.index', ['status' => 'completed'])]);
                if (($pendingUpfrontCount ?? 0) > 0) $tasks->push(['icon' => 'bi-clock-history', 'color' => 'info', 'count' => $pendingUpfrontCount, 'label' => 'Lab/Radiology awaiting upfront payment', 'url' => route('receptionist.invoices.index', ['status' => 'pending', 'department' => 'lab'])]);
                if (($pendingDiscountCount ?? 0) > 0) $tasks->push(['icon' => 'bi-tag', 'color' => 'warning', 'count' => $pendingDiscountCount, 'label' => 'Discount requests pending approval', 'url' => route('receptionist.invoices.index')]);
                if (($registeredCount ?? 0) > 0) $tasks->push(['icon' => 'bi-person-plus', 'color' => 'primary', 'count' => $registeredCount, 'label' => 'Patients waiting for triage', 'url' => route('receptionist.patients.index')]);
            @endphp
            @if($tasks->count() > 0)
            <div class="card mb-4 fade-in delay-2">
                <div class="card-header"><i class="bi bi-list-check me-2" style="color:var(--accent-warning);"></i>Pending Tasks <span class="badge bg-warning ms-2">{{ $tasks->sum('count') }}</span></div>
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

            {{-- Pending Upfront Payment (Lab/Radiology) --}}
            @if(($pendingUpfrontInvoices ?? collect())->count() > 0)
            <div class="card mb-4 fade-in delay-2">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-2" style="color:var(--accent-info);"></i>Awaiting Upfront Payment (Lab/Radiology)</span>
                    <span class="badge bg-info">{{ $pendingUpfrontCount ?? 0 }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Patient</th>
                                    <th>Department</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingUpfrontInvoices as $invoice)
                                    <tr>
                                        <td style="color:var(--text-muted);">{{ $invoice->id }}</td>
                                        <td class="fw-medium">{{ $invoice->patient?->full_name ?? '—' }}</td>
                                        <td><span class="badge bg-info">{{ $invoice->department === 'lab' ? 'Laboratory' : 'Radiology' }}</span></td>
                                        <td style="color:var(--text-secondary);">{{ $invoice->service_name }}</td>
                                        <td class="fw-bold" style="color:var(--accent-success);">{{ currency($invoice->total_amount) }}</td>
                                        <td>
                                            <a href="{{ route('receptionist.invoices.show', $invoice) }}" class="btn btn-sm btn-info"><i class="bi bi-cash me-1"></i>Collect</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Unpaid Invoices --}}
            <div class="card mb-4 fade-in delay-2">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cash-stack me-2" style="color:var(--accent-warning);"></i>Invoices Ready for Payment</span>
                    <a href="{{ route('receptionist.invoices.index') }}" class="btn btn-sm btn-outline-primary">All Invoices</a>
                </div>
                <div class="card-body p-0">
                    @if(($unpaidInvoices ?? collect())->isEmpty())
                        <div class="empty-state py-4">
                            <i class="bi bi-check-circle"></i>
                            <h5>All paid up</h5>
                            <p class="mb-0">No invoices awaiting payment</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Patient</th>
                                        <th>Department</th>
                                        <th>Service</th>
                                        <th>Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($unpaidInvoices as $invoice)
                                        <tr>
                                            <td style="color:var(--text-muted);">{{ $invoice->id }}</td>
                                            <td class="fw-medium">{{ $invoice->patient?->full_name ?? '—' }}</td>
                                            <td><span class="badge-glass" style="background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);">{{ ucfirst($invoice->department) }}</span></td>
                                            <td style="color:var(--text-secondary);">{{ $invoice->service_name }}</td>
                                            <td class="fw-bold" style="color:var(--accent-success);">{{ currency($invoice->total_amount) }}</td>
                                            <td>
                                                <a href="{{ route('receptionist.invoices.show', $invoice) }}" class="btn btn-sm btn-success"><i class="bi bi-cash me-1"></i>Collect</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recent Patients --}}
            <div class="card mb-4 fade-in delay-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-people me-2" style="color:var(--accent-primary);"></i>Recent Patients</span>
                    <a href="{{ route('receptionist.patients.index') }}" class="btn btn-sm btn-outline-secondary">All Patients</a>
                </div>
                <div class="card-body p-0">
                    @if(($recentPatients ?? collect())->isEmpty())
                        <div class="empty-state py-4">
                            <i class="bi bi-people"></i>
                            <h5>No patients yet</h5>
                            <p class="mb-0">No patients registered yet</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentPatients as $patient)
                                        <tr>
                                            <td style="color:var(--text-muted);">{{ $patient->id }}</td>
                                            <td class="fw-medium">{{ $patient->full_name }}</td>
                                            <td>
                                                @php
                                                    $badge = match($patient->status) {
                                                        'registered' => 'badge-glass-primary',
                                                        'triage' => 'badge-glass-info',
                                                        'with_doctor' => 'badge-glass-warning',
                                                        'completed' => 'badge-glass-success',
                                                        default => 'badge-glass-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $patient->status)) }}</span>
                                            </td>
                                            <td style="color:var(--text-secondary);">{{ $patient->created_at->format('M d, H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Doctor Earnings --}}
            @if(!empty($doctorEarnings))
            <div class="card mb-4 fade-in delay-4">
                <div class="card-header">
                    <i class="bi bi-wallet2 me-2" style="color:var(--accent-success);"></i>Doctor Earnings Today
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th>Today</th>
                                    <th>Unpaid Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($doctorEarnings as $earning)
                                    <tr>
                                        <td class="fw-medium">{{ $earning['name'] }}</td>
                                        <td style="color:var(--accent-success);">{{ currency($earning['todayEarnings']) }}</td>
                                        <td>
                                            <span style="color:var(--accent-warning);">{{ currency($earning['unpaidEarnings']) }}</span>
                                            @if($earning['unpaidEarnings'] >= 5000)
                                                <span class="badge bg-danger ms-1" style="font-size:0.65rem;">High</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($earning['unpaidEarnings'] > 0)
                                                <a href="{{ route('reception.payouts.create', ['doctor_id' => $earning['id']]) }}" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-wallet2 me-1"></i>Pay
                                                </a>
                                            @else
                                                <span class="text-muted">&mdash;</span>
                                            @endif
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
            <div class="card mb-4 fade-in delay-3">
                <div class="card-header"><i class="bi bi-lightning-charge me-2" style="color:var(--accent-warning);"></i>Quick Actions</div>
                <div class="card-body">
                    <div class="quick-actions" style="grid-template-columns: 1fr;">
                        <a href="{{ route('receptionist.patients.create') }}" class="quick-action-btn">
                            <i class="bi bi-person-plus" style="color:var(--accent-primary);"></i>Register Patient
                        </a>
                        <a href="{{ route('receptionist.patients.index') }}" class="quick-action-btn">
                            <i class="bi bi-people" style="color:var(--accent-info);"></i>All Patients
                        </a>
                        <a href="{{ route('receptionist.invoices.index') }}" class="quick-action-btn">
                            <i class="bi bi-receipt" style="color:var(--accent-secondary);"></i>All Invoices
                        </a>
                        <a href="{{ route('receptionist.invoices.create') }}" class="quick-action-btn">
                            <i class="bi bi-plus-circle" style="color:var(--accent-success);"></i>Create Invoice
                        </a>
                        <a href="{{ route('reception.payouts.index') }}" class="quick-action-btn">
                            <i class="bi bi-wallet" style="color:var(--accent-warning);"></i>Doctor Payouts
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
// Smart refresh: reload dashboard data every 60s without full page reload flash
(function() {
    var refreshInterval = 60000; // 60 seconds
    var lastRefresh = Date.now();
    
    setInterval(function() {
        // Only refresh if tab is visible
        if (document.hidden) return;
        if (Date.now() - lastRefresh < refreshInterval - 1000) return;
        lastRefresh = Date.now();
        
        // Soft reload via fetch and DOM swap of key elements
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                
                // Update stats cards
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
            .catch(function() {}); // Silent fail
    }, refreshInterval);
})();
</script>
@endpush
