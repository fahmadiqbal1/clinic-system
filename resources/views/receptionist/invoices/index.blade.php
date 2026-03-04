@extends('layouts.app')
@section('title', 'Invoices — ' . config('app.name'))

@section('content')
<div class="container py-4">
    {{-- Page Header --}}
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-receipt me-2"></i>Invoices</h1>
            <p class="text-muted mb-0">Manage patient invoices and payments</p>
        </div>
        <a href="{{ route('receptionist.invoices.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Create Invoice
        </a>
    </div>

    @if($invoices->count() > 0)
        <div class="glass-panel p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="sortable-th" style="width:90px;">Invoice</th>
                            <th class="sortable-th">Patient</th>
                            <th class="sortable-th">Department</th>
                            <th class="sortable-th d-none d-md-table-cell">Service</th>
                            <th class="sortable-th">Amount</th>
                            <th class="sortable-th">Status</th>
                            <th style="width:80px;" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr>
                                <td><span class="code-tag">#{{ $invoice->id }}</span></td>
                                <td class="fw-medium">{{ $invoice->patient?->full_name ?? 'Unknown' }}</td>
                                <td>
                                    @php
                                        $deptIcon = match($invoice->department ?? '') {
                                            'lab' => 'bi-flask',
                                            'radiology' => 'bi-radioactive',
                                            'pharmacy' => 'bi-capsule',
                                            'consultation' => 'bi-bandaid',
                                            default => 'bi-building',
                                        };
                                    @endphp
                                    <span class="badge-glass"><i class="bi {{ $deptIcon }} me-1"></i>{{
                                        match($invoice->department ?? '') {
                                            'lab' => 'Laboratory',
                                            'radiology' => 'Radiology',
                                            'pharmacy' => 'Pharmacy',
                                            'consultation' => 'Consultation',
                                            default => ucfirst($invoice->department ?? 'N/A')
                                        }
                                    }}</span>
                                </td>
                                <td class="d-none d-md-table-cell text-muted">{{ $invoice->service_name ?? '—' }}</td>
                                <td><span class="price-display">{{ currency($invoice->total_amount ?? 0) }}</span></td>
                                <td>
                                    @php
                                        $statusStyle = match($invoice->status) {
                                            'completed' => 'text-success',
                                            'paid'      => 'text-info',
                                            'cancelled' => 'text-danger',
                                            default     => 'text-warning',
                                        };
                                        $statusIcon = match($invoice->status) {
                                            'completed' => 'bi-check-circle-fill',
                                            'paid'      => 'bi-credit-card-fill',
                                            'cancelled' => 'bi-x-circle-fill',
                                            default     => 'bi-hourglass-split',
                                        };
                                    @endphp
                                    <span class="{{ $statusStyle }}"><i class="bi {{ $statusIcon }} me-1"></i>{{ ucfirst($invoice->status ?? 'pending') }}</span>
                                    @if(($invoice->discount_status ?? 'none') === 'pending')
                                        <br><small class="text-warning"><i class="bi bi-tag me-1"></i>Discount Pending</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('receptionist.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary" title="View details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $invoices->links() }}
        </div>
    @else
        <div class="glass-panel text-center py-5">
            <i class="bi bi-receipt-cutoff" style="font-size:3rem;opacity:0.3;"></i>
            <h5 class="mt-3">No Invoices Yet</h5>
            <p class="text-muted mb-3">Get started by creating a new invoice.</p>
            <a href="{{ route('receptionist.invoices.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Create Invoice
            </a>
        </div>
    @endif
</div>
@endsection
