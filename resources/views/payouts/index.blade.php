@extends('layouts.app')

@section('title', 'Staff Payouts')

@section('content')
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-header"><i class="bi bi-cash-coin me-2"></i>Staff Payouts</h1>
            <p class="page-subtitle">Manage staff earnings and disbursements</p>
        </div>
        @can('create', App\Models\DoctorPayout::class)
            <a href="{{ route('reception.payouts.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Generate Payout
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert-banner-success fade-in delay-1">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert-banner-danger fade-in delay-1">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Owner Filter Bar --}}
    @if (auth()->user()->hasRole('Owner') && $staffList->count() > 0)
        <div class="glass-card p-3 mb-4 fade-in delay-1">
            <form method="GET" action="{{ route('reception.payouts.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-white-50 mb-1">Staff Member</label>
                    <select name="staff_id" class="form-select form-select-sm">
                        <option value="">All Staff</option>
                        @foreach ($staffList as $member)
                            <option value="{{ $member->id }}" @selected(request('staff_id') == $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-white-50 mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="confirmed" @selected(request('status') === 'confirmed')>Confirmed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-white-50 mb-1">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-white-50 mb-1">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('reception.payouts.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    @endif

    <div class="card glass-card fade-in delay-2">
        <div class="card-body p-0">
            @forelse ($payouts as $payout)
                <div class="data-row hover-lift" style="padding: 1.25rem 1.5rem;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon stat-icon-primary">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div>
                                <h3 class="h5 fw-semibold mb-1" style="color: var(--text-primary);">
                                    {{ $payout->doctor?->name ?? 'Unknown Staff' }}
                                </h3>
                                <p class="small mb-0" style="color: var(--text-tertiary);">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    {{ $payout->period_start?->format('M d, Y') ?? 'N/A' }} — {{ $payout->period_end?->format('M d, Y') ?? 'N/A' }}
                                    @if ($payout->paid_amount < $payout->total_amount)
                                        <span class="badge-glass badge-glass-info ms-2" style="font-size:0.7rem;">Partial</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="text-end">
                            <p class="stat-value mb-0">{{ currency($payout->paid_amount) }}</p>
                            @if ($payout->paid_amount < $payout->total_amount)
                                <p class="small text-white-50 mb-1">of {{ currency($payout->total_amount) }}</p>
                            @endif
                            <span class="badge-glass @if ($payout->status === 'confirmed') badge-glass-success @else badge-glass-warning @endif">
                                <i class="bi {{ $payout->status === 'confirmed' ? 'bi-check-circle' : 'bi-clock' }} me-1"></i>
                                {{ ucfirst($payout->status) }}
                            </span>
                            @if($payout->approval_status)
                                <span class="badge-glass ms-1 @if($payout->approval_status === 'approved') badge-glass-success @elseif($payout->approval_status === 'rejected') badge-glass-danger @else badge-glass-warning @endif">
                                    {{ ucfirst($payout->approval_status) }}
                                </span>
                            @endif
                            @if($payout->payout_type === 'monthly')
                                <span class="badge-glass badge-glass-info ms-1" style="font-size:0.7rem;">Monthly</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-3 pt-2" style="border-top: 1px solid rgba(255,255,255,0.06);">
                        <a href="{{ route('reception.payouts.show', $payout) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i> View Details
                        </a>
                        @if ($payout->status === 'confirmed' && auth()->user()->hasRole('Owner'))
                            <a href="{{ route('owner.payouts.correction-create', $payout) }}" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-pencil-square me-1"></i> Create Correction
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <i class="bi bi-inbox" style="font-size: 2.5rem; color: var(--text-tertiary);"></i>
                    <p class="mt-2 mb-0" style="color: var(--text-tertiary);">No payouts found</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        {{ $payouts->withQueryString()->links() }}
    </div>
</div>
@endsection