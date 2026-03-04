@extends('layouts.app')

@section('title', 'Revenue Ledger')

@section('content')
<div class="fade-in">
    <div class="mb-4">
        <h1 class="page-header"><i class="bi bi-journal-text me-2"></i>Revenue Ledger</h1>
        <p class="page-subtitle">Browse revenue distribution entries across all invoices</p>
    </div>

    {{-- Filters --}}
    <div class="glass-card fade-in delay-1 mb-4">
        <form method="GET" action="{{ route('owner.revenue-ledger.index') }}" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted"><i class="bi bi-calendar-event me-1"></i>From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted"><i class="bi bi-calendar-event me-1"></i>To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted"><i class="bi bi-building me-1"></i>Department</label>
                <select name="department" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <option value="lab" {{ ($filters['department'] ?? '') === 'lab' ? 'selected' : '' }}>Laboratory</option>
                    <option value="radiology" {{ ($filters['department'] ?? '') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                    <option value="pharmacy" {{ ($filters['department'] ?? '') === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                    <option value="consultation" {{ ($filters['department'] ?? '') === 'consultation' ? 'selected' : '' }}>Consultation</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted"><i class="bi bi-person-badge me-1"></i>Role</label>
                <select name="role_type" class="form-select form-select-sm">
                    <option value="">All Roles</option>
                    <option value="Owner" {{ ($filters['role_type'] ?? '') === 'Owner' ? 'selected' : '' }}>Owner</option>
                    <option value="Doctor" {{ ($filters['role_type'] ?? '') === 'Doctor' ? 'selected' : '' }}>Doctor</option>
                    <option value="Pharmacy" {{ ($filters['role_type'] ?? '') === 'Pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                    <option value="Laboratory" {{ ($filters['role_type'] ?? '') === 'Laboratory' ? 'selected' : '' }}>Laboratory</option>
                    <option value="Radiology" {{ ($filters['role_type'] ?? '') === 'Radiology' ? 'selected' : '' }}>Radiology</option>
                    <option value="Referrer" {{ ($filters['role_type'] ?? '') === 'Referrer' ? 'selected' : '' }}>Referrer</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted"><i class="bi bi-flag me-1"></i>Payout Status</label>
                <select name="payout_status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="unpaid" {{ ($filters['payout_status'] ?? '') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                    <option value="paid" {{ ($filters['payout_status'] ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('owner.revenue-ledger.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            </div>
        </form>
    </div>

    {{-- Summary stat --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="glass-stat hover-lift fade-in delay-2">
                <div class="stat-icon stat-icon-success"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="stat-value">{{ currency($totalAmount) }}</div>
                    <div class="stat-label">Filtered Total</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="glass-card fade-in delay-2">
        @if($entries->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th text-uppercase small text-muted">Date</th>
                            <th class="sortable-th text-uppercase small text-muted">Invoice #</th>
                            <th class="sortable-th text-uppercase small text-muted">Department</th>
                            <th class="sortable-th text-uppercase small text-muted">Role</th>
                            <th class="sortable-th text-uppercase small text-muted">Recipient</th>
                            <th class="sortable-th text-uppercase small text-muted text-end">Percentage</th>
                            <th class="sortable-th text-uppercase small text-muted text-end">Amount</th>
                            <th class="sortable-th text-uppercase small text-muted text-center">Rx</th>
                            <th class="sortable-th text-uppercase small text-muted">Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                            <tr>
                                <td class="text-muted"><i class="bi bi-calendar3 me-1"></i>{{ $entry->created_at->format('M d, Y') }}</td>
                                <td>
                                    @if($entry->invoice)
                                        <span class="fw-medium">#{{ $entry->invoice->id }}</span>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    @if($entry->invoice)
                                        <span class="badge-glass">{{ ucfirst($entry->invoice->department) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge-glass">{{ $entry->role_type }}</span>
                                </td>
                                <td><i class="bi bi-person me-1 text-muted"></i>{{ $entry->user?->name ?? '&mdash;' }}</td>
                                <td class="text-end">{{ number_format($entry->percentage, 2) }}%</td>
                                <td class="text-end fw-semibold">{{ currency($entry->amount) }}</td>
                                <td class="text-center">
                                    @if($entry->is_prescribed)
                                        <span class="badge-glass" style="background: rgba(255,193,7,0.2); color: #e6a800;"><i class="bi bi-capsule"></i> Rx</span>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    @if($entry->payout_id)
                                        <span class="badge-glass" style="background: rgba(25,135,84,0.15); color: #198754;"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                    @else
                                        <span class="badge-glass" style="background: rgba(255,193,7,0.2); color: #e6a800;"><i class="bi bi-clock me-1"></i>Unpaid</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $entries->links() }}
            </div>
        @else
            <div class="empty-state">
                <i class="bi bi-journal-x" style="font-size: 2.5rem;"></i>
                <h3 class="h6 fw-medium mt-2">No ledger entries found</h3>
                <p class="small text-muted mb-0">Revenue entries are created when invoices are marked as paid.</p>
            </div>
        @endif
    </div>
</div>
@endsection