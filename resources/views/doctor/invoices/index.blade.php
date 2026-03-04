@extends('layouts.app')
@section('title', 'My Invoices — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-receipt me-2"></i>My Invoices</h1>
            <p class="page-subtitle">Invoices attributed to you as prescribing doctor</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="glass-card mb-4">
        <form method="GET" action="{{ route('doctor.invoices.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="in_progress" {{ ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="paid" {{ ($filters['status'] ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="cancelled" {{ ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Department</label>
                <select name="department" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <option value="consultation" {{ ($filters['department'] ?? '') === 'consultation' ? 'selected' : '' }}>Consultation</option>
                    <option value="lab" {{ ($filters['department'] ?? '') === 'lab' ? 'selected' : '' }}>Laboratory</option>
                    <option value="radiology" {{ ($filters['department'] ?? '') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                    <option value="pharmacy" {{ ($filters['department'] ?? '') === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
                <a href="{{ route('doctor.invoices.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    {{-- Invoices Table --}}
    @if($invoices->count() > 0)
    <div class="glass-card fade-in delay-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td class="fw-medium">#{{ $invoice->id }}</td>
                            <td style="color:var(--text-secondary);">{{ $invoice->created_at->format('M d, Y') }}</td>
                            <td>{{ $invoice->patient?->full_name ?? '—' }}</td>
                            <td><span class="badge-glass">{{ ucfirst($invoice->department) }}</span></td>
                            <td>
                                @if($invoice->status === 'paid')
                                    <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.18);color:var(--accent-success);">Paid</span>
                                @elseif($invoice->status === 'completed')
                                    <span class="badge-glass" style="background:rgba(var(--accent-info-rgb),0.18);color:var(--accent-info);">Completed</span>
                                @elseif($invoice->status === 'in_progress')
                                    <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.18);color:var(--accent-warning);">In Progress</span>
                                @elseif($invoice->status === 'cancelled')
                                    <span class="badge-glass">Cancelled</span>
                                @else
                                    <span class="badge-glass">Pending</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('doctor.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
            {{ $invoices->links() }}
        </div>
    </div>
    @else
    <div class="empty-state fade-in delay-1">
        <i class="bi bi-receipt" style="font-size:2.5rem;opacity:0.3;"></i>
        <h6 class="mt-3 mb-1">No invoices found</h6>
        <p class="small mb-0" style="color:var(--text-muted);">Invoices will appear here when they are created with you as the prescribing doctor.</p>
    </div>
    @endif
</div>
@endsection
