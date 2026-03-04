@extends('layouts.app')
@section('title', 'New Procurement Request — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-cart-plus me-2" style="color:var(--accent-success);"></i>New Procurement Request</h1>
            <p class="page-subtitle">Submit a new inventory or service procurement request</p>
        </div>
        <a href="{{ route('procurement.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Cancel</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger fade-in">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('procurement.store') }}" method="POST" id="procurementForm">
        @csrf

        <div class="glass-card p-4 mb-4 fade-in delay-1">
            <h5 class="fw-bold mb-3"><i class="bi bi-clipboard-data me-2" style="color:var(--accent-primary);"></i>Request Details</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                    @if($userDepartment)
                        <input type="hidden" name="department" value="{{ $userDepartment }}">
                        <input type="text" class="form-control" value="{{ ucfirst($userDepartment) }}" disabled>
                    @else
                        <select name="department" id="department" class="form-select" required>
                            <option value="">Select Department</option>
                            <option value="pharmacy" {{ old('department') === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                            <option value="laboratory" {{ old('department') === 'laboratory' ? 'selected' : '' }}>Laboratory</option>
                            <option value="radiology" {{ old('department') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                        </select>
                    @endif
                </div>
                <div class="col-md-4 mb-3">
                    <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                    <select name="type" id="type" class="form-select" required>
                        <option value="">Select Type</option>
                        <option value="inventory" {{ old('type') === 'inventory' ? 'selected' : '' }}>Inventory</option>
                        <option value="service" {{ old('type') === 'service' ? 'selected' : '' }}>Service</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="1">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Inventory Items Section --}}
        <div class="glass-card p-4 mb-4 fade-in delay-2" id="inventorySection" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-box-seam me-2" style="color:var(--accent-info);"></i>Inventory Items</h5>
                <button type="button" class="btn btn-sm btn-primary" id="addInventoryItem"><i class="bi bi-plus-lg me-1"></i>Add Item</button>
            </div>
            <div class="table-responsive">
                <table class="table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Inventory Item</th>
                            <th style="width: 150px;">Qty Requested</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody id="inventoryRows">
                        {{-- Dynamic rows added via JS --}}
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Service Items Section --}}
        <div class="glass-card p-4 mb-4 fade-in delay-2" id="serviceSection" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-tools me-2" style="color:var(--accent-warning);"></i>Service Items</h5>
                <button type="button" class="btn btn-sm btn-primary" id="addServiceItem"><i class="bi bi-plus-lg me-1"></i>Add Item</button>
            </div>
            <div class="table-responsive">
                <table class="table" id="serviceTable">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th style="width: 150px;">Quantity</th>
                            <th style="width: 150px;">Unit Price ({{ currency_symbol() }})</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody id="serviceRows">
                        {{-- Dynamic rows added via JS --}}
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled><i class="bi bi-send me-1"></i>Submit Request</button>
        </div>
    </form>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type');
    const inventorySection = document.getElementById('inventorySection');
    const serviceSection = document.getElementById('serviceSection');
    const inventoryRows = document.getElementById('inventoryRows');
    const serviceRows = document.getElementById('serviceRows');
    const submitBtn = document.getElementById('submitBtn');

    const inventoryItems = @json($inventoryItems);

    let inventoryRowIndex = 0;
    let serviceRowIndex = 0;

    typeSelect.addEventListener('change', function() {
        const val = this.value;
        inventorySection.style.display = val === 'inventory' ? 'block' : 'none';
        serviceSection.style.display = val === 'service' ? 'block' : 'none';
        submitBtn.disabled = !val;

        // Add first row if empty
        if (val === 'inventory' && inventoryRows.children.length === 0) addInventoryRow();
        if (val === 'service' && serviceRows.children.length === 0) addServiceRow();
    });

    // Trigger on page load if old value exists
    if (typeSelect.value) typeSelect.dispatchEvent(new Event('change'));

    document.getElementById('addInventoryItem').addEventListener('click', addInventoryRow);
    document.getElementById('addServiceItem').addEventListener('click', addServiceRow);

    function addInventoryRow() {
        const i = inventoryRowIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="items[${i}][inventory_item_id]" class="form-select" required>
                    <option value="">Select Item</option>
                    ${inventoryItems.map(item => `<option value="${item.id}">${item.name} (${item.department}) - ${item.unit}</option>`).join('')}
                </select>
            </td>
            <td><input type="number" name="items[${i}][quantity_requested]" class="form-control" min="1" value="1" required></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">✕</button></td>
        `;
        inventoryRows.appendChild(tr);
        tr.querySelector('.remove-row').addEventListener('click', () => tr.remove());
    }

    function addServiceRow() {
        const i = serviceRowIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="items[${i}][service_name]" class="form-control" placeholder="e.g. Equipment maintenance" required></td>
            <td><input type="number" name="items[${i}][quantity_requested]" class="form-control" min="1" value="1" required></td>
            <td><input type="number" name="items[${i}][unit_price]" class="form-control" min="0.01" step="0.01" placeholder="0.00" required></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">✕</button></td>
        `;
        serviceRows.appendChild(tr);
        tr.querySelector('.remove-row').addEventListener('click', () => tr.remove());
    }
});
</script>
@endsection
