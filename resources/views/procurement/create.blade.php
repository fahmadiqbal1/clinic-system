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

    <form action="{{ route('procurement.store') }}" method="POST" enctype="multipart/form-data" id="procurementForm">
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
                        <option value="inventory" {{ old('type') === 'inventory' ? 'selected' : '' }}>Inventory (Restock Existing)</option>
                        <option value="service" {{ old('type') === 'service' ? 'selected' : '' }}>Service</option>
                        <option value="new_item_request" {{ old('type') === 'new_item_request' ? 'selected' : '' }}>New Item Request</option>
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
                            <th style="width: 140px;">Qty Requested</th>
                            <th style="width: 160px;">Quoted Price ({{ currency_symbol() }}) <small class="text-muted fw-normal">optional</small></th>
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

        {{-- New Item Request Section --}}
        <div class="glass-card p-4 mb-4 fade-in delay-2" id="newItemSection" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-plus-circle me-2" style="color:var(--accent-success);"></i>New Items to Add
                </h5>
                <button type="button" class="btn btn-sm btn-success" id="addNewItemRow"><i class="bi bi-plus-lg me-1"></i>Add Row</button>
            </div>
            <div class="alert alert-info py-2 mb-3" style="font-size:0.85rem;">
                <i class="bi bi-info-circle me-1"></i>
                List the items you want added to the inventory catalogue. A manufacturer price checklist is <strong>mandatory</strong> — the request cannot be submitted without one.
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-sm" id="newItemTable">
                    <thead>
                        <tr>
                            <th>Item Name <span class="text-danger">*</span></th>
                            <th>Manufacturer <span class="text-danger">*</span></th>
                            <th style="width:80px;">Unit</th>
                            <th style="width:90px;">Pack Size</th>
                            <th style="width:90px;">Qty <span class="text-danger">*</span></th>
                            <th style="width:130px;">Est. Unit Price ({{ currency_symbol() }})</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="newItemRows">
                        {{-- Rows added dynamically --}}
                    </tbody>
                </table>
            </div>

            {{-- Vendor + mandatory price checklist --}}
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="vendor_id_new" class="form-label">Supplier / Vendor</label>
                    <select name="vendor_id" id="vendor_id_new" class="form-select">
                        <option value="">Select Vendor (optional)</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->id }}" {{ old('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 mb-3">
                    <label for="price_checklist" class="form-label">
                        Manufacturer Price Checklist <span class="text-danger">*</span>
                        <small class="text-muted fw-normal ms-1">(PDF or CSV — required)</small>
                    </label>
                    <input type="file" name="price_checklist" id="price_checklist" class="form-control @error('price_checklist') is-invalid @enderror"
                           accept=".pdf,.csv,.txt">
                    @error('price_checklist')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @else
                        <div class="form-text">Upload the supplier's current price list. Checklist date must be newer than the last processed checklist for this vendor.</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled><i class="bi bi-send me-1"></i>Submit Request</button>
        </div>
    </form>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect      = document.getElementById('type');
    const inventorySection = document.getElementById('inventorySection');
    const serviceSection  = document.getElementById('serviceSection');
    const newItemSection  = document.getElementById('newItemSection');
    const inventoryRows   = document.getElementById('inventoryRows');
    const serviceRows     = document.getElementById('serviceRows');
    const newItemRows     = document.getElementById('newItemRows');
    const submitBtn       = document.getElementById('submitBtn');

    const inventoryItems = @json($inventoryItems);

    let inventoryRowIndex = 0;
    let serviceRowIndex   = 0;
    let newItemRowIndex   = 0;

    typeSelect.addEventListener('change', function() {
        const val = this.value;
        inventorySection.style.display = val === 'inventory'         ? 'block' : 'none';
        serviceSection.style.display   = val === 'service'           ? 'block' : 'none';
        newItemSection.style.display   = val === 'new_item_request'  ? 'block' : 'none';
        submitBtn.disabled = !val;

        // Add first row if the selected section is empty
        if (val === 'inventory'        && inventoryRows.children.length === 0) addInventoryRow();
        if (val === 'service'          && serviceRows.children.length   === 0) addServiceRow();
        if (val === 'new_item_request' && newItemRows.children.length   === 0) addNewItemRow();

        // Checklist is required only for new_item_request
        const checklist = document.getElementById('price_checklist');
        if (checklist) checklist.required = (val === 'new_item_request');
    });

    // Trigger on page load if old value exists
    if (typeSelect.value) typeSelect.dispatchEvent(new Event('change'));

    document.getElementById('addInventoryItem').addEventListener('click', addInventoryRow);
    document.getElementById('addServiceItem').addEventListener('click', addServiceRow);
    document.getElementById('addNewItemRow').addEventListener('click', addNewItemRow);

    function addInventoryRow() {
        const i = inventoryRowIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="items[${i}][inventory_item_id]" class="form-select" required>
                    <option value="">Select Item</option>
                    ${inventoryItems.map(item => `<option value="${item.id}" data-price="${item.purchase_price || ''}">${item.name}${item.manufacturer ? ' [' + item.manufacturer + ']' : ''} (${item.department}) - ${item.unit}</option>`).join('')}
                </select>
            </td>
            <td><input type="number" name="items[${i}][quantity_requested]" class="form-control" min="1" value="1" required></td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Rs.</span>
                    <input type="number" name="items[${i}][quoted_unit_price]" class="form-control quoted-price" min="0" step="0.01" placeholder="0.00">
                </div>
            </td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">✕</button></td>
        `;
        inventoryRows.appendChild(tr);
        tr.querySelector('.remove-row').addEventListener('click', () => tr.remove());
        tr.querySelector('select').addEventListener('change', function() {
            const priceInput = tr.querySelector('.quoted-price');
            if (!priceInput.value) priceInput.value = this.selectedOptions[0]?.dataset.price || '';
        });
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

    function addNewItemRow() {
        const i = newItemRowIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="new_items[${i}][name]" class="form-control form-control-sm" placeholder="Item name" required></td>
            <td><input type="text" name="new_items[${i}][manufacturer]" class="form-control form-control-sm" placeholder="Manufacturer" required></td>
            <td><input type="text" name="new_items[${i}][unit]" class="form-control form-control-sm" placeholder="pack"></td>
            <td><input type="text" name="new_items[${i}][pack_size]" class="form-control form-control-sm" placeholder="e.g. 10 tabs"></td>
            <td><input type="number" name="new_items[${i}][qty]" class="form-control form-control-sm" min="1" value="1" required></td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Rs.</span>
                    <input type="number" name="new_items[${i}][unit_price]" class="form-control" min="0" step="0.01" placeholder="0.00">
                </div>
            </td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">✕</button></td>
        `;
        newItemRows.appendChild(tr);
        tr.querySelector('.remove-row').addEventListener('click', () => tr.remove());
    }
});
</script>
@endsection
