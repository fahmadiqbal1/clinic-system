@extends('layouts.app')
@section('title', 'Department P&L')

@section('content')
<div class="fade-in">
    {{-- Page Header with Date Filter --}}
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-building me-2"></i>Department Profit & Loss</h1>
            <p class="text-muted mb-0">Revenue, costs, and profitability breakdown by department</p>
        </div>
        <form method="GET" action="{{ route('owner.department-pnl') }}" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}" />
            </div>
            <div>
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}" />
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
        </form>
    </div>

    {{-- Company-Wide Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="glass-stat hover-lift fade-in delay-1">
                <div class="stat-icon stat-icon-success"><i class="bi bi-arrow-up-circle"></i></div>
                <div>
                    <div class="stat-value" style="color:var(--accent-success);">{{ currency($totals['revenue']) }}</div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-stat hover-lift fade-in delay-1">
                <div class="stat-icon stat-icon-danger"><i class="bi bi-arrow-down-circle"></i></div>
                <div>
                    <div class="stat-value" style="color:var(--accent-danger);">{{ currency($totals['total_expenses'] + $totals['cogs'] + $totals['commissions']) }}</div>
                    <div class="stat-label">Total Costs</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-stat hover-lift fade-in delay-1">
                <div class="stat-icon stat-icon-warning"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-value" style="color:var(--accent-warning);">{{ currency($totals['commissions']) }}</div>
                    <div class="stat-label">Commissions</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-stat hover-lift fade-in delay-1">
                <div class="stat-icon stat-icon-info"><i class="bi bi-wallet2"></i></div>
                <div>
                    <div class="stat-value" style="color: {{ $totals['net_profit'] >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' }};">{{ currency($totals['net_profit']) }}</div>
                    <div class="stat-label">Net Profit</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Department Cards --}}
    <div class="row g-4 mb-4">
        @php
            $deptLabels = ['consultation' => 'Consultation', 'lab' => 'Laboratory', 'radiology' => 'Radiology', 'pharmacy' => 'Pharmacy', 'general' => 'General'];
            $deptIcons = ['consultation' => 'bi-stethoscope', 'lab' => 'bi-droplet', 'radiology' => 'bi-radioactive', 'pharmacy' => 'bi-capsule', 'general' => 'bi-gear'];
        @endphp
        @foreach($pnl as $dept => $data)
            @if($data['revenue'] > 0 || $data['total_expenses'] > 0 || $data['cogs'] > 0 || $data['commissions'] > 0)
            <div class="col-md-6 col-xl-4">
                <div class="glass-card hover-lift fade-in delay-2">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi {{ $deptIcons[$dept] ?? 'bi-building' }} me-2" style="color:var(--role-accent);"></i>
                            {{ $deptLabels[$dept] ?? ucfirst($dept) }}
                        </h6>
                        <span class="badge {{ $data['net_profit'] >= 0 ? 'bg-success' : 'bg-danger' }}" style="font-size:0.75rem;">
                            {{ $data['net_profit'] >= 0 ? 'Profit' : 'Loss' }}
                        </span>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                            <span class="text-muted small">Revenue</span>
                            <span class="fw-semibold" style="color:var(--accent-success);">{{ currency($data['revenue']) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                            <span class="text-muted small">COGS</span>
                            <span class="fw-semibold" style="color:var(--accent-danger);">-{{ currency($data['cogs']) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                            <span class="text-muted small">Commissions</span>
                            <span class="fw-semibold" style="color:var(--accent-warning);">-{{ currency($data['commissions']) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                            <span class="text-muted small">Fixed Expenses</span>
                            <span class="fw-semibold" style="color:var(--accent-danger);">-{{ currency($data['expenses_fixed']) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                            <span class="text-muted small">Variable Expenses</span>
                            <span class="fw-semibold" style="color:var(--accent-danger);">-{{ currency($data['expenses_variable']) }}</span>
                        </div>
                        @if($data['expenses_procurement'] > 0)
                        <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                            <span class="text-muted small">Procurement</span>
                            <span class="fw-semibold" style="color:var(--accent-info);">-{{ currency($data['expenses_procurement']) }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2" style="border-top:2px solid rgba(255,255,255,0.1);">
                        <span class="fw-bold">Net Profit</span>
                        <span class="fw-bold fs-5" style="color: {{ $data['net_profit'] >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' }};">
                            {{ currency($data['net_profit']) }}
                        </span>
                    </div>

                    {{-- Profit margin bar --}}
                    @if($data['revenue'] > 0)
                        @php $margin = round(($data['net_profit'] / $data['revenue']) * 100, 1); @endphp
                        <div class="mt-2">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Profit Margin</small>
                                <small class="fw-semibold" style="color: {{ $margin >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' }};">{{ $margin }}%</small>
                            </div>
                            <div class="progress" style="height:6px; background:rgba(255,255,255,0.06); border-radius:3px;">
                                <div class="progress-bar {{ $margin >= 0 ? 'bg-success' : 'bg-danger' }}" style="width:{{ min(abs($margin), 100) }}%;"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        @endforeach
    </div>

    {{-- Comparison Table --}}
    <div class="glass-card fade-in delay-3">
        <h6 class="form-section-title mb-3"><i class="bi bi-table me-2"></i>Department Comparison</h6>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="sortable-th text-uppercase small text-muted">Department</th>
                        <th class="sortable-th text-uppercase small text-muted text-end">Revenue</th>
                        <th class="sortable-th text-uppercase small text-muted text-end">COGS</th>
                        <th class="sortable-th text-uppercase small text-muted text-end">Commissions</th>
                        <th class="sortable-th text-uppercase small text-muted text-end">Expenses</th>
                        <th class="sortable-th text-uppercase small text-muted text-end">Net Profit</th>
                        <th class="sortable-th text-uppercase small text-muted text-end">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pnl as $dept => $data)
                    <tr>
                        <td class="fw-medium">
                            <i class="bi {{ $deptIcons[$dept] ?? 'bi-building' }} me-2" style="opacity:0.6;"></i>
                            {{ $deptLabels[$dept] ?? ucfirst($dept) }}
                        </td>
                        <td class="text-end" style="color:var(--accent-success);">{{ currency($data['revenue']) }}</td>
                        <td class="text-end text-muted">{{ currency($data['cogs']) }}</td>
                        <td class="text-end text-muted">{{ currency($data['commissions']) }}</td>
                        <td class="text-end text-muted">{{ currency($data['total_expenses']) }}</td>
                        <td class="text-end fw-semibold" style="color: {{ $data['net_profit'] >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' }};">
                            {{ currency($data['net_profit']) }}
                        </td>
                        <td class="text-end">
                            @if($data['revenue'] > 0)
                                @php $m = round(($data['net_profit'] / $data['revenue']) * 100, 1); @endphp
                                <span style="color: {{ $m >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' }};">{{ $m }}%</span>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid rgba(255,255,255,0.15);">
                        <td class="fw-bold">TOTAL</td>
                        <td class="text-end fw-bold" style="color:var(--accent-success);">{{ currency($totals['revenue']) }}</td>
                        <td class="text-end fw-bold text-muted">{{ currency($totals['cogs']) }}</td>
                        <td class="text-end fw-bold text-muted">{{ currency($totals['commissions']) }}</td>
                        <td class="text-end fw-bold text-muted">{{ currency($totals['total_expenses']) }}</td>
                        <td class="text-end fw-bold" style="color: {{ $totals['net_profit'] >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' }};">
                            {{ currency($totals['net_profit']) }}
                        </td>
                        <td class="text-end fw-bold">
                            @if($totals['revenue'] > 0)
                                @php $tm = round(($totals['net_profit'] / $totals['revenue']) * 100, 1); @endphp
                                <span style="color: {{ $tm >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' }};">{{ $tm }}%</span>
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <a href="{{ route('owner.financial-report') }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-bar-graph me-1"></i>Financial Report
        </a>
        <a href="{{ route('owner.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>
@endsection
