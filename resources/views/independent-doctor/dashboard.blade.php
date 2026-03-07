@extends('layouts.app')
@section('title', 'Independent Doctor Dashboard — ' . config('app.name'))

@section('content')
<div class="container mt-4">

    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1">
                <i class="bi bi-person-workspace me-2" style="color:var(--accent-primary);"></i>
                Independent Doctor Portal
            </h2>
            <p class="page-subtitle mb-0">
                Welcome back, {{ auth()->user()->name }}
                <span class="badge ms-2" style="background:rgba(255,193,7,0.2); color:#ffc107; font-size:0.7rem; border:1px solid rgba(255,193,7,0.3);">
                    <i class="bi bi-link-45deg me-1"></i>External / Referral
                </span>
            </p>
        </div>
        <a href="{{ route('independent-doctor.patients.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>New Referral Patient
        </a>
    </div>

    {{-- Pending Payout Alert --}}
    @if($pendingPayouts > 0)
    <div class="alert-banner-success mb-4 fade-in delay-1">
        <div class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-wallet2 me-2"></i><strong>{{ $pendingPayouts }}</strong> payout{{ $pendingPayouts > 1 ? 's' : '' }} awaiting your confirmation</span>
            <a href="{{ route('reception.payouts.index') }}" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Review Payouts</a>
        </div>
    </div>
    @endif

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-1">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-primary mb-1" style="font-size:1.6rem;">{{ $totalReferrals }}</div>
                    <div class="stat-label"><i class="bi bi-people me-1"></i>Total Referrals</div>
                    <a href="{{ route('independent-doctor.patients.index') }}" class="small" style="color:var(--accent-primary);">View all &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-2">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-warning mb-1" style="font-size:1.6rem;">{{ $todayReferrals }}</div>
                    <div class="stat-label"><i class="bi bi-calendar-day me-1"></i>Today's Referrals</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-3">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-info mb-1" style="font-size:1.6rem;">{{ $pendingInvoices }}</div>
                    <div class="stat-label"><i class="bi bi-hourglass-split me-1"></i>Pending Orders</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 hover-lift fade-in delay-4">
                <div class="card-body glass-stat text-center py-3">
                    <div class="stat-value glow-success mb-1" style="font-size:1.6rem;">{{ $completedInvoices }}</div>
                    <div class="stat-label"><i class="bi bi-check-circle me-1"></i>Completed Orders</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Earnings Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 hover-lift fade-in delay-2 accent-left-success">
                <div class="card-body py-3">
                    <div class="stat-label mb-1"><i class="bi bi-cash-stack me-1"></i>Total Commission Earned</div>
                    <div class="stat-value glow-success" style="font-size:1.4rem;">{{ currency($totalEarnings) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 hover-lift fade-in delay-3 accent-left-warning">
                <div class="card-body py-3">
                    <div class="stat-label mb-1"><i class="bi bi-clock-history me-1"></i>Unpaid Commission</div>
                    <div class="stat-value glow-warning" style="font-size:1.4rem;">{{ currency($unpaidEarnings) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 hover-lift fade-in delay-4 accent-left-info">
                <div class="card-body py-3">
                    <div class="stat-label mb-1"><i class="bi bi-wallet2 me-1"></i>Paid Commission</div>
                    <div class="stat-value glow-info" style="font-size:1.4rem;">{{ currency($paidEarnings) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Recent Referral Patients --}}
        <div class="col-lg-7">
            <div class="glass-card p-4 fade-in delay-2">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-people me-2" style="color:var(--accent-primary);"></i>Recent Referral Patients</h5>
                    <a href="{{ route('independent-doctor.patients.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                @if($recentPatients->isEmpty())
                    <div class="text-center py-4" style="color:var(--text-muted);">
                        <i class="bi bi-person-plus" style="font-size:2rem; opacity:0.3;"></i>
                        <p class="mt-2 mb-0">No referral patients yet.</p>
                        <a href="{{ route('independent-doctor.patients.create') }}" class="btn btn-primary btn-sm mt-3">
                            <i class="bi bi-plus-circle me-1"></i>Register First Patient
                        </a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size:0.9rem;">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Phone</th>
                                    <th>Orders</th>
                                    <th>Registered</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentPatients as $patient)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $patient->full_name }}</div>
                                        <small style="color:var(--text-muted);">{{ $patient->gender }}</small>
                                    </td>
                                    <td>{{ $patient->phone ?? '—' }}</td>
                                    <td>
                                        <span class="badge" style="background:rgba(var(--accent-primary-rgb),0.15); color:var(--accent-primary);">
                                            {{ $patient->invoices->count() }} order(s)
                                        </span>
                                    </td>
                                    <td><small style="color:var(--text-muted);">{{ $patient->created_at->format('d M Y') }}</small></td>
                                    <td>
                                        <a href="{{ route('independent-doctor.patients.show', $patient) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent Commission Transactions --}}
        <div class="col-lg-5">
            <div class="glass-card p-4 fade-in delay-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-receipt me-2" style="color:var(--accent-success);"></i>Recent Commissions</h5>
                    <a href="{{ route('doctor.invoices.index') }}" class="btn btn-sm btn-outline-secondary">All Invoices</a>
                </div>
                @if($recentTransactions->isEmpty())
                    <div class="text-center py-4" style="color:var(--text-muted);">
                        <i class="bi bi-cash-coin" style="font-size:2rem; opacity:0.3;"></i>
                        <p class="mt-2 mb-0">No commission transactions yet.</p>
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($recentTransactions as $tx)
                        <div class="list-group-item bg-transparent border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="small fw-semibold">
                                        {{ $tx->invoice?->department ? ucfirst($tx->invoice->department) : '—' }}
                                    </div>
                                    <small style="color:var(--text-muted);">{{ $tx->created_at->format('d M Y') }}</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold" style="color:var(--accent-success);">
                                        +{{ currency($tx->amount) }}
                                    </div>
                                    @if($tx->payout_id)
                                        <span class="badge" style="background:rgba(25,135,84,0.2); color:#198754; font-size:0.65rem;">Paid</span>
                                    @else
                                        <span class="badge" style="background:rgba(255,193,7,0.2); color:#ffc107; font-size:0.65rem;">Pending</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
