@extends('layouts.app')
@section('title', 'Add Radiology Service — ' . config('app.name'))

@section('content')
<div class="container py-4" style="max-width:800px;">
    {{-- Page Header --}}
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-plus-circle me-2"></i>Add Imaging Procedure</h1>
            <p class="text-muted mb-0">Create a new procedure entry in the imaging pricing catalog</p>
        </div>
        <a href="{{ route('radiology.catalog.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="glass-panel">
        <form method="POST" action="{{ route('radiology.catalog.store') }}">
            @csrf

            {{-- Basic Info --}}
            <div class="form-section">
                <h6 class="form-section-title"><i class="bi bi-info-circle me-2"></i>Procedure Details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Procedure Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}" required placeholder="e.g. Chest X-Ray (PA)">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                            value="{{ old('code') }}" required placeholder="e.g. RAD-XR-001">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control @error('category') is-invalid @enderror"
                            value="{{ old('category') }}" placeholder="e.g. X-Ray" list="categoryList">
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
                    <div class="col-md-6">
                        <label class="form-label">Price ({{ config('app.currency') }}) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                            <input type="number" step="0.01" name="price" class="form-control @error('price') is-invalid @enderror"
                                value="{{ old('price') }}" required placeholder="0.00">
                        </div>
                        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Turnaround Time</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-clock"></i></span>
                            <input type="text" name="turnaround_time" class="form-control @error('turnaround_time') is-invalid @enderror"
                                value="{{ old('turnaround_time') }}" placeholder="e.g. 30 minutes">
                        </div>
                        @error('turnaround_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Description --}}
            <div class="form-section">
                <h6 class="form-section-title"><i class="bi bi-card-text me-2"></i>Description</h6>
                <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                    rows="3" placeholder="Procedure details, patient preparation requirements, etc.">{{ old('description') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Actions --}}
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,0.06);">
                <a href="{{ route('radiology.catalog.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save Procedure
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
