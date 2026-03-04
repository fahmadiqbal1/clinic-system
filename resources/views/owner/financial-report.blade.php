@extends('layouts.app')
@section('title', 'Financial Report')

@section('content')
<div class="fade-in">
    {{-- Page Header with Date Filter --}}
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-graph-up-arrow me-2"></i>Financial Report</h1>
            <p class="text-muted mb-0">Revenue, expenses, and profitability analysis</p>
        </div>
        <form method="GET" action="{{ route('owner.financial-report') }}" class="d-flex gap-2 align-items-end">
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

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="glass-stat">
                <div class="stat-icon" style="color:var(--accent-success);"><i class="bi bi-arrow-up-circle"></i></div>
                <div class="stat-value" style="color:var(--accent-success);">{{ currency($revenue) }}</div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-stat">
                <div class="stat-icon" style="color:var(--accent-danger);"><i class="bi bi-arrow-down-circle"></i></div>
                <div class="stat-value" style="color:var(--accent-danger);">{{ currency($expenses) }}</div>
                <div class="stat-label">Total Expenses</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-stat">
                <div class="stat-icon" style="color:var(--accent-info);"><i class="bi bi-wallet2"></i></div>
                <div class="stat-value" style="color:var(--accent-info);">{{ currency($net_profit) }}</div>
                <div class="stat-label">Net Profit</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-stat">
                <div class="stat-icon"><i class="bi bi-calendar-range"></i></div>
                <div class="stat-value" style="font-size:0.95rem;">{{ $from }}</div>
                <div class="stat-label">to {{ $to }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Department Breakdown --}}
        @if(count($department_breakdown) > 0)
        <div class="col-md-6">
            <div class="glass-panel">
                <h6 class="form-section-title mb-3"><i class="bi bi-building me-2"></i>Expenses by Department</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th class="text-end">Expenses</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($department_breakdown as $department => $data)
                            <tr>
                                <td><i class="bi bi-circle-fill me-2" style="font-size:0.5rem;opacity:0.5;"></i>{{ ucfirst($department) }}</td>
                                <td class="text-end"><span class="price-display">{{ currency($data['expenses']) }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Doctor Breakdown --}}
        @if(count($doctor_breakdown) > 0)
        <div class="col-md-6">
            <div class="glass-panel">
                <h6 class="form-section-title mb-3"><i class="bi bi-person-badge me-2"></i>Revenue by Doctor</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($doctor_breakdown as $doctor)
                            <tr>
                                <td><i class="bi bi-person me-2" style="opacity:0.5;"></i>{{ $doctor['name'] }}</td>
                                <td class="text-end"><span class="price-display">{{ currency($doctor['revenue']) }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="mt-4">
        <a href="{{ route('owner.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>
@endsection
