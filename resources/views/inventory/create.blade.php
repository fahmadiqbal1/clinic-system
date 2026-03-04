@extends('layouts.app')
@section('title', 'Add Inventory Item')

@section('content')
<div class="fade-in">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="page-header mb-3">
                <div>
                    <h1 class="page-title"><i class="bi bi-plus-circle me-2"></i>Add Inventory Item</h1>
                    <p class="page-subtitle">Create a new item in the catalog</p>
                </div>
                <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
            </div>

            <div class="glass-card">
                    <form action="{{ route('inventory.store') }}" method="POST">
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
                            <div class="col-md-6 mb-3">
                                <label for="sku" class="form-label">SKU</label>
                                <input type="text" name="sku" id="sku" value="{{ old('sku') }}"
                                    class="form-control @error('sku') is-invalid @enderror">
                                @error('sku')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
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
    </div>
</div>
@endsection
