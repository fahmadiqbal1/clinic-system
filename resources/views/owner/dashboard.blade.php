@extends('layouts.app')
@section('title', 'Owner Dashboard — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header mb-4 fade-in">
        <h1><i class="bi bi-speedometer2 me-2"></i>Owner Dashboard</h1>
        <p class="page-subtitle">Financial overview and system status</p>
    </div>

    {{-- 7-Day Revenue Sparkline --}}
    <div class="glass-card p-3 mb-4 fade-in" style="position:relative; overflow:hidden;">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <span class="fw-semibold" style="color:var(--text-primary); font-size:0.92rem;"><i class="bi bi-activity me-2" style="color:var(--accent-success);"></i>7-Day Revenue Pulse</span>
                <span class="ms-3 text-muted small">Last 7 days at a glance</span>
            </div>
            <a href="{{ route('owner.revenue-forecast') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem;">Full Forecast →</a>
        </div>
        <div style="height:70px; position:relative;">
            <canvas id="sparklineChart"></canvas>
        </div>
        {{-- 3-D shimmer layer --}}
        <div style="position:absolute;top:0;right:0;width:180px;height:100%;background:linear-gradient(90deg,transparent,rgba(129,140,248,0.05));pointer-events:none;border-radius:0 var(--card-radius) var(--card-radius) 0;"></div>
    </div>

    {{-- Financial Overview --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4 fade-in delay-1">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-success">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Today's Revenue</div>
                        <div class="stat-value glow-success">{{ number_format($today_revenue, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 fade-in delay-2">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-danger">
                        <i class="bi bi-receipt-cutoff"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Today's Expenses</div>
                        <div class="stat-value glow-danger">{{ number_format($today_expenses, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 fade-in delay-3">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-primary">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Net Profit</div>
                        <div class="stat-value glow-primary">{{ number_format($today_net, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Operational Status --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 fade-in delay-3">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Unpaid Invoices</div>
                        <div class="stat-value glow-warning">{{ $unpaid_invoices_count }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in delay-4">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-info">
                        <i class="bi bi-droplet"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Lab</div>
                        <div class="stat-value">{{ $pending_lab_count }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in delay-5">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-secondary">
                        <i class="bi bi-camera"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Radiology</div>
                        <div class="stat-value">{{ $pending_radiology_count }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in delay-6">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-warning">
                        <i class="bi bi-capsule"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Pharmacy</div>
                        <div class="stat-value">{{ $pending_pharmacy_count }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Additional Metrics --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4 fade-in delay-5">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-danger">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Low Stock Items</div>
                        <div class="stat-value glow-danger">{{ $low_stock_count }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 fade-in delay-6">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-warning">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Payouts ({{ $pending_payout_count }})</div>
                        <div class="stat-value glow-warning">{{ number_format($pending_payout_total, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 fade-in delay-7">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-info">
                        <i class="bi bi-percent"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Discounts</div>
                        <div class="stat-value">{{ $pending_discount_count }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pending Tasks --}}
    @php
        $tasks = collect();
        if ($pending_discount_count > 0) $tasks->push(['label' => 'Discount requests awaiting approval', 'count' => $pending_discount_count, 'icon' => 'bi-percent', 'color' => 'warning', 'url' => route('owner.discount-approvals.index')]);
        if ($pending_payout_count > 0) $tasks->push(['label' => 'Doctor payouts pending', 'count' => $pending_payout_count, 'icon' => 'bi-wallet2', 'color' => 'info', 'url' => route('reception.payouts.index')]);
        if ($unpaid_invoices_count > 0) $tasks->push(['label' => 'Unpaid invoices across system', 'count' => $unpaid_invoices_count, 'icon' => 'bi-receipt', 'color' => 'primary', 'url' => '#']);
        if ($low_stock_count > 0) $tasks->push(['label' => 'Low stock items need attention', 'count' => $low_stock_count, 'icon' => 'bi-exclamation-triangle', 'color' => 'danger', 'url' => route('dashboard.low-stock-alerts')]);
    @endphp
    @if($tasks->count() > 0)
    <div class="glass-card p-4 mb-4 fade-in delay-5">
        <h5 class="mb-3"><i class="bi bi-list-check me-2" style="color:var(--accent-warning);"></i>Pending Tasks <span class="badge bg-warning text-dark ms-2">{{ $tasks->sum('count') }}</span></h5>
        <div>
            @foreach($tasks as $task)
                <a href="{{ $task['url'] }}" class="d-flex align-items-center justify-content-between py-2 text-decoration-none" style="border-bottom:1px solid var(--glass-border);">
                    <span><i class="bi {{ $task['icon'] }} me-2" style="color:var(--accent-{{ $task['color'] }});"></i><span style="color:var(--text-primary);">{{ $task['label'] }}</span></span>
                    <span class="badge bg-{{ $task['color'] }}">{{ $task['count'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Pending Approvals --}}
    @if($pending_discounts->count() > 0)
    <div class="glass-card p-4 mb-4 fade-in delay-7">
        <h5 class="mb-3"><i class="bi bi-exclamation-circle me-2" style="color:var(--accent-warning);"></i>Pending Discount Approvals</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Patient</th>
                        <th>Amount</th>
                        <th>Discount</th>
                        <th>Requested By</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending_discounts as $disc)
                    <tr>
                        <td><strong>#{{ $disc->id }}</strong></td>
                        <td>{{ $disc->patient ? $disc->patient->first_name . ' ' . $disc->patient->last_name : 'N/A' }}</td>
                        <td>{{ number_format($disc->total_amount, 2) }}</td>
                        <td><span class="badge bg-warning text-dark">{{ number_format($disc->discount_amount, 2) }}</span></td>
                        <td>{{ $disc->discountRequester?->name ?? 'Unknown' }}</td>
                        <td class="text-end">
                            <a href="{{ route('owner.discount-approvals.index') }}" class="btn btn-sm btn-outline-warning"><i class="bi bi-eye me-1"></i>Review</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($pending_discount_count > 5)
            <div class="text-center mt-2">
                <a href="{{ route('owner.discount-approvals.index') }}" class="btn btn-sm btn-outline-info">View all {{ $pending_discount_count }} pending discounts &rarr;</a>
            </div>
        @endif
    </div>
    @endif

    {{-- Charts Row --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8 fade-in delay-6">
            <div class="glass-card p-3">
                <h6 class="mb-3"><i class="bi bi-graph-up me-2" style="color:var(--accent-primary);"></i>7-Day Revenue & Expenses</h6>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 fade-in delay-7">
            <div class="glass-card p-3">
                <h6 class="mb-3"><i class="bi bi-pie-chart me-2" style="color:var(--accent-secondary);"></i>Today's Orders by Department</h6>
                <div class="chart-container">
                    <canvas id="deptChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="glass-card p-4 fade-in delay-7">
        <h5 class="mb-3"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
        <div class="quick-actions">
            <a href="{{ route('owner.financial-report') }}" class="quick-action-btn">
                <i class="bi bi-file-earmark-bar-graph me-1"></i>Financial Report
            </a>
            <a href="{{ route('owner.users.index') }}" class="quick-action-btn">
                <i class="bi bi-people me-1"></i>Manage Users
            </a>
            <a href="{{ route('owner.service-catalog.index') }}" class="quick-action-btn">
                <i class="bi bi-journal-medical me-1"></i>Service Catalog
            </a>
            <a href="{{ route('reception.payouts.index') }}" class="quick-action-btn">
                <i class="bi bi-wallet me-1"></i>View Payouts
            </a>
            @if($pending_discount_count > 0)
            <a href="{{ route('owner.discount-approvals.index') }}" class="quick-action-btn">
                <i class="bi bi-percent me-1"></i>Pending Discounts ({{ $pending_discount_count }})
            </a>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart defaults for dark theme
    Chart.defaults.color = 'rgba(255,255,255,0.6)';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.08)';

    // 7-Day Revenue Sparkline
    var sparkCtx = document.getElementById('sparklineChart');
    if (sparkCtx) {
        var sparkRevenue = @json($trend_revenue ?? []);
        var sparkLabels  = @json($trend_labels ?? []);
        var sparkMax = Math.max(...sparkRevenue, 1);
        new Chart(sparkCtx, {
            type: 'line',
            data: {
                labels: sparkLabels,
                datasets: [{
                    data: sparkRevenue,
                    borderColor: '#34d399',
                    backgroundColor: function(ctx) {
                        var gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 70);
                        gradient.addColorStop(0,   'rgba(52,211,153,0.35)');
                        gradient.addColorStop(1,   'rgba(52,211,153,0)');
                        return gradient;
                    },
                    fill: true,
                    tension: 0.45,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#34d399',
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 900, easing: 'easeInOutCubic' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return ' PKR ' + ctx.parsed.y.toLocaleString(); }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false, min: 0, max: sparkMax * 1.15 }
                }
            }
        });
    }

    // 7-Day Trend Line Chart
    var trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: @json($trend_labels ?? []),
                datasets: [
                    {
                        label: 'Revenue',
                        data: @json($trend_revenue ?? []),
                        borderColor: '#34d399',
                        backgroundColor: 'rgba(52,211,153,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#34d399'
                    },
                    {
                        label: 'Expenses',
                        data: @json($trend_expenses ?? []),
                        borderColor: '#f87171',
                        backgroundColor: 'rgba(248,113,113,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#f87171'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Department Doughnut Chart
    var deptCtx = document.getElementById('deptChart');
    if (deptCtx) {
        var deptData = @json($dept_counts ?? []);
        var labels = Object.keys(deptData).map(function(l) { return l.charAt(0).toUpperCase() + l.slice(1); });
        var values = Object.values(deptData);
        var colors = ['#818cf8', '#34d399', '#fbbf24', '#f87171', '#67e8f9', '#22d3ee'];

        new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: labels.length ? labels : ['No data'],
                datasets: [{
                    data: values.length ? values : [1],
                    backgroundColor: values.length ? colors.slice(0, values.length) : ['rgba(255,255,255,0.1)'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 12 } } },
                cutout: '65%'
            }
        });
    }
});
</script>
@endpush
