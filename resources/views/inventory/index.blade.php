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
        <div class="d-flex gap-2">
            <a href="{{ route('inventory.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Item</a>
        </div>
    </div>

    {{-- Stats Bar --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="glass-card text-center py-3">
                <div style="font-size:1.5rem;font-weight:700;color:var(--accent-primary);">{{ $stats['total'] }}</div>
                <small style="color:var(--text-muted);">Total Items</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-card text-center py-3">
                <div style="font-size:1.5rem;font-weight:700;color:var(--accent-success);">{{ $stats['active'] }}</div>
                <small style="color:var(--text-muted);">Active</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-card text-center py-3">
                <div style="font-size:1.5rem;font-weight:700;color:var(--accent-danger);">{{ $stats['low_stock'] }}</div>
                <small style="color:var(--text-muted);">Low Stock</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-card text-center py-3">
                @foreach($stats['departments'] as $dept => $count)
                    <span class="badge-glass me-1" style="font-size:0.7rem;">{{ ucfirst($dept) }}: {{ $count }}</span>
                @endforeach
                <br><small style="color:var(--text-muted);">By Department</small>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="glass-card mb-4">
        <div class="row g-2 align-items-end">
            @if(!$userDepartment)
            <div class="col-md-2">
                <label class="form-label small mb-1">Department</label>
                <select id="filterDepartment" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <option value="pharmacy" {{ ($filters['department'] ?? '') === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                    <option value="laboratory" {{ ($filters['department'] ?? '') === 'laboratory' ? 'selected' : '' }}>Laboratory</option>
                    <option value="radiology" {{ ($filters['department'] ?? '') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select id="filterStatus" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="rx" {{ ($filters['status'] ?? '') === 'rx' ? 'selected' : '' }}>Prescription Only</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Search</label>
                <input type="text" id="filterSearch" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="Name, SKU, barcode, formula...">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Sort</label>
                <select id="filterSort" class="form-select form-select-sm">
                    <option value="">Name A-Z</option>
                    <option value="name_desc" {{ ($filters['sort'] ?? '') === 'name_desc' ? 'selected' : '' }}>Name Z-A</option>
                    <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>Price Low-High</option>
                    <option value="price_desc" {{ ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>Price High-Low</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-1">
                <button id="btnResetFilters" class="btn btn-sm btn-outline-secondary w-100">Reset</button>
            </div>
        </div>
    </div>

    {{-- Barcode Scanner Quick Lookup --}}
    <div class="glass-card mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="mb-0"><i class="bi bi-upc-scan me-2"></i>Quick Barcode Lookup</h6>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#scannerCollapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="collapse" id="scannerCollapse">
            <x-barcode-scanner id="inventory-lookup" modes="usb,camera" placeholder="Scan barcode to find item..." />
            <div id="scanResult" class="mt-2" style="display:none;"></div>
        </div>
    </div>

    {{-- Table --}}
    <div id="inventoryTableContainer">
        @include('inventory._table', ['items' => $items, 'userDepartment' => $userDepartment])
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let debounceTimer;
    const container = document.getElementById('inventoryTableContainer');
    const searchInput = document.getElementById('filterSearch');
    const statusSelect = document.getElementById('filterStatus');
    const sortSelect = document.getElementById('filterSort');
    const deptSelect = document.getElementById('filterDepartment');

    function fetchItems() {
        const params = new URLSearchParams();
        if (searchInput && searchInput.value) params.set('search', searchInput.value);
        if (statusSelect && statusSelect.value) params.set('status', statusSelect.value);
        if (sortSelect && sortSelect.value) params.set('sort', sortSelect.value);
        if (deptSelect && deptSelect.value) params.set('department', deptSelect.value);

        const url = '{{ route("inventory.index") }}?' + params.toString();
        window.history.replaceState({}, '', url);

        axios.get(url, { headers: { 'Accept': 'application/json' } })
            .then(function(response) {
                container.innerHTML = response.data.html;
            })
            .catch(function() {
                window.location.href = url;
            });
    }

    function debouncedFetch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchItems, 350);
    }

    if (searchInput) searchInput.addEventListener('input', debouncedFetch);
    if (statusSelect) statusSelect.addEventListener('change', fetchItems);
    if (sortSelect) sortSelect.addEventListener('change', fetchItems);
    if (deptSelect) deptSelect.addEventListener('change', fetchItems);

    document.getElementById('btnResetFilters').addEventListener('click', function() {
        if (searchInput) searchInput.value = '';
        if (statusSelect) statusSelect.value = '';
        if (sortSelect) sortSelect.value = '';
        if (deptSelect) deptSelect.value = '';
        fetchItems();
    });

    // Barcode scanner lookup
    document.addEventListener('barcode-scanned', function(e) {
        if (e.detail.scannerId !== 'inventory-lookup') return;
        const resultDiv = document.getElementById('scanResult');
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm"></div> Looking up...</div>';

        axios.get('{{ route("inventory.barcode-lookup") }}', { params: { code: e.detail.code } })
            .then(function(response) {
                if (response.data.found) {
                    const item = response.data.item;
                    resultDiv.innerHTML = `
                        <div class="glass-card" style="background:rgba(var(--accent-success-rgb),0.08);border-color:var(--accent-success);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${item.name}</h6>
                                    <small style="color:var(--text-muted);">Barcode: ${item.barcode} | SKU: ${item.sku || '\u2014'} | ${item.department}</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">${item.current_stock} ${item.unit}</div>
                                    <small>Price: ${item.selling_price}</small>
                                </div>
                            </div>
                            <div class="mt-2">
                                <a href="/inventory/${item.id}/edit" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                                <a href="/inventory/${item.id}/adjust" class="btn btn-sm btn-outline-warning">Adjust Stock</a>
                            </div>
                        </div>`;
                } else {
                    resultDiv.innerHTML = `
                        <div class="glass-card" style="background:rgba(var(--accent-danger-rgb),0.08);border-color:var(--accent-danger);">
                            <i class="bi bi-x-circle me-1"></i>No item found with barcode <strong>${e.detail.code}</strong>
                            <a href="{{ route('inventory.create') }}?barcode=${encodeURIComponent(e.detail.code)}" class="btn btn-sm btn-outline-primary ms-2">Create New</a>
                        </div>`;
                }
            })
            .catch(function() {
                resultDiv.innerHTML = '<div class="text-danger">Error looking up barcode.</div>';
            });
    });

    // Inline quick-update (selling price)
    container.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-quick-price');
        if (!btn) return;
        const itemId = btn.dataset.itemId;
        const currentPrice = btn.dataset.currentPrice;
        const newPrice = prompt('New selling price:', currentPrice);
        if (newPrice === null || newPrice === currentPrice) return;
        if (isNaN(parseFloat(newPrice)) || parseFloat(newPrice) < 0) {
            alert('Invalid price');
            return;
        }

        axios.patch('/inventory/' + itemId + '/quick-update', { selling_price: parseFloat(newPrice) })
            .then(function() { fetchItems(); })
            .catch(function() { alert('Failed to update price.'); });
    });

    // Toggle active/inactive
    container.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-toggle-active');
        if (!btn) return;
        const itemId = btn.dataset.itemId;
        const newActive = btn.dataset.isActive === '1' ? 0 : 1;

        axios.patch('/inventory/' + itemId + '/quick-update', { is_active: newActive })
            .then(function() { fetchItems(); })
            .catch(function() { alert('Failed to toggle status.'); });
    });
});
</script>
@endpush

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
