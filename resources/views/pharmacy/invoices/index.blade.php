@extends('layouts.app')
@section('title', 'Pharmacy Orders — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-capsule me-2" style="color:var(--accent-success);"></i>Pharmacy Orders</h2>
            <p class="page-subtitle mb-0">Review and complete medication dispensing orders</p>
        </div>
        <div class="search-glass-wrapper" style="min-width:220px;">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="tableSearch" class="form-control form-control-sm search-glass" placeholder="Search orders..." aria-label="Search orders">
        </div>
    </div>

    @if($invoices->count() > 0)
    <div class="card fade-in delay-1">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul me-2" style="color:var(--accent-info);"></i>Orders</span>
            <span class="badge-glass" style="background:rgba(var(--accent-primary-rgb),0.15);color:var(--accent-primary);">{{ $invoices->total() }} order(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th">Order ID</th>
                            <th class="sortable-th">Patient</th>
                            <th class="sortable-th">Medication/Item</th>
                            <th class="sortable-th">Amount</th>
                            <th class="sortable-th">Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr>
                                <td class="fw-medium">#{{ $invoice->id }}</td>
                                <td style="color:var(--text-secondary);">{{ $invoice->patient?->full_name ?? 'Unknown' }}</td>
                                <td style="color:var(--text-secondary);">{{ $invoice->service_name ?? 'Medication N/A' }}</td>
                                <td class="fw-medium">{{ currency($invoice->total_amount ?? 0) }}</td>
                                <td>
                                    @php
                                        $sStyle = match($invoice->status) {
                                            'completed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                                            'paid' => 'background:rgba(var(--accent-primary-rgb),0.15);color:var(--accent-primary);',
                                            'cancelled' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                                            default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                                        };
                                    @endphp
                                    <span class="badge-glass" style="{{ $sStyle }}">{{ ucfirst($invoice->status ?? 'pending') }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('pharmacy.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye me-1"></i>View</a>
                                    @if($invoice->status === 'pending')
                                        <a href="{{ route('pharmacy.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-cash me-1"></i>Collect</a>
                                    @elseif($invoice->isPaid() && !$invoice->performed_by_user_id)
                                        <a href="{{ route('pharmacy.invoices.show', $invoice) }}" class="btn btn-sm btn-success"><i class="bi bi-play-circle me-1"></i>Start</a>
                                    @elseif($invoice->isPaid() && $invoice->performed_by_user_id && !$invoice->items->count())
                                        <a href="{{ route('pharmacy.invoices.show', $invoice) }}" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Dispense</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-center">
            {{ $invoices->links() }}
        </div>
    </div>
    @else
    <div class="card fade-in delay-1">
        <div class="card-body">
            <div class="empty-state py-5">
                <i class="bi bi-capsule"></i>
                <h5>No medication orders</h5>
                <p class="mb-0">All orders are fulfilled or none assigned yet.</p>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var s = document.getElementById('tableSearch');
    if (s) s.addEventListener('input', function() {
        var q = this.value.toLowerCase();
        document.querySelectorAll('table tbody tr').forEach(function(r) { r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none'; });
    });
});
</script>
@endpush
@endsection
