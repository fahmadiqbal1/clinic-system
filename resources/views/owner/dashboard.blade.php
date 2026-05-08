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
        <div class="col-md-3 fade-in delay-5">
            <a href="{{ route('dashboard.low-stock-alerts') }}" class="text-decoration-none">
            <div class="glass-card p-3 hover-lift" style="{{ $out_of_stock_count > 0 ? 'border-left:3px solid var(--accent-danger);' : '' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-danger"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <div class="text-muted small">Out of Stock</div>
                        <div class="stat-value glow-danger">{{ $out_of_stock_count }}</div>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-md-3 fade-in delay-6">
            <a href="{{ route('dashboard.low-stock-alerts') }}" class="text-decoration-none">
            <div class="glass-card p-3 hover-lift" style="{{ $low_stock_count > 0 ? 'border-left:3px solid var(--accent-warning);' : '' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-warning"><i class="bi bi-exclamation-triangle"></i></div>
                    <div>
                        <div class="text-muted small">Low Stock</div>
                        <div class="stat-value glow-warning">{{ $low_stock_count }}</div>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-md-3 fade-in delay-6">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-warning"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="text-muted small">Pending Payouts ({{ $pending_payout_count }})</div>
                        <div class="stat-value glow-warning">{{ number_format($pending_payout_total, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 fade-in delay-7">
            @if($overdue_receipts_count > 0)
            <a href="{{ route('procurement.index') }}" class="text-decoration-none">
            <div class="glass-card p-3 hover-lift" style="border-left:3px solid var(--accent-danger);">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-danger"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <div class="text-muted small">Overdue Receipts</div>
                        <div class="stat-value glow-danger">{{ $overdue_receipts_count }}</div>
                    </div>
                </div>
            </div>
            </a>
            @else
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-info"><i class="bi bi-percent"></i></div>
                    <div>
                        <div class="text-muted small">Pending Discounts</div>
                        <div class="stat-value">{{ $pending_discount_count }}</div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Staff & Credentials --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 fade-in delay-2">
            <a href="{{ route('owner.attendance.index') }}" class="text-decoration-none">
            <div class="glass-card p-3 hover-lift" style="{{ $on_shift_count > 0 ? 'border-left:3px solid var(--accent-success);' : '' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-success"><i class="bi bi-person-check"></i></div>
                    <div>
                        <div class="text-muted small">Staff On Shift Now</div>
                        <div class="stat-value glow-success">{{ $on_shift_count }}</div>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-md-6 fade-in delay-3">
            <a href="{{ route('owner.credentials.index') }}" class="text-decoration-none">
            <div class="glass-card p-3 hover-lift" style="{{ $pending_credentials_count > 0 ? 'border-left:3px solid var(--accent-warning);' : '' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-warning"><i class="bi bi-patch-exclamation"></i></div>
                    <div>
                        <div class="text-muted small">Credentials Awaiting Review</div>
                        <div class="stat-value {{ $pending_credentials_count > 0 ? 'glow-warning' : '' }}">{{ $pending_credentials_count }}</div>
                    </div>
                </div>
            </div>
            </a>
        </div>
    </div>

    {{-- Pending Vendor Price Lists --}}
    @if($pending_price_list_count > 0)
    <div class="glass-card p-4 mb-4 fade-in" style="border-left:3px solid var(--accent-primary);">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon stat-icon-primary"><i class="bi bi-file-earmark-check"></i></div>
                <div>
                    <div class="fw-semibold" style="color:var(--text-primary);">{{ $pending_price_list_count }} Vendor Price List{{ $pending_price_list_count !== 1 ? 's' : '' }} Ready to Review</div>
                    <div class="text-muted small">AI extraction complete — approve to update or create inventory items</div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr>
                    <th>Vendor</th><th>File</th><th>Items</th><th>Flagged</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>
                    @foreach($pending_price_lists as $pl)
                    <tr>
                        <td class="fw-medium">{{ $pl->vendor->name }}</td>
                        <td><small class="text-muted">{{ $pl->original_filename }}</small></td>
                        <td>{{ $pl->item_count ?? '—' }}</td>
                        <td>
                            @if($pl->flagged_count)
                                <span class="badge bg-warning text-dark">{{ $pl->flagged_count }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $pl->status === 'flagged' ? 'bg-warning text-dark' : 'bg-primary' }}">
                                {{ ucfirst($pl->status) }}
                            </span>
                        </td>
                        <td class="d-flex gap-1 align-items-center">
                            <a href="{{ route('owner.vendors.price-list.review', $pl) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>Review
                            </a>
                            <form method="POST" action="{{ route('owner.vendors.price-list.reject', $pl) }}"
                                  onsubmit="return confirm('Reject this price list? It will be removed from the review queue.')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject price list">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($pending_price_list_count > 5)
            <div class="text-center mt-2">
                <a href="{{ route('owner.vendors.index') }}" class="btn btn-sm btn-outline-secondary">View all {{ $pending_price_list_count }} pending</a>
            </div>
        @endif
    </div>
    @endif

    {{-- Procurement Summary --}}
    @if($pending_procurement_count > 0)
    <div class="glass-card p-4 mb-4 fade-in delay-6" style="border-left:3px solid var(--accent-warning);">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon stat-icon-warning"><i class="bi bi-cart-check"></i></div>
                <div>
                    <div class="fw-semibold" style="color:var(--text-primary);">{{ $pending_procurement_count }} Procurement Request{{ $pending_procurement_count !== 1 ? 's' : '' }} Awaiting Approval</div>
                    <div class="text-muted small">Purchase orders, service requests, price changes pending your review</div>
                </div>
            </div>
            <a href="{{ route('procurement.index') }}" class="btn btn-warning btn-sm"><i class="bi bi-clipboard-check me-1"></i>Review Now</a>
        </div>
    </div>
    @endif

    {{-- Pending Tasks --}}
    @php
        $tasks = collect();
        if ($pending_procurement_count > 0) $tasks->push(['label' => 'Procurement requests awaiting approval', 'count' => $pending_procurement_count, 'icon' => 'bi-cart3', 'color' => 'warning', 'url' => route('procurement.index')]);
        if ($pending_discount_count > 0) $tasks->push(['label' => 'Discount requests awaiting approval', 'count' => $pending_discount_count, 'icon' => 'bi-percent', 'color' => 'warning', 'url' => route('owner.discount-approvals.index')]);
        if ($pending_payout_count > 0) $tasks->push(['label' => 'Doctor payouts pending', 'count' => $pending_payout_count, 'icon' => 'bi-wallet2', 'color' => 'info', 'url' => route('reception.payouts.index')]);
        if ($unpaid_invoices_count > 0) $tasks->push(['label' => 'Unpaid invoices across system', 'count' => $unpaid_invoices_count, 'icon' => 'bi-receipt', 'color' => 'primary', 'url' => '#']);
        if ($out_of_stock_count > 0) $tasks->push(['label' => 'Items out of stock — auto-order may be in progress', 'count' => $out_of_stock_count, 'icon' => 'bi-box-seam', 'color' => 'danger', 'url' => route('dashboard.low-stock-alerts')]);
        if ($low_stock_count > 0) $tasks->push(['label' => 'Low stock items approaching minimum', 'count' => $low_stock_count, 'icon' => 'bi-exclamation-triangle', 'color' => 'warning', 'url' => route('dashboard.low-stock-alerts')]);
        if (!empty($overdue_receipts_count) && $overdue_receipts_count > 0) $tasks->push(['label' => 'Inventory receipts overdue (>48h past approval)', 'count' => $overdue_receipts_count, 'icon' => 'bi-clock-history', 'color' => 'danger', 'url' => route('procurement.index')]);
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

    {{-- AI & Infrastructure card (Phase 3 — flag-gated) --}}
    @if(\App\Models\PlatformSetting::isEnabled('ai.sidecar.enabled') || \App\Models\PlatformSetting::isEnabled('ai.ragflow.enabled'))
    @php $pendingAiCount = \App\Models\AiActionRequest::where('status', 'pending')->count(); @endphp
    <div class="glass-card p-4 mb-4 fade-in delay-7" style="border:1px solid rgba(129,140,248,0.3);">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0"><i class="bi bi-cpu me-2" style="color:var(--accent-primary);"></i>AI & Infrastructure</h5>
            <a href="{{ route('owner.ai-oversight') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem;">View All →</a>
        </div>
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <div class="p-2 rounded text-center" style="background:rgba(129,140,248,0.08); border:1px solid rgba(129,140,248,0.2);">
                    <div class="small text-muted">Sidecar</div>
                    <div class="fw-semibold" style="color:{{ \App\Models\PlatformSetting::isEnabled('ai.sidecar.enabled') ? 'var(--accent-success)' : 'var(--text-muted)' }}; font-size:0.85rem;">
                        {{ \App\Models\PlatformSetting::isEnabled('ai.sidecar.enabled') ? 'ON' : 'OFF' }}
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded text-center" style="background:rgba(52,211,153,0.08); border:1px solid rgba(52,211,153,0.2);">
                    <div class="small text-muted">RAGFlow</div>
                    <div class="fw-semibold" style="color:{{ \App\Models\PlatformSetting::isEnabled('ai.ragflow.enabled') ? 'var(--accent-success)' : 'var(--text-muted)' }}; font-size:0.85rem;">
                        {{ \App\Models\PlatformSetting::isEnabled('ai.ragflow.enabled') ? 'ON' : 'OFF' }}
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded text-center" style="background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.2);">
                    <div class="small text-muted">Pending AI Requests</div>
                    <div class="fw-semibold" style="color:{{ $pendingAiCount > 0 ? 'var(--accent-warning)' : 'var(--text-primary)' }}; font-size:0.85rem;">
                        {{ $pendingAiCount }}
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded text-center" style="background:rgba(129,140,248,0.08); border:1px solid rgba(129,140,248,0.2);">
                    <div class="small text-muted">Knowledge Assistant</div>
                    <div class="fw-semibold" style="color:{{ \App\Models\PlatformSetting::isEnabled('ai.chat.enabled.owner') ? 'var(--accent-success)' : 'var(--text-muted)' }}; font-size:0.85rem;">
                        {{ \App\Models\PlatformSetting::isEnabled('ai.chat.enabled.owner') ? 'Active' : 'Inactive' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

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
