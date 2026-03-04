@extends('layouts.app')
@section('title', 'Payout Analytics — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-wallet2 me-2" style="color:var(--accent-primary);"></i>Payout Analytics</h1>
            <p class="page-subtitle">Overview of staff payouts and commission distribution</p>
        </div>
        <a href="{{ route('reception.payouts.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Generate Payout</a>
    </div>

    @if (session('success'))
        <div class="alert-banner-success fade-in delay-1"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
         SECTION 1: PAYOUTS AWAITING OWNER APPROVAL
    ═══════════════════════════════════════════════════════════════════ --}}
    @if($awaitingApproval->count() > 0)
    <div class="glass-card p-3 mb-4 fade-in delay-1" style="border-left: 4px solid var(--accent-warning);">
        <h5 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Payouts Awaiting Your Approval ({{ $awaitingApproval->count() }})</h5>
        @foreach ($awaitingApproval as $ap)
            <div class="data-row hover-lift" style="padding: 0.75rem 1rem;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h6 class="fw-semibold mb-1" style="color:var(--text-primary);">{{ $ap->doctor?->name ?? 'Unknown' }}</h6>
                        <p class="small mb-0" style="color:var(--text-muted);">
                            Salary: {{ currency($ap->salary_amount) }} + Commission: {{ currency($ap->total_amount - $ap->salary_amount) }}
                            <span class="ms-2"><i class="bi bi-person me-1"></i>Created by {{ $ap->creator?->name ?? '—' }}</span>
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="stat-value" style="font-size:1.1rem;">{{ currency($ap->paid_amount) }}</span>
                        <form method="POST" action="{{ route('payouts.approve', $ap) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-success" onclick="return confirm('Approve this payout?')"><i class="bi bi-check-lg me-1"></i>Approve</button>
                        </form>
                        <form method="POST" action="{{ route('payouts.reject', $ap) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Reject this payout? Commission entries will be released.')"><i class="bi bi-x-lg me-1"></i>Reject</button>
                        </form>
                        <a href="{{ route('reception.payouts.show', $ap) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Details</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
         SECTION 2: STAFF OVERVIEW — UNPAID COMMISSIONS PER STAFF
    ═══════════════════════════════════════════════════════════════════ --}}
    <div class="glass-card p-3 mb-4 fade-in delay-1">
        <h5 class="fw-semibold mb-3"><i class="bi bi-people me-2" style="color:var(--accent-primary);"></i>Staff Payout Overview</h5>
        <div class="table-responsive">
            <table class="table table-sm mb-0" style="color:var(--text-primary);">
                <thead>
                    <tr style="border-bottom: 2px solid var(--glass-border);">
                        <th>Staff Member</th>
                        <th>Role</th>
                        <th class="text-end">Base Salary</th>
                        <th class="text-end">Unpaid Commission</th>
                        <th class="text-end">Total Paid (All Time)</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staffOverview as $s)
                        <tr>
                            <td class="fw-semibold">{{ $s['name'] }}</td>
                            <td><span class="badge-glass badge-glass-info">{{ $s['roles'] }}</span></td>
                            <td class="text-end">{{ $s['baseSalary'] > 0 ? currency($s['baseSalary']) : '—' }}</td>
                            <td class="text-end">
                                @if($s['unpaid'] > 0)
                                    <span class="fw-semibold" style="color:var(--accent-warning);">{{ currency($s['unpaid']) }}</span>
                                @else
                                    <span style="color:var(--text-muted);">{{ currency(0) }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ currency($s['totalPaid']) }}</td>
                            <td class="text-center">
                                <a href="{{ route('owner.payouts.performance', $s['id']) }}" class="btn btn-sm btn-outline-info" title="View analytics"><i class="bi bi-bar-chart"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No commission-earning staff found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4 fade-in delay-1">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-success"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="text-muted small">Total Paid (Period)</div>
                        <div class="stat-value glow-success">{{ currency($totalPaid) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 fade-in delay-2">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-warning"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <div class="text-muted small">Pending Confirmation</div>
                        <div class="stat-value glow-warning">{{ $pendingCount }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 fade-in delay-3">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-info"><i class="bi bi-check2-circle"></i></div>
                    <div>
                        <div class="text-muted small">Confirmed</div>
                        <div class="stat-value glow-info">{{ $confirmedCount }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="glass-card p-3 mb-4 fade-in delay-2">
        <form method="GET" action="{{ route('owner.payouts.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1" style="color:var(--text-muted);">Staff Member</label>
                <select name="staff_id" class="form-select form-select-sm">
                    <option value="">All Staff</option>
                    @foreach ($staffMembers as $member)
                        <option value="{{ $member->id }}" @selected(($filters['staff_id'] ?? '') == $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" style="color:var(--text-muted);">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="confirmed" @selected(($filters['status'] ?? '') === 'confirmed')>Confirmed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" style="color:var(--text-muted);">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1" style="color:var(--text-muted);">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-semibold"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('owner.payouts.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Clear</a>
            </div>
        </form>
    </div>

    {{-- Payouts Table --}}
    <div class="glass-card fade-in delay-3">
        <div class="card-body p-0">
            @forelse ($payouts as $payout)
                <div class="data-row hover-lift" style="padding: 1rem 1.25rem;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon stat-icon-primary"><i class="bi bi-person-badge"></i></div>
                            <div>
                                <h6 class="fw-semibold mb-1" style="color:var(--text-primary);">{{ $payout->doctor?->name ?? 'Unknown' }}</h6>
                                <p class="small mb-0" style="color:var(--text-muted);">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    {{ $payout->period_start?->format('M d, Y') ?? 'N/A' }} — {{ $payout->period_end?->format('M d, Y') ?? 'N/A' }}
                                    <span class="ms-2"><i class="bi bi-person me-1"></i>by {{ $payout->creator?->name ?? '—' }}</span>
                                    @if($payout->payout_type === 'monthly')
                                        <span class="badge-glass badge-glass-info ms-2">Monthly</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-end">
                                <span class="stat-value" style="font-size:1.2rem;">{{ currency($payout->paid_amount) }}</span>
                                @if($payout->paid_amount < $payout->total_amount)
                                    <br><small style="color:var(--text-muted);">of {{ currency($payout->total_amount) }}</small>
                                @endif
                                <br>
                                <span class="badge-glass @if($payout->status === 'confirmed') badge-glass-success @else badge-glass-warning @endif">
                                    <i class="bi {{ $payout->status === 'confirmed' ? 'bi-check-circle' : 'bi-clock' }} me-1"></i>{{ ucfirst($payout->status) }}
                                </span>
                                @if($payout->approval_status)
                                    <span class="badge-glass @if($payout->approval_status === 'approved') badge-glass-success @elseif($payout->approval_status === 'rejected') badge-glass-danger @else badge-glass-warning @endif ms-1">
                                        {{ ucfirst($payout->approval_status) }}
                                    </span>
                                @endif
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <a href="{{ route('reception.payouts.show', $payout) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
                                @if($payout->doctor_id)
                                    <a href="{{ route('owner.payouts.performance', $payout->doctor_id) }}" class="btn btn-sm btn-outline-info"><i class="bi bi-bar-chart me-1"></i>Analytics</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state py-5">
                    <i class="bi bi-wallet2" style="font-size:2.5rem;"></i>
                    <h5>No payouts found</h5>
                    <p class="mb-0">Adjust filters or generate a new payout.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if($payouts->hasPages())
        <div class="d-flex justify-content-center mt-3 fade-in delay-4">{{ $payouts->links() }}</div>
    @endif
</div>
@endsection
