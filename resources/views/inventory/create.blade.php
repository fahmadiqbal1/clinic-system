@extends('layouts.app')
@section('title', 'Add Inventory Item')

@section('content')
<div class="fade-in">
    <div class="page-header mb-3">
        <div>
            <h1 class="page-title"><i class="bi bi-plus-circle me-2"></i>Add Inventory Item</h1>
            <p class="page-subtitle">Create a new item in the catalog</p>
        </div>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <div class="row g-4">
        {{-- Left Column: Form --}}
        <div class="col-lg-7">
            <div class="glass-card">
                <form action="{{ route('inventory.store') }}" method="POST" id="createItemForm">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Item Name *</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}"
                            class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label">Department *</label>
                        @if($userDepartment)
                            <input type="hidden" name="department" value="{{ $userDepartment }}">
                            <input type="text" class="form-control" value="{{ ucfirst($userDepartment) }}" disabled>
                        @else
                            <select name="department" id="department" class="form-select @error('department') is-invalid @enderror" required>
                                <option value="">Select department</option>
                                <option value="pharmacy" {{ old('department') === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                                <option value="laboratory" {{ old('department') === 'laboratory' ? 'selected' : '' }}>Laboratory</option>
                                <option value="radiology" {{ old('department') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                            </select>
                        @endif
                        @error('department')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="chemical_formula" class="form-label">Chemical Formula</label>
                        <input type="text" name="chemical_formula" id="chemical_formula" value="{{ old('chemical_formula') }}"
                            class="form-control @error('chemical_formula') is-invalid @enderror">
                        @error('chemical_formula')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="sku" class="form-label">SKU</label>
                            <input type="text" name="sku" id="sku" value="{{ old('sku') }}"
                                class="form-control @error('sku') is-invalid @enderror">
                            @error('sku')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="barcode" class="form-label">Barcode</label>
                            <input type="text" name="barcode" id="barcode" value="{{ old('barcode', request('barcode')) }}"
                                class="form-control @error('barcode') is-invalid @enderror" placeholder="Scan or type barcode">
                            @error('barcode')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="unit" class="form-label">Unit *</label>
                            <input type="text" name="unit" id="unit" value="{{ old('unit') }}"
                                class="form-control @error('unit') is-invalid @enderror"
                                placeholder="e.g., pcs, ml, mg, box" required>
                            @error('unit')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="minimum_stock_level" class="form-label">Minimum Stock Level *</label>
                        <input type="number" min="0" name="minimum_stock_level" id="minimum_stock_level"
                            value="{{ old('minimum_stock_level', 0) }}"
                            class="form-control @error('minimum_stock_level') is-invalid @enderror" required>
                        @error('minimum_stock_level')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="purchase_price" class="form-label">Purchase Price ({{ currency_symbol() }}) *</label>
                            <input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price"
                                value="{{ old('purchase_price') }}"
                                class="form-control @error('purchase_price') is-invalid @enderror" required>
                            @error('purchase_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="selling_price" class="form-label">Selling Price ({{ currency_symbol() }}) *</label>
                            <input type="number" step="0.01" min="0" name="selling_price" id="selling_price"
                                value="{{ old('selling_price') }}"
                                class="form-control @error('selling_price') is-invalid @enderror" required>
                            @error('selling_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="requires_prescription" id="requires_prescription"
                                {{ old('requires_prescription') ? 'checked' : '' }}
                                value="1" class="form-check-input">
                            <label class="form-check-label" for="requires_prescription">Requires Prescription</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="is_active"
                                checked value="1" class="form-check-input">
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2 pt-3" style="border-top:1px solid var(--glass-border);">
                        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Item</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right Column: Barcode Scanner + Preview --}}
        <div class="col-lg-5">
            {{-- Scanner to auto-fill barcode --}}
            <div class="glass-card mb-3">
                <h6 class="mb-3"><i class="bi bi-upc-scan me-2"></i>Scan Barcode</h6>
                <x-barcode-scanner id="create-scanner" modes="usb,camera" placeholder="Scan to fill barcode field..." />
                <div id="scanFeedback" class="mt-2" style="display:none;"></div>
            </div>

            {{-- Live Preview --}}
            <div class="glass-card">
                <h6 class="mb-3"><i class="bi bi-eye me-2"></i>Preview</h6>
                <div id="livePreview">
                    <div class="text-center py-3" style="color:var(--text-muted);">
                        <i class="bi bi-box-seam" style="font-size:2rem;opacity:0.3;"></i>
                        <p class="small mt-2 mb-0">Start filling the form to see a preview</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const barcodeInput = document.getElementById('barcode');

    // Scanner auto-fills the barcode field
    document.addEventListener('barcode-scanned', function(e) {
        if (e.detail.scannerId !== 'create-scanner') return;
        barcodeInput.value = e.detail.code;

        const feedback = document.getElementById('scanFeedback');
        feedback.style.display = 'block';

        // Check if barcode already exists
        axios.get('{{ route("inventory.barcode-lookup") }}', { params: { code: e.detail.code } })
            .then(function(response) {
                if (response.data.found) {
                    feedback.innerHTML = '<div class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>This barcode is already assigned to <strong>' + response.data.item.name + '</strong>.</div>';
                } else {
                    feedback.innerHTML = '<div class="text-success"><i class="bi bi-check-circle me-1"></i>Barcode set: ' + e.detail.code + '</div>';
                }
            });
    });

    // Live preview
    const previewFields = ['name', 'department', 'sku', 'barcode', 'unit', 'purchase_price', 'selling_price'];
    const preview = document.getElementById('livePreview');

    function updatePreview() {
        const name = document.getElementById('name').value;
        if (!name) {
            preview.innerHTML = '<div class="text-center py-3" style="color:var(--text-muted);"><i class="bi bi-box-seam" style="font-size:2rem;opacity:0.3;"></i><p class="small mt-2 mb-0">Start filling the form to see a preview</p></div>';
            return;
        }

        const dept = document.getElementById('department') ? (document.getElementById('department').value || '{{ $userDepartment ?? "" }}') : '{{ $userDepartment ?? "" }}';
        const sku = document.getElementById('sku').value || '—';
        const bc = document.getElementById('barcode').value || '—';
        const unit = document.getElementById('unit').value || '—';
        const pp = document.getElementById('purchase_price').value || '0';
        const sp = document.getElementById('selling_price').value || '0';
        const rx = document.getElementById('requires_prescription').checked;
        const margin = pp > 0 ? (((sp - pp) / pp) * 100).toFixed(1) : '—';

        preview.innerHTML = `
            <div class="mb-2"><strong>${name}</strong>${rx ? ' <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.18);color:var(--accent-warning);font-size:0.65rem;">Rx</span>' : ''}</div>
            <table class="table table-sm mb-0" style="font-size:0.85rem;">
                <tr><td style="color:var(--text-muted);width:40%;">Department</td><td>${dept ? dept.charAt(0).toUpperCase() + dept.slice(1) : '—'}</td></tr>
                <tr><td style="color:var(--text-muted);">SKU</td><td>${sku}</td></tr>
                <tr><td style="color:var(--text-muted);">Barcode</td><td>${bc}</td></tr>
                <tr><td style="color:var(--text-muted);">Unit</td><td>${unit}</td></tr>
                <tr><td style="color:var(--text-muted);">Purchase</td><td>${pp}</td></tr>
                <tr><td style="color:var(--text-muted);">Selling</td><td>${sp}</td></tr>
                <tr><td style="color:var(--text-muted);">Margin</td><td>${margin}%</td></tr>
            </table>`;
    }

    previewFields.forEach(function(field) {
        const el = document.getElementById(field);
        if (el) el.addEventListener('input', updatePreview);
    });
    document.getElementById('requires_prescription').addEventListener('change', updatePreview);

    // Trigger preview if barcode was pre-filled from URL
    if (barcodeInput.value) updatePreview();
});
</script>
@endpush
