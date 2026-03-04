@extends('layouts.app')
@section('title', 'Inventory — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-box-seam me-2"></i>
                @if($userDepartment)
                    {{ ucfirst($userDepartment) }} Catalog
                @else
                    Inventory Catalog
                @endif
            </h1>
            <p class="page-subtitle">Browse and manage inventory items</p>
        </div>
        @if(Auth::user()->hasRole('Owner'))
            <a href="{{ route('inventory.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Item</a>
        @endif
    </div>

    {{-- Filters --}}
    <div class="glass-card mb-4">
        <form method="GET" action="{{ route('inventory.index') }}" class="row g-2 align-items-end">
            @if(!$userDepartment)
            <div class="col-md-2">
                <label class="form-label small mb-1">Department</label>
                <select name="department" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <option value="pharmacy" {{ ($filters['department'] ?? '') === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                    <option value="laboratory" {{ ($filters['department'] ?? '') === 'laboratory' ? 'selected' : '' }}>Laboratory</option>
                    <option value="radiology" {{ ($filters['department'] ?? '') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                </select>
            </div>
            @endif
            <div class="col-md-3">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, SKU, formula...">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary">Search</button>
                <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    @if($items->count() > 0)
        <div class="glass-card fade-in delay-1">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-th">Name</th>
                            @if(!$userDepartment)
                            <th class="sortable-th">Department</th>
                            @endif
                            <th class="sortable-th">SKU</th>
                            <th class="sortable-th">Unit</th>
                            <th class="sortable-th text-end">Stock</th>
                            <th class="sortable-th text-end">Min Level</th>
                            @if(Auth::user()->hasRole('Owner'))
                            <th class="sortable-th text-end">Purchase {{ currency_symbol() }}</th>
                            @endif
                            <th class="sortable-th text-end">Selling {{ currency_symbol() }}</th>
                            <th class="sortable-th text-center">Status</th>
                            @if(Auth::user()->hasRole('Owner'))
                            <th>Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>
                                    <span class="fw-medium">{{ $item->name }}</span>
                                    @if($item->chemical_formula)
                                        <br><small style="color:var(--text-muted);">{{ $item->chemical_formula }}</small>
                                    @endif
                                    @if($item->requires_prescription)
                                        <span class="badge-glass ms-1" style="background:rgba(var(--accent-warning-rgb),0.18);color:var(--accent-warning);font-size:0.65rem;">Rx</span>
                                    @endif
                                </td>
                                @if(!$userDepartment)
                                <td><span class="badge-glass">{{ ucfirst($item->department) }}</span></td>
                                @endif
                                <td style="color:var(--text-muted);">{{ $item->sku ?? '—' }}</td>
                                <td>{{ $item->unit }}</td>
                                <td class="text-end">
                                    @if($item->current_stock <= $item->minimum_stock_level)
                                        <span style="color:var(--accent-danger);" class="fw-bold">{{ $item->current_stock }}</span>
                                    @else
                                        <span class="fw-medium">{{ $item->current_stock }}</span>
                                    @endif
                                </td>
                                <td class="text-end" style="color:var(--text-muted);">{{ $item->minimum_stock_level }}</td>
                                @if(Auth::user()->hasRole('Owner'))
                                <td class="text-end">{{ currency($item->purchase_price) }}</td>
                                @endif
                                <td class="text-end">{{ currency($item->selling_price) }}</td>
                                <td class="text-center">
                                    @if($item->is_active)
                                        <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.18);color:var(--accent-success);">Active</span>
                                    @else
                                        <span class="badge-glass">Inactive</span>
                                    @endif
                                </td>
                                @if(Auth::user()->hasRole('Owner'))
                                <td>
                                    <a href="{{ route('inventory.edit', $item) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $items->links() }}
            </div>
        </div>
    @else
        <div class="empty-state fade-in delay-1">
            <i class="bi bi-box-seam" style="font-size:2.5rem;opacity:0.3;"></i>
            <h6 class="mt-3 mb-1">No inventory items found</h6>
            <p class="small mb-0" style="color:var(--text-muted);">
                @if(Auth::user()->hasRole('Owner'))
                    Add your first inventory item to get started.
                @else
                    No items available for your department.
                @endif
            </p>
        </div>
    @endif
</div>
@endsection
