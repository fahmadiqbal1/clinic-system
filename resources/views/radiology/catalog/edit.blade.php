@extends('layouts.app')
@section('title', 'Edit Radiology Service — ' . config('app.name'))

@section('content')
<div class="container py-4" style="max-width:800px;">
    {{-- Page Header --}}
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-pencil-square me-2"></i>Edit Imaging Procedure</h1>
            <p class="text-muted mb-0">Update procedure details and pricing for <strong>{{ $serviceCatalog->name }}</strong></p>
        </div>
        <a href="{{ route('radiology.catalog.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="glass-panel">
        <form method="POST" action="{{ route('radiology.catalog.update', $serviceCatalog) }}">
            @csrf
            @method('PATCH')

            {{-- Basic Info --}}
            <div class="form-section">
                <h6 class="form-section-title"><i class="bi bi-info-circle me-2"></i>Procedure Details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Procedure Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $serviceCatalog->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                            value="{{ old('code', $serviceCatalog->code) }}" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control @error('category') is-invalid @enderror"
                            value="{{ old('category', $serviceCatalog->category) }}" list="categoryList">
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <datalist id="categoryList">
                            <option value="X-Ray">
                            <option value="Ultrasound">
                            <option value="CT Scan">
                            <option value="MRI">
                            <option value="Mammography">
                            <option value="Fluoroscopy">
                            <option value="DEXA Scan">
                        </datalist>
                    </div>
                </div>
            </div>

            {{-- Pricing & Timing --}}
            <div class="form-section">
                <h6 class="form-section-title"><i class="bi bi-cash-coin me-2"></i>Pricing &amp; Timing</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Price ({{ config('app.currency') }}) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                            <input type="number" step="0.01" name="price" class="form-control @error('price') is-invalid @enderror"
                                value="{{ old('price', $serviceCatalog->price) }}" required>
                        </div>
                        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Turnaround Time</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-clock"></i></span>
                            <input type="text" name="turnaround_time" class="form-control @error('turnaround_time') is-invalid @enderror"
                                value="{{ old('turnaround_time', $serviceCatalog->turnaround_time) }}" placeholder="e.g. 30 minutes">
                        </div>
                        @error('turnaround_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-select">
                            <option value="1" {{ $serviceCatalog->is_active ? 'selected' : '' }}>✅ Active</option>
                            <option value="0" {{ !$serviceCatalog->is_active ? 'selected' : '' }}>⛔ Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Description --}}
            <div class="form-section">
                <h6 class="form-section-title"><i class="bi bi-card-text me-2"></i>Description</h6>
                <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                    rows="3" placeholder="Procedure details, patient preparation requirements...">{{ old('description', $serviceCatalog->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Actions --}}
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,0.06);">
                <a href="{{ route('radiology.catalog.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Update Procedure
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
