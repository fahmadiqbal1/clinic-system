@extends('layouts.app')

@section('title', 'Discount Approvals')

@section('content')
<div class="fade-in">
    <div class="mb-4">
        <h1 class="page-header"><i class="bi bi-tag me-2"></i>Discount Approvals</h1>
        <p class="page-subtitle">Review and approve/reject staff discount requests</p>
    </div>

    {{-- Pending Discount Requests --}}
    <div class="glass-card fade-in delay-1 mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Pending Requests</h5>
            @if($pendingDiscounts->count() > 0)
                <span class="badge-glass" style="background: rgba(255,193,7,0.2); color: #e6a800;">{{ $pendingDiscounts->total() }}</span>
            @endif
        </div>

        @if($pendingDiscounts->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-uppercase small text-muted">Invoice</th>
                            <th class="text-uppercase small text-muted">Patient</th>
                            <th class="text-uppercase small text-muted">Department</th>
                            <th class="text-uppercase small text-muted text-end">Total</th>
                            <th class="text-uppercase small text-muted text-end">Discount</th>
                            <th class="text-uppercase small text-muted">Requested By</th>
                            <th class="text-uppercase small text-muted">Reason</th>
                            <th class="text-uppercase small text-muted">Requested At</th>
                            <th class="text-uppercase small text-muted">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingDiscounts as $invoice)
                            <tr>
                                <td class="fw-medium">#{{ $invoice->id }}</td>
                                <td><i class="bi bi-person me-1 text-muted"></i>{{ $invoice->patient?->full_name ?? 'Unknown' }}</td>
                                <td>
                                    <span class="badge-glass">{{ ucfirst($invoice->department) }}</span>
                                </td>
                                <td class="text-end">{{ currency($invoice->total_amount) }}</td>
                                <td class="text-end fw-bold text-danger">{{ currency($invoice->discount_amount) }}</td>
                                <td>{{ $invoice->discountRequester?->name ?? 'N/A' }}</td>
                                <td class="text-muted">{{ $invoice->discount_reason ?? '-' }}</td>
                                <td class="text-muted">{{ $invoice->discount_requested_at?->format('M d, H:i') }}</td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <form action="{{ route('invoices.discount.approve', $invoice) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve discount of {{ currency($invoice->discount_amount) }}?')">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('invoices.discount.reject', $invoice) }}" method="POST" class="d-flex gap-1">
                                            @csrf
                                            <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Reason" style="width: 120px;">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this discount request?')">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $pendingDiscounts->links() }}
            </div>
        @else
            <div class="empty-state">
                <i class="bi bi-check-circle" style="font-size: 2.5rem; color: rgba(25,135,84,0.5);"></i>
                <p class="mt-2 mb-0">No pending discount requests. All clear!</p>
            </div>
        @endif
    </div>

    {{-- Recently Processed --}}
    @if($recentlyProcessed->count() > 0)
        <div class="glass-card fade-in delay-2">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-muted"></i>Discount History</h5>
                <small class="text-muted">{{ $recentlyProcessed->total() }} total &mdash; use for financial statements</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle table-sm mb-0">
                    <thead>
                        <tr>
                            <th class="text-uppercase small text-muted">Invoice</th>
                            <th class="text-uppercase small text-muted">Patient</th>
                            <th class="text-uppercase small text-muted">Department</th>
                            <th class="text-uppercase small text-muted text-end">Amount</th>
                            <th class="text-uppercase small text-muted">Status</th>
                            <th class="text-uppercase small text-muted">Requested By</th>
                            <th class="text-uppercase small text-muted">Processed By</th>
                            <th class="text-uppercase small text-muted">Processed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentlyProcessed as $invoice)
                            <tr>
                                <td class="fw-medium">#{{ $invoice->id }}</td>
                                <td><i class="bi bi-person me-1 text-muted"></i>{{ $invoice->patient?->full_name ?? 'Unknown' }}</td>
                                <td><span class="badge-glass">{{ ucfirst($invoice->department) }}</span></td>
                                <td class="text-end">{{ currency($invoice->discount_amount) }}</td>
                                <td>
                                    @if($invoice->discount_status === 'approved')
                                        <span class="badge-glass" style="background: rgba(25,135,84,0.15); color: #198754;">
                                            <i class="bi bi-check-circle me-1"></i>Approved
                                        </span>
                                    @else
                                        <span class="badge-glass" style="background: rgba(220,53,69,0.15); color: #dc3545;">
                                            <i class="bi bi-x-circle me-1"></i>Rejected
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $invoice->discountRequester?->name ?? 'N/A' }}</td>
                                <td>{{ $invoice->discountApprover?->name ?? 'N/A' }}</td>
                                <td class="text-muted">{{ $invoice->discount_approved_at?->format('M d, H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $recentlyProcessed->withQueryString()->links() }}</div>
        </div>
    @endif
</div>
@endsection