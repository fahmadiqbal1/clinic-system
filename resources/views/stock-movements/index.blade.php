@extends('layouts.app')
@section('title', 'Stock Movements — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header fade-in mb-4">
        <h2 class="h4 fw-bold mb-1">
            <i class="bi bi-arrow-left-right me-2" style="color:var(--accent-info);"></i>
            @if($userDepartment)
                {{ ucfirst($userDepartment) }} Stock Movements
            @else
                Stock Movement History
            @endif
        </h2>
        <p class="page-subtitle">Track all inventory stock changes</p>
    </div>

    <div class="glass-card fade-in delay-1">
        <!-- Filters -->
        <form method="GET" action="{{ route('stock-movements.index') }}" class="row g-2 mb-4 px-3 pt-3">
            @if(!$userDepartment)
            <div class="col-md-2">
                <select name="department" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <option value="pharmacy" {{ ($filters['department'] ?? '') === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                    <option value="laboratory" {{ ($filters['department'] ?? '') === 'laboratory' ? 'selected' : '' }}>Laboratory</option>
                    <option value="radiology" {{ ($filters['department'] ?? '') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <select name="item_id" class="form-select form-select-sm">
                    <option value="">All Items</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" {{ ($filters['item_id'] ?? '') == $item->id ? 'selected' : '' }}>
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="purchase" {{ ($filters['type'] ?? '') === 'purchase' ? 'selected' : '' }}>Purchase</option>
                    <option value="dispense" {{ ($filters['type'] ?? '') === 'dispense' ? 'selected' : '' }}>Dispense</option>
                    <option value="adjustment" {{ ($filters['type'] ?? '') === 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('stock-movements.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Reset</a>
            </div>
        </form>

        @if($movements->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th text-uppercase small" style="color:var(--text-muted);">Date</th>
                            <th class="sortable-th text-uppercase small" style="color:var(--text-muted);">Item</th>
                            @if(!$userDepartment)
                            <th class="sortable-th text-uppercase small" style="color:var(--text-muted);">Department</th>
                            @endif
                            <th class="sortable-th text-uppercase small" style="color:var(--text-muted);">Type</th>
                            <th class="sortable-th text-uppercase small text-end" style="color:var(--text-muted);">Quantity</th>
                            <th class="sortable-th text-uppercase small" style="color:var(--text-muted);">Source</th>
                            <th class="sortable-th text-uppercase small" style="color:var(--text-muted);">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($movements as $movement)
                            <tr>
                                <td style="color:var(--text-muted);">{{ $movement->created_at->format('M d, Y H:i') }}</td>
                                <td class="fw-medium">{{ $movement->inventoryItem->name ?? '—' }}</td>
                                @if(!$userDepartment)
                                <td>
                                    <span class="badge badge-glass-primary">{{ ucfirst($movement->inventoryItem->department ?? '') }}</span>
                                </td>
                                @endif
                                <td>
                                    @if($movement->type === 'purchase')
                                        <span class="badge badge-glass-success">Purchase</span>
                                    @elseif($movement->type === 'dispense')
                                        <span class="badge badge-glass-danger">Dispense</span>
                                    @else
                                        <span class="badge badge-glass-warning">Adjustment</span>
                                    @endif
                                </td>
                                <td class="text-end fw-medium">
                                    @if($movement->quantity > 0)
                                        <span style="color:var(--accent-success);">+{{ $movement->quantity }}</span>
                                    @else
                                        <span style="color:var(--accent-danger);">{{ $movement->quantity }}</span>
                                    @endif
                                </td>
                                <td class="small" style="color:var(--text-muted);">
                                    @if($movement->reference_type)
                                        {{ class_basename($movement->reference_type) }} <span class="code-tag">#{{ $movement->reference_id }}</span>
                                    @else
                                        Manual
                                    @endif
                                </td>
                                <td style="color:var(--text-muted);">{{ $movement->creator?->name ?? 'System' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3 pb-3">
                {{ $movements->links() }}
            </div>
        @else
            <div class="text-center py-5">
                <i class="bi bi-arrow-left-right" style="font-size:3rem; color:var(--text-muted);"></i>
                <h3 class="h6 fw-medium mt-3">No stock movements found</h3>
                <p class="small" style="color:var(--text-muted);">Stock movements are recorded when items are purchased, dispensed, or adjusted.</p>
            </div>
        @endif
    </div>
</div>
@endsection
