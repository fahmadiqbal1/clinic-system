@extends('layouts.app')
@section('title', 'Payout Details — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Print Header --}}
    <div class="print-header">
        <h2>{{ config('app.name') }}</h2>
        <p>Payout #{{ $payout->id }} &mdash; {{ $payout->created_at?->format('M d, Y') }}</p>
    </div>

    <div class="glass-card fade-in">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3" style="border-bottom:1px solid var(--glass-border);">
            <div>
                <h2 class="h4 fw-bold mb-1"><i class="bi bi-wallet2 me-2" style="color:var(--accent-warning);"></i>Payout #{{ $payout->id }}</h2>
                <p class="page-subtitle mb-0">Staff Payout Details</p>
            </div>
            <div class="d-flex gap-2 no-print">
                <button onclick="window.print()" class="btn btn-outline-info btn-sm" data-no-disable="true"><i class="bi bi-printer me-1"></i>Print</button>
                <a href="{{ route('reception.payouts.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Payouts</a>
            </div>
        </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <!-- Status & Staff Member -->
            <div class="d-flex align-items-center justify-content-between pb-4 mb-4" style="border-bottom:1px solid var(--glass-border);">
                <div>
                    <p class="small mb-1" style="color:var(--text-muted);">Staff Member</p>
                    <p class="fs-5 fw-semibold mb-0">{{ $payout->doctor?->name ?? 'Unknown' }}</p>
                </div>
                <div>
                    <p class="small mb-1" style="color:var(--text-muted);">Type</p>
                    <p class="fs-5 fw-semibold mb-0">
                        @if($payout->payout_type === 'monthly')
                            <span class="badge-glass badge-glass-info">Monthly (Salary + Commission)</span>
                        @else
                            <span class="badge-glass badge-glass-primary">Daily Commission</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="small mb-1" style="color:var(--text-muted);">Status</p>
                    <p class="fs-5 fw-semibold mb-0">
                        <span class="badge {{ $payout->status === 'confirmed' ? 'badge-glass-success' : 'badge-glass-warning' }}">
                            {{ ucfirst($payout->status) }}
                        </span>
                        @if($payout->approval_status)
                            <span class="badge ms-1 @if($payout->approval_status === 'approved') badge-glass-success @elseif($payout->approval_status === 'rejected') badge-glass-danger @else badge-glass-warning @endif">
                                Approval: {{ ucfirst($payout->approval_status) }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            <!-- Period & Amounts -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <p class="small mb-1" style="color:var(--text-muted);">Period Start</p>
                    <p class="fs-5 fw-semibold">{{ $payout->period_start?->format('M d, Y') ?? 'N/A' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="small mb-1" style="color:var(--text-muted);">Period End</p>
                    <p class="fs-5 fw-semibold">{{ $payout->period_end?->format('M d, Y') ?? 'N/A' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="small mb-1" style="color:var(--text-muted);">Total Amount</p>
                    <p class="h4 fw-bold" style="color:var(--accent-info);">{{ number_format($payout->total_amount, 2) }}</p>
                </div>
                <div class="col-md-3">
                    <p class="small mb-1" style="color:var(--text-muted);">Paid Amount</p>
                    <p class="h4 fw-bold" style="color:var(--accent-success);">{{ number_format($payout->paid_amount, 2) }}</p>
                </div>
            </div>

            @if($payout->payout_type === 'monthly')
            <div class="row mb-4">
                <div class="col-md-4">
                    <p class="small mb-1" style="color:var(--text-muted);">Salary Component</p>
                    <p class="fs-5 fw-semibold" style="color:var(--accent-primary);">{{ number_format($payout->salary_amount, 2) }}</p>
                </div>
                <div class="col-md-4">
                    <p class="small mb-1" style="color:var(--text-muted);">Commission Component</p>
                    <p class="fs-5 fw-semibold" style="color:var(--accent-warning);">{{ number_format($payout->total_amount - $payout->salary_amount, 2) }}</p>
                </div>
            </div>
            @endif

            <!-- Outstanding Balance -->
            @if($payout->outstanding_balance > 0)
                <div class="alert alert-warning">
                    <strong>Outstanding Balance:</strong> {{ number_format($payout->outstanding_balance, 2) }}
                </div>
            @endif

            <!-- Created & Confirmed & Approved Info -->
            <div class="row mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                <div class="col-md-3">
                    <p class="small mb-1" style="color:var(--text-muted);">Created By</p>
                    <p class="fw-semibold">{{ $payout->creator?->name ?? 'N/A' }}</p>
                </div>
                @if($payout->approver)
                    <div class="col-md-3">
                        <p class="small mb-1" style="color:var(--text-muted);">{{ $payout->approval_status === 'approved' ? 'Approved' : 'Reviewed' }} By</p>
                        <p class="fw-semibold">{{ $payout->approver->name }}</p>
                    </div>
                    <div class="col-md-3">
                        <p class="small mb-1" style="color:var(--text-muted);">{{ $payout->approval_status === 'approved' ? 'Approved' : 'Reviewed' }} At</p>
                        <p class="fw-semibold">{{ $payout->approved_at?->format('M d, Y H:i') }}</p>
                    </div>
                @endif
                @if($payout->confirmer)
                    <div class="col-md-3">
                        <p class="small mb-1" style="color:var(--text-muted);">Confirmed By</p>
                        <p class="fw-semibold">{{ $payout->confirmer->name }}</p>
                    </div>
                @endif
            </div>
            @if($payout->confirmed_at)
                <div class="row mb-4">
                    <div class="col-md-3">
                        <p class="small mb-1" style="color:var(--text-muted);">Confirmed At</p>
                        <p class="fw-semibold">{{ $payout->confirmed_at?->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            @endif

            <!-- Correction Info -->
            @if($payout->correction_of_id)
                <div class="alert" style="background:rgba(var(--accent-info-rgb),0.15); border:1px solid rgba(var(--accent-info-rgb),0.3); color:var(--text-primary);">
                    This is a correction of Payout #{{ $payout->correction_of_id }}.
                </div>
            @endif

            <!-- Revenue Ledger Entries -->
            @if($payout->revenueLedgers && $payout->revenueLedgers->count() > 0)
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    <h5 class="fw-bold mb-3">Associated Revenue Entries</h5>
                    <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Role Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payout->revenueLedgers as $entry)
                                <tr>
                                    <td>#{{ $entry->invoice_id }}</td>
                                    <td>{{ $entry->role_type }}</td>
                                    <td class="fw-semibold" style="color:var(--accent-success);">{{ number_format($entry->amount, 2) }}</td>
                                    <td style="color:var(--text-muted);">{{ $entry->created_at?->format('M d, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            @endif

            <!-- Actions -->
            <div class="d-flex gap-2 pt-4 no-print" style="border-top:1px solid var(--glass-border);">
                {{-- Owner: Approve/Reject monthly payouts awaiting approval --}}
                @if($payout->needsApproval() && $payout->approval_status === 'pending' && auth()->user()->hasRole('Owner'))
                    <form action="{{ route('payouts.approve', $payout) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success" onclick="return confirm('Approve this payout?')"><i class="bi bi-check-lg me-1"></i>Approve</button>
                    </form>
                    <form action="{{ route('payouts.reject', $payout) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this payout? Commission entries will be released.')"><i class="bi bi-x-lg me-1"></i>Reject</button>
                    </form>
                @endif

                {{-- Staff: Confirm their own payout (doctors always, others after approval) --}}
                @if($payout->canBeConfirmed() && auth()->user()->id === $payout->doctor_id)
                    <form action="{{ route('payouts.confirm', $payout) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success" onclick="return confirm('Confirm receipt of this payout?')"><i class="bi bi-check-circle me-1"></i>Confirm Received</button>
                    </form>
                @endif

                {{-- Owner: Create correction on confirmed payouts --}}
                @if($payout->status === 'confirmed' && auth()->user()->hasRole('Owner'))
                    <a href="{{ route('owner.payouts.correction-create', $payout) }}" class="btn btn-warning"><i class="bi bi-pencil-square me-1"></i>Create Correction</a>
                @endif

                <a href="{{ route('reception.payouts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
            </div>
    </div>
</div>
@endsection
