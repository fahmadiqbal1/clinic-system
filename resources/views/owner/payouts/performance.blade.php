@extends('layouts.app')
@section('title', ($staff->name ?? 'Staff') . ' Performance — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <a href="{{ route('owner.payouts.index') }}" class="text-decoration-none small" style="color:var(--accent-primary);">
                <i class="bi bi-arrow-left me-1"></i>Back to Payouts
            </a>
            <h1 class="page-header mb-1 mt-2"><i class="bi bi-bar-chart-line me-2" style="color:var(--accent-primary);"></i>{{ $staff->name }}</h1>
            <p class="page-subtitle">Performance analytics &amp; earnings breakdown</p>
        </div>
        <a href="{{ route('reception.payouts.create', ['doctor_id' => $staff->id]) }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Generate Payout
        </a>
    </div>

    {{-- Date Range Filter --}}
    <div class="glass-card p-3 mb-4 fade-in delay-1">
        <form method="GET" action="{{ route('owner.payouts.performance', $staff) }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1" style="color:var(--text-muted);">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" style="color:var(--text-muted);">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary fw-semibold"><i class="bi bi-funnel me-1"></i>Apply</button>
            </div>
        </form>
    </div>

    {{-- Unpaid Balance Banner --}}
    @if($unpaidBalance > 0)
    <div class="alert-banner-warning mb-4 fade-in delay-1">
        <div class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle me-2"></i><strong>{{ currency($unpaidBalance) }}</strong> in unpaid commissions</span>
            <a href="{{ route('reception.payouts.create', ['doctor_id' => $staff->id]) }}" class="btn btn-sm btn-warning"><i class="bi bi-cash me-1"></i>Pay Now</a>
        </div>
    </div>
    @endif

    {{-- Department Earnings Cards --}}
    @php
        $deptMeta = [
            'consultation' => ['label' => 'Consultation', 'icon' => 'bi-heart-pulse', 'color' => 'danger'],
            'lab'          => ['label' => 'Laboratory',    'icon' => 'bi-droplet',     'color' => 'info'],
            'radiology'    => ['label' => 'Radiology',     'icon' => 'bi-radioactive', 'color' => 'warning'],
            'pharmacy'     => ['label' => 'Pharmacy',      'icon' => 'bi-capsule',     'color' => 'success'],
        ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach($deptMeta as $dept => $meta)
        @php
            $deptData = $deptEarnings[$dept] ?? null;
            $earned = $deptData['total'] ?? 0;
            $count = $deptData['invoice_count'] ?? 0;
            $pct = $totalEarnings > 0 ? round(($earned / $totalEarnings) * 100, 1) : 0;
        @endphp
        <div class="col-6 col-lg-3 fade-in delay-{{ $loop->iteration }}">
            <div class="glass-card p-3 hover-lift h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="stat-icon stat-icon-{{ $meta['color'] }}"><i class="bi {{ $meta['icon'] }}"></i></div>
                    <h6 class="mb-0 fw-semibold" style="color:var(--text-primary);">{{ $meta['label'] }}</h6>
                </div>
                <div class="stat-value glow-{{ $meta['color'] }}" style="font-size:1.4rem;">{{ currency($earned) }}</div>
                <div class="d-flex justify-content-between mt-1">
                    <small style="color:var(--text-muted);">{{ $count }} invoice{{ $count !== 1 ? 's' : '' }}</small>
                    <small style="color:var(--accent-{{ $meta['color'] }});">{{ $pct }}%</small>
                </div>
                {{-- Earnings bar --}}
                <div class="progress mt-2" style="height:4px; background:var(--glass-border);">
                    <div class="progress-bar bg-{{ $meta['color'] }}" style="width:{{ $pct }}%;"></div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        {{-- Ordering Analysis --}}
        <div class="col-lg-6 fade-in delay-3">
            <div class="glass-card p-3 h-100">
                <h6 class="fw-semibold mb-3" style="color:var(--text-primary);"><i class="bi bi-clipboard-data me-2" style="color:var(--accent-info);"></i>Ordering Analysis</h6>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="color:var(--text-primary);">
                        <thead>
                            <tr style="border-color:var(--glass-border);">
                                <th>Department</th>
                                <th class="text-end">Orders</th>
                                <th class="text-end">Total Value</th>
                                <th class="text-end">Avg Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-color:var(--glass-border);">
                                <td><i class="bi bi-droplet me-1" style="color:var(--accent-info);"></i>Laboratory</td>
                                <td class="text-end">{{ $labOrders->count ?? 0 }}</td>
                                <td class="text-end">{{ currency($labOrders->total_value ?? 0) }}</td>
                                <td class="text-end">{{ currency($labOrders->avg_value ?? 0) }}</td>
                            </tr>
                            <tr style="border-color:var(--glass-border);">
                                <td><i class="bi bi-radioactive me-1" style="color:var(--accent-warning);"></i>Radiology</td>
                                <td class="text-end">{{ $radOrders->count ?? 0 }}</td>
                                <td class="text-end">{{ currency($radOrders->total_value ?? 0) }}</td>
                                <td class="text-end">{{ currency($radOrders->avg_value ?? 0) }}</td>
                            </tr>
                            <tr style="border-color:var(--glass-border);">
                                <td><i class="bi bi-capsule me-1" style="color:var(--accent-success);"></i>Pharmacy</td>
                                <td class="text-end">{{ $pharmacyOrders->count ?? 0 }}</td>
                                <td class="text-end">{{ currency($pharmacyOrders->total_value ?? 0) }}</td>
                                <td class="text-end">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top Earning Services --}}
        <div class="col-lg-6 fade-in delay-4">
            <div class="glass-card p-3 h-100">
                <h6 class="fw-semibold mb-3" style="color:var(--text-primary);"><i class="bi bi-star me-2" style="color:var(--accent-warning);"></i>Top Earning Services</h6>
                @if($topServices->isEmpty())
                    <div class="text-center py-3" style="color:var(--text-muted);">
                        <i class="bi bi-inbox" style="font-size:1.5rem;"></i>
                        <p class="mb-0 mt-1">No service data in this period</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="color:var(--text-primary);">
                            <thead>
                                <tr style="border-color:var(--glass-border);">
                                    <th>Service</th>
                                    <th>Dept</th>
                                    <th class="text-end">Times</th>
                                    <th class="text-end">Earned</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topServices as $svc)
                                <tr style="border-color:var(--glass-border);">
                                    <td>{{ $svc->service_name ?? 'Unknown' }}</td>
                                    <td>
                                        @php $dc = $deptMeta[$svc->department] ?? ['icon' => 'bi-circle', 'color' => 'secondary']; @endphp
                                        <i class="bi {{ $dc['icon'] }}" style="color:var(--accent-{{ $dc['color'] }});"></i>
                                    </td>
                                    <td class="text-end">{{ $svc->times_used }}</td>
                                    <td class="text-end fw-semibold" style="color:var(--accent-success);">{{ currency($svc->total_earned) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Payout History --}}
    <div class="glass-card mb-4 fade-in delay-5">
        <div class="card-header d-flex justify-content-between align-items-center" style="border-color:var(--glass-border);">
            <span><i class="bi bi-journal-text me-2" style="color:var(--accent-primary);"></i>Payout History</span>
            <span class="badge-glass badge-glass-primary">{{ $payouts->count() }} records</span>
        </div>
        <div class="card-body p-0">
            @forelse($payouts as $p)
                <div class="data-row" style="padding:0.75rem 1.25rem;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-medium" style="color:var(--text-primary);">{{ currency($p->paid_amount) }}</span>
                            @if($p->paid_amount < $p->total_amount)
                                <small style="color:var(--text-muted);">of {{ currency($p->total_amount) }}</small>
                            @endif
                            <br>
                            <small style="color:var(--text-muted);">
                                {{ $p->period_start?->format('M d') }} — {{ $p->period_end?->format('M d, Y') }}
                                &middot; by {{ $p->creator?->name ?? '—' }}
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-glass @if($p->status === 'confirmed') badge-glass-success @else badge-glass-warning @endif">
                                <i class="bi {{ $p->status === 'confirmed' ? 'bi-check-circle' : 'bi-clock' }} me-1"></i>{{ ucfirst($p->status) }}
                            </span>
                            <a href="{{ route('reception.payouts.show', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state py-4">
                    <i class="bi bi-wallet2" style="font-size:1.8rem;"></i>
                    <p class="mb-0 mt-2">No payouts recorded yet</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
