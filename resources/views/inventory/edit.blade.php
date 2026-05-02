@extends('layouts.app')
@section('title', 'Edit Item: ' . $item->name)

@section('content')
<div class="fade-in">
    <div class="page-header mb-3">
        <div>
            <h1 class="page-title"><i class="bi bi-pencil-square me-2"></i>Edit: {{ $item->name }}</h1>
            <p class="page-subtitle">{{ ucfirst($item->department) }} department</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventory.adjust', $item) }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-plus-slash-minus me-1"></i>Stock Adjust</a>
            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left Column: Form --}}
        <div class="col-lg-7">
            @if(Auth::user()->hasRole('Pharmacy') && !Auth::user()->hasRole('Owner'))
                {{-- Pharmacist: only selling price --}}
                <div class="glass-card">
                    <form action="{{ route('inventory.update', $item) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" value="{{ $item->name }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label for="selling_price" class="form-label">Selling Price ({{ currency_symbol() }}) *</label>
                            <input type="number" step="0.01" min="0" name="selling_price" id="selling_price"
                                value="{{ old('selling_price', $item->selling_price) }}"
                                class="form-control @error('selling_price') is-invalid @enderror" required>
                            @error('selling_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2 pt-3" style="border-top:1px solid var(--glass-border);">
                            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Price</button>
                        </div>
                    </form>
                </div>
            @else
                {{-- Full edit form --}}
                <div class="glass-card">
                    <form action="{{ route('inventory.update', $item) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="name" class="form-label">Item Name *</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $item->name) }}"
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
                                    <option value="pharmacy" {{ old('department', $item->department) === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                                    <option value="laboratory" {{ old('department', $item->department) === 'laboratory' ? 'selected' : '' }}>Laboratory</option>
                                    <option value="radiology" {{ old('department', $item->department) === 'radiology' ? 'selected' : '' }}>Radiology</option>
                                </select>
                            @endif
                            @error('department')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="manufacturer" class="form-label">Manufacturer</label>
                                <input type="text" name="manufacturer" id="manufacturer"
                                    value="{{ old('manufacturer', $item->manufacturer) }}"
                                    class="form-control @error('manufacturer') is-invalid @enderror"
                                    placeholder="e.g. GlaxoSmithKline, Roche">
                                @error('manufacturer')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="manufacturer_tag" class="form-label">Manufacturer Tag <small class="text-muted">(≤8 chars)</small></label>
                                <input type="text" name="manufacturer_tag" id="manufacturer_tag"
                                    value="{{ old('manufacturer_tag', $item->manufacturer_tag) }}"
                                    class="form-control @error('manufacturer_tag') is-invalid @enderror"
                                    maxlength="8" placeholder="GSK">
                                @error('manufacturer_tag')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="chemical_formula" class="form-label">Chemical Formula</label>
                            <input type="text" name="chemical_formula" id="chemical_formula"
                                value="{{ old('chemical_formula', $item->chemical_formula) }}"
                                class="form-control @error('chemical_formula') is-invalid @enderror">
                            @error('chemical_formula')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="sku" class="form-label">SKU</label>
                                <input type="text" name="sku" id="sku" value="{{ old('sku', $item->sku) }}"
                                    class="form-control @error('sku') is-invalid @enderror">
                                @error('sku')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="barcode" class="form-label">Barcode</label>
                                <input type="text" name="barcode" id="barcode" value="{{ old('barcode', $item->barcode) }}"
                                    class="form-control @error('barcode') is-invalid @enderror" placeholder="Scan or type barcode">
                                @error('barcode')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="unit" class="form-label">Unit *</label>
                                <input type="text" name="unit" id="unit" value="{{ old('unit', $item->unit) }}"
                                    class="form-control @error('unit') is-invalid @enderror" required>
                                @error('unit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="minimum_stock_level" class="form-label">Minimum Stock Level *</label>
                            <input type="number" min="0" name="minimum_stock_level" id="minimum_stock_level"
                                value="{{ old('minimum_stock_level', $item->minimum_stock_level) }}"
                                class="form-control @error('minimum_stock_level') is-invalid @enderror" required>
                            @error('minimum_stock_level')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="purchase_price" class="form-label">Purchase Price ({{ currency_symbol() }}) *</label>
                                <input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price"
                                    value="{{ old('purchase_price', $item->purchase_price) }}"
                                    class="form-control @error('purchase_price') is-invalid @enderror" required>
                                @error('purchase_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="selling_price" class="form-label">Selling Price ({{ currency_symbol() }}) *</label>
                                <input type="number" step="0.01" min="0" name="selling_price" id="selling_price"
                                    value="{{ old('selling_price', $item->selling_price) }}"
                                    class="form-control @error('selling_price') is-invalid @enderror" required>
                                @error('selling_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="requires_prescription" id="requires_prescription"
                                    {{ old('requires_prescription', $item->requires_prescription) ? 'checked' : '' }}
                                    value="1" class="form-check-input">
                                <label class="form-check-label" for="requires_prescription">Requires Prescription</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="is_active"
                                    {{ old('is_active', $item->is_active) ? 'checked' : '' }}
                                    value="1" class="form-check-input">
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>

                        <div class="d-flex gap-2 pt-3" style="border-top:1px solid var(--glass-border);">
                            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Item</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        {{-- Right Column: Stock Info + Recent Movements --}}
        <div class="col-lg-5">
            {{-- Stock Overview --}}
            <div class="glass-card mb-3">
                <h6 class="mb-3"><i class="bi bi-graph-up me-2"></i>Stock Overview</h6>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div style="font-size:1.3rem;font-weight:700;color:{{ $currentStock <= $item->minimum_stock_level ? 'var(--accent-danger)' : 'var(--accent-success)' }};">{{ $currentStock }}</div>
                        <small style="color:var(--text-muted);">Current Stock</small>
                    </div>
                    <div class="col-4">
                        <div style="font-size:1.3rem;font-weight:700;color:var(--accent-warning);">{{ $item->minimum_stock_level }}</div>
                        <small style="color:var(--text-muted);">Min Level</small>
                    </div>
                    <div class="col-4">
                        @php $margin = $item->purchase_price > 0 ? round((($item->selling_price - $item->purchase_price) / $item->purchase_price) * 100, 1) : 0; @endphp
                        <div style="font-size:1.3rem;font-weight:700;color:var(--accent-primary);">{{ $margin }}%</div>
                        <small style="color:var(--text-muted);">Margin</small>
                    </div>
                </div>

                @if($item->weighted_avg_cost)
                <div class="mt-3 pt-2" style="border-top:1px solid var(--glass-border);">
                    <small style="color:var(--text-muted);">Weighted Avg Cost: <strong>{{ currency($item->weighted_avg_cost) }}</strong></small>
                </div>
                @endif
            </div>

            {{-- Barcode Scanner --}}
            <div class="glass-card mb-3">
                <h6 class="mb-3"><i class="bi bi-upc-scan me-2"></i>Scan Barcode</h6>
                <x-barcode-scanner id="edit-scanner" modes="usb,camera" placeholder="Scan to update barcode..." />
            </div>

            {{-- Recent Stock Movements --}}
            <div class="glass-card">
                <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i>Recent Movements</h6>
                @if($recentMovements->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" style="font-size:0.8rem;">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Qty</th>
                                    <th>Date</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentMovements as $mv)
                                    <tr>
                                        <td><span class="badge-glass">{{ ucfirst($mv->type) }}</span></td>
                                        <td class="text-end fw-medium" style="color:{{ $mv->quantity >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' }};">
                                            {{ $mv->quantity >= 0 ? '+' : '' }}{{ $mv->quantity }}
                                        </td>
                                        <td style="color:var(--text-muted);">{{ $mv->created_at->format('d M H:i') }}</td>
                                        <td>{{ $mv->creator?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <a href="{{ route('stock-movements.index', ['item' => $item->id]) }}" class="btn btn-sm btn-outline-secondary">View All Movements</a>
                    </div>
                @else
                    <p class="small mb-0" style="color:var(--text-muted);">No stock movements yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('barcode-scanned', function(e) {
        if (e.detail.scannerId !== 'edit-scanner') return;
        const barcodeInput = document.getElementById('barcode');
        if (barcodeInput) barcodeInput.value = e.detail.code;
    });
});
</script>
@endpush
