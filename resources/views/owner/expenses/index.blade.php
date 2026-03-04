@extends('layouts.app')

@section('title', 'Expense Management')

@section('content')
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="page-header"><i class="bi bi-receipt me-2"></i>Expense Management</h1>
            <p class="page-subtitle">Track and manage clinic expenses</p>
        </div>
        <a href="{{ route('owner.expenses.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Expense
        </a>
    </div>

    {{-- Filters --}}
    <div class="glass-card fade-in delay-1 mb-4">
        <form method="GET" action="{{ route('owner.expenses.index') }}" class="row g-3 align-items-end">
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
                    <option value="general" {{ ($filters['department'] ?? '') === 'general' ? 'selected' : '' }}>General</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted"><i class="bi bi-funnel me-1"></i>Source</label>
                <select name="source" class="form-select form-select-sm">
                    <option value="">All Sources</option>
                    <option value="manual" {{ ($filters['source'] ?? '') === 'manual' ? 'selected' : '' }}>Manual</option>
                    <option value="procurement" {{ ($filters['source'] ?? '') === 'procurement' ? 'selected' : '' }}>Procurement</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted"><i class="bi bi-tag me-1"></i>Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <option value="fixed" {{ ($filters['category'] ?? '') === 'fixed' ? 'selected' : '' }}>Fixed</option>
                    <option value="variable" {{ ($filters['category'] ?? '') === 'variable' ? 'selected' : '' }}>Variable</option>
                    <option value="procurement" {{ ($filters['category'] ?? '') === 'procurement' ? 'selected' : '' }}>Procurement</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('owner.expenses.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            </div>
        </form>
    </div>

    {{-- Summary stat --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="glass-stat hover-lift fade-in delay-2">
                <div class="stat-icon stat-icon-danger"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="stat-value">{{ currency($totalFiltered) }}</div>
                    <div class="stat-label">Filtered Total</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="glass-card fade-in delay-2">
        @if($expenses->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th text-uppercase small text-muted">Date</th>
                            <th class="sortable-th text-uppercase small text-muted">Department</th>
                            <th class="sortable-th text-uppercase small text-muted">Description</th>
                            <th class="sortable-th text-uppercase small text-muted text-end">Cost</th>
                            <th class="sortable-th text-uppercase small text-muted">Category</th>
                            <th class="sortable-th text-uppercase small text-muted">Source</th>
                            <th class="sortable-th text-uppercase small text-muted">Created By</th>
                            <th class="text-uppercase small text-muted">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expenses as $expense)
                            <tr>
                                <td class="text-muted"><i class="bi bi-calendar3 me-1"></i>{{ $expense->created_at->format('M d, Y') }}</td>
                                <td>
                                    <span class="badge-glass">{{ ucfirst($expense->department) }}</span>
                                </td>
                                <td>{{ Str::limit($expense->description, 60) }}</td>
                                <td class="text-end fw-semibold">{{ currency($expense->cost) }}</td>
                                <td>
                                    @if($expense->category === 'fixed')
                                        <span class="badge-glass" style="background: rgba(129,140,248,0.15); color: #818cf8;"><i class="bi bi-pin-angle me-1"></i>Fixed</span>
                                    @elseif($expense->category === 'procurement')
                                        <span class="badge-glass" style="background: rgba(13,202,240,0.15); color: #0dcaf0;"><i class="bi bi-box-seam me-1"></i>Procurement</span>
                                    @else
                                        <span class="badge-glass"><i class="bi bi-shuffle me-1"></i>Variable</span>
                                    @endif
                                </td>
                                <td>
                                    @if($expense->invoice_id)
                                        <span class="badge-glass" style="background: rgba(13,202,240,0.15); color: #0dcaf0;"><i class="bi bi-box-seam me-1"></i>Procurement</span>
                                    @else
                                        <span class="badge-glass"><i class="bi bi-pencil me-1"></i>Manual</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $expense->creator?->name ?? 'System' }}</td>
                                <td>
                                    @if(!$expense->invoice_id)
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('owner.expenses.edit', $expense) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('owner.expenses.destroy', $expense) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Delete this expense?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $expenses->links() }}
            </div>
        @else
            <div class="empty-state">
                <i class="bi bi-receipt-cutoff" style="font-size: 2.5rem;"></i>
                <h3 class="h6 fw-medium mt-2">No expenses found</h3>
                <p class="small text-muted mb-0">Try adjusting your filters or add a new expense.</p>
            </div>
        @endif
    </div>
</div>
@endsection