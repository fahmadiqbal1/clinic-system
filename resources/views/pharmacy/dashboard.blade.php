@extends('layouts.app')
@section('title', 'Pharmacy Dashboard — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="mb-1"><i class="bi bi-capsule me-2" style="color:var(--accent-success);"></i>Pharmacy Dashboard</h2>
            <p class="page-subtitle mb-0">Manage prescriptions, dispensing, payments & inventory <span class="auto-poll-indicator ms-2" role="status" aria-label="Auto-refreshing"><span class="auto-poll-dot"></span>Live</span></p>
        </div>
        <a href="{{ route('pharmacy.invoices.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-list-check me-1"></i>All Prescriptions</a>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-4 col-lg-2">
            <div class="card border-warning hover-lift fade-in delay-1">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-warning mb-1" style="font-size:1.6rem;">{{ $pendingInvoices ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-hourglass-split me-1"></i>Pending Rx</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-lg-2">
            <div class="card border-primary hover-lift fade-in delay-2">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-primary mb-1" style="font-size:1.6rem;">{{ $inProgressCount ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-arrow-repeat me-1"></i>Dispensing</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-lg-2">
            <div class="card border-info hover-lift fade-in delay-3">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-info mb-1" style="font-size:1.6rem;">{{ $awaitingPayment ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-cash-coin me-1"></i>Awaiting Pay</div>
                </div>
            </div>
        </div>
        <div class="col-4 col-lg-2">
            <div class="card border-success hover-lift fade-in delay-4">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-success mb-1" style="font-size:1.6rem;">{{ $paidToday ?? 0 }}</div>
                    <div class="stat-label"><i class="bi bi-check-circle me-1"></i>Paid Today</div>
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
        if (($pendingInvoices ?? 0) > 0) $tasks->push(['label' => 'Prescriptions waiting to be dispensed', 'count' => $pendingInvoices, 'icon' => 'bi-hourglass-split', 'color' => 'warning', 'url' => route('pharmacy.invoices.index')]);
        if (($inProgressCount ?? 0) > 0) $tasks->push(['label' => 'Currently dispensing', 'count' => $inProgressCount, 'icon' => 'bi-arrow-repeat', 'color' => 'primary', 'url' => route('pharmacy.invoices.index')]);
        if (($awaitingPayment ?? 0) > 0) $tasks->push(['label' => 'Completed — collect payment', 'count' => $awaitingPayment, 'icon' => 'bi-cash-coin', 'color' => 'success', 'url' => route('pharmacy.invoices.index')]);
        if (($lowStockItems ?? 0) > 0) $tasks->push(['label' => 'Low stock items', 'count' => $lowStockItems, 'icon' => 'bi-exclamation-triangle', 'color' => 'danger', 'url' => route('dashboard.low-stock-alerts')]);
        if (($pendingProcurements ?? 0) > 0) $tasks->push(['label' => 'Pending procurement orders', 'count' => $pendingProcurements, 'icon' => 'bi-cart3', 'color' => 'info', 'url' => route('procurement.index')]);
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
        {{-- Work Queue --}}
        <div class="col-lg-8">
            <div class="card mb-4 fade-in delay-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-prescription2 me-2" style="color:var(--accent-warning);"></i>Prescription Work Queue</span>
                    <a href="{{ route('pharmacy.invoices.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    @if(($workQueue ?? collect())->isEmpty())
                        <div class="empty-state py-4">
                            <i class="bi bi-check-circle"></i>
                            <h5>Queue is clear</h5>
                            <p class="mb-0">No pending or in-progress prescriptions</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Patient</th>
                                        <th>Service</th>
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
                                                <span class="badge {{ $invoice->status === 'pending' ? 'bg-warning' : 'bg-primary' }}">
                                                    {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
                                                </span>
                                            </td>
                                            <td>{{ currency($invoice->total_amount) }}</td>
                                            <td>
                                                <a href="{{ route('pharmacy.invoices.show', $invoice) }}" class="btn btn-sm btn-info"><i class="bi bi-play-circle me-1"></i>Work</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Ready for Payment --}}
            @if(($readyForPayment ?? collect())->count() > 0)
            <div class="card mb-4 fade-in delay-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cash-stack me-2" style="color:var(--accent-success);"></i>Completed — Ready for Payment</span>
                    <span class="badge bg-success">{{ $readyForPayment->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($readyForPayment as $invoice)
                                    <tr>
                                        <td style="color:var(--text-muted);">{{ $invoice->id }}</td>
                                        <td>{{ $invoice->patient?->full_name ?? '—' }}</td>
                                        <td>{{ $invoice->service_name }}</td>
                                        <td class="fw-bold" style="color:var(--accent-success);">{{ currency($invoice->total_amount) }}</td>
                                        <td>
                                            <a href="{{ route('pharmacy.invoices.show', $invoice) }}" class="btn btn-sm btn-success"><i class="bi bi-cash me-1"></i>Collect</a>
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
                        <a href="{{ route('pharmacy.invoices.index') }}" class="quick-action-btn">
                            <i class="bi bi-prescription2" style="color:var(--accent-primary);"></i>All Prescriptions
                        </a>
                        <a href="{{ route('inventory.index') }}" class="quick-action-btn">
                            <i class="bi bi-capsule" style="color:var(--accent-info);"></i>Medication Catalog
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
