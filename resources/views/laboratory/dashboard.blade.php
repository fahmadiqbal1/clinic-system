@extends('layouts.app')
@section('title', 'Laboratory Dashboard — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="mb-1"><i class="bi bi-droplet-half me-2" style="color:var(--accent-info);"></i>Laboratory Dashboard</h2>
            <p class="page-subtitle mb-0">Manage lab test orders, results & inventory <span class="auto-poll-indicator ms-2" role="status" aria-label="Auto-refreshing"><span class="auto-poll-dot"></span>Live</span></p>
        </div>
        <a href="{{ route('laboratory.invoices.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-list-check me-1"></i>All Tests</a>
    </div>

    {{-- Shift Clock --}}
    <x-shift-clock />

    {{-- KPI Strip --}}
    <div class="row g-2 mb-3 fade-in delay-1">
        <div class="col-6 col-md-2">
            <div class="card hover-lift" style="border-left:3px solid var(--accent-success);">
                <div class="card-body glass-stat text-center py-2">
                    <div class="stat-value mb-0" style="font-size:1.4rem;">{{ $kpi['completed_today'] ?? 0 }}</div>
                    <div class="stat-label" style="font-size:0.7rem;">Done Today</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card hover-lift" style="border-left:3px solid var(--accent-warning);">
                <div class="card-body glass-stat text-center py-2">
                    <div class="stat-value mb-0" style="font-size:1.4rem;">{{ $kpi['pending_queue'] ?? 0 }}</div>
                    <div class="stat-label" style="font-size:0.7rem;">Pending Queue</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card hover-lift" style="border-left:3px solid var(--accent-info);">
                <div class="card-body glass-stat text-center py-2">
                    <div class="stat-value mb-0" style="font-size:1.1rem;">{{ number_format($kpi['revenue_month'] ?? 0, 0) }}</div>
                    <div class="stat-label" style="font-size:0.7rem;">Revenue PKR</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card hover-lift" style="border-left:3px solid var(--accent-primary);">
                <div class="card-body glass-stat text-center py-2">
                    <div class="stat-value mb-0" style="font-size:1.4rem;">{{ $kpi['processed_month'] ?? 0 }}</div>
                    <div class="stat-label" style="font-size:0.7rem;">Tests This Month</div>
                </div>
            </div>
        </div>
        @if(!empty($kpi['nps']))
        <div class="col-6 col-md-2">
            @php $npsColor = $kpi['nps'] >= 70 ? 'var(--accent-success)' : ($kpi['nps'] >= 40 ? 'var(--accent-warning)' : 'var(--accent-danger)'); @endphp
            <div class="card hover-lift" style="border-left:3px solid {{ $npsColor }};">
                <div class="card-body glass-stat text-center py-2">
                    <div class="stat-value mb-0" style="font-size:1.3rem;color:{{ $npsColor }};">{{ $kpi['nps'] }}%</div>
                    <div class="stat-label" style="font-size:0.7rem;">NPS</div>
                </div>
            </div>
        </div>
        @endif
        <div class="col-6 col-md-2">
            <div class="card hover-lift" style="border-left:3px solid var(--accent-secondary);">
                <div class="card-body glass-stat text-center py-2">
                    <div class="stat-value mb-0" style="font-size:1.2rem;">{{ $kpi['shifts_month'] ?? 0 }}</div>
                    <div class="stat-label" style="font-size:0.7rem;">{{ $kpi['hours_month'] ?? 0 }}h Shifts</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-4 col-lg-2">
            <div class="card border-warning hover-lift fade-in delay-1">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-warning mb-1" style="font-size:1.6rem;">{{ $pendingInvoices ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-hourglass-split me-1"></i>Pending</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-lg-2">
            <div class="card border-primary hover-lift fade-in delay-2">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-primary mb-1" style="font-size:1.6rem;">{{ $inProgressCount ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-arrow-repeat me-1"></i>In Progress</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-lg-2">
            <div class="card border-success hover-lift fade-in delay-3">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-success mb-1" style="font-size:1.6rem;">{{ $completedToday ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-check-circle me-1"></i>Done Today</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-lg-2">
            <div class="card border-info hover-lift fade-in delay-4">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-info mb-1" style="font-size:1.6rem;">{{ $paidToday ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-cash me-1"></i>Paid Today</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-lg-2">
            <div class="card {{ ($lowStockItems ?? 0) > 0 ? 'border-danger' : '' }} hover-lift fade-in delay-5">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value {{ ($lowStockItems ?? 0) > 0 ? 'glow-danger' : '' }} mb-1" style="font-size:1.6rem;">{{ $lowStockItems ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-exclamation-triangle me-1"></i>Low Stock</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-lg-2">
            <div class="card hover-lift fade-in delay-6">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value mb-1" style="font-size:1.6rem;">{{ $pendingProcurements ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-cart me-1"></i>Orders</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pending Tasks --}}
    @php
        $tasks = collect();
        if (($pendingInvoices ?? 0) > 0) $tasks->push(['icon' => 'bi-hourglass-split', 'color' => 'warning', 'count' => $pendingInvoices, 'label' => 'Pending tests to start', 'url' => route('laboratory.invoices.index')]);
        if (($paidAwaitingWork ?? 0) > 0) $tasks->push(['icon' => 'bi-cash-coin', 'color' => 'info', 'count' => $paidAwaitingWork, 'label' => 'Paid — awaiting work', 'url' => route('laboratory.invoices.index')]);
        if (($inProgressCount ?? 0) > 0) $tasks->push(['icon' => 'bi-arrow-repeat', 'color' => 'primary', 'count' => $inProgressCount, 'label' => 'Tests in progress', 'url' => route('laboratory.invoices.index')]);
        if (($lowStockItems ?? 0) > 0) $tasks->push(['icon' => 'bi-exclamation-triangle', 'color' => 'danger', 'count' => $lowStockItems, 'label' => 'Low stock items', 'url' => route('dashboard.low-stock-alerts')]);
        if (($pendingProcurements ?? 0) > 0) $tasks->push(['icon' => 'bi-cart', 'color' => 'secondary', 'count' => $pendingProcurements, 'label' => 'Pending procurement orders', 'url' => route('procurement.index')]);
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

    <div class="row g-3">
        {{-- Work Queue --}}
        <div class="col-lg-8">
            <div class="card mb-4 fade-in delay-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clipboard2-pulse me-2" style="color:var(--accent-warning);"></i>Test Work Queue</span>
                    <a href="{{ route('laboratory.invoices.index') }}" class="btn btn-sm btn-outline-primary">View All Tests</a>
                </div>
                <div class="card-body p-0">
                    @if(($workQueue ?? collect())->isEmpty())
                        <div class="empty-state py-4">
                            <i class="bi bi-check-circle"></i>
                            <h5>Queue is clear</h5>
                            <p class="mb-0">No pending or in-progress tests</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Patient</th>
                                        <th>Test</th>
                                        <th>Doctor</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($workQueue as $invoice)
                                        <tr>
                                            <td style="color:var(--text-muted);">{{ $invoice->id }}</td>
                                            <td class="fw-medium">{{ $invoice->patient?->full_name ?? '—' }}</td>
                                            <td>{{ $invoice->service_name }}</td>
                                            <td>{{ $invoice->prescribingDoctor?->name ?? '—' }}</td>
                                            <td>
                                                @php
                                                    $badgeClass = match(true) {
                                                        $invoice->status === 'paid' && !$invoice->performed_by_user_id => 'bg-info',
                                                        $invoice->status === 'paid' => 'bg-primary',
                                                        $invoice->status === 'in_progress' => 'bg-primary',
                                                        default => 'bg-warning',
                                                    };
                                                    $badgeLabel = match(true) {
                                                        $invoice->status === 'paid' && !$invoice->performed_by_user_id => 'Paid — Start Work',
                                                        $invoice->status === 'paid' => 'Paid — In Progress',
                                                        default => ucfirst(str_replace('_', ' ', $invoice->status)),
                                                    };
                                                @endphp
                                                <span class="badge {{ $badgeClass }}">
                                                    {{ $badgeLabel }}
                                                </span>
                                            </td>
                                            <td>{{ currency($invoice->total_amount) }}</td>
                                            <td>
                                                <a href="{{ route('laboratory.invoices.show', $invoice) }}" class="btn btn-sm btn-info"><i class="bi bi-play-circle me-1"></i>Work</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recently Completed --}}
            @if(($recentCompleted ?? collect())->count() > 0)
            <div class="card mb-4 fade-in delay-4">
                <div class="card-header">
                    <i class="bi bi-clock-history me-2" style="color:var(--accent-success);"></i>Completed — Awaiting Payment
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Patient</th>
                                    <th>Test</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentCompleted as $invoice)
                                    <tr>
                                        <td style="color:var(--text-muted);">{{ $invoice->id }}</td>
                                        <td>{{ $invoice->patient?->full_name ?? '—' }}</td>
                                        <td>{{ $invoice->service_name }}</td>
                                        <td>{{ currency($invoice->total_amount) }}</td>
                                        <td>
                                            <a href="{{ route('laboratory.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-info">View</a>
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
                        <a href="{{ route('laboratory.invoices.index') }}" class="quick-action-btn">
                            <i class="bi bi-clipboard2-pulse" style="color:var(--accent-primary);"></i>View All Tests
                        </a>
                        <a href="{{ route('inventory.index') }}" class="quick-action-btn">
                            <i class="bi bi-box-seam" style="color:var(--accent-info);"></i>Test Catalog
                        </a>
                        <a href="{{ route('stock-movements.index') }}" class="quick-action-btn">
                            <i class="bi bi-arrow-left-right" style="color:var(--accent-secondary);"></i>Stock History
                        </a>
                        <a href="{{ route('procurement.index') }}" class="quick-action-btn">
                            <i class="bi bi-cart3" style="color:var(--accent-warning);"></i>Procurement
                        </a>
                        <a href="{{ route('procurement.create') }}" class="quick-action-btn">
                            <i class="bi bi-plus-circle" style="color:var(--accent-success);"></i>New Procurement
                        </a>
                        <a href="{{ route('dashboard.low-stock-alerts') }}" class="quick-action-btn">
                            <i class="bi bi-exclamation-triangle" style="color:var(--accent-danger);"></i>Low Stock
                            @if(($lowStockItems ?? 0) > 0)
                                <span class="badge bg-danger ms-1">{{ $lowStockItems }}</span>
                            @endif
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
