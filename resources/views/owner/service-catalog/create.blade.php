@extends('layouts.app')
@section('title', 'Create Service — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header mb-4 fade-in">
        <h1><i class="bi bi-plus-circle me-2"></i>Add Service</h1>
        <p class="page-subtitle">Add a new service to the catalog</p>
    </div>

    <div class="glass-card p-4 fade-in delay-1" style="max-width: 700px;">
        <form method="POST" action="{{ route('owner.service-catalog.store') }}">
            @csrf

            <div class="mb-3">
                <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                <select name="department" id="department" class="form-select @error('department') is-invalid @enderror" required>
                    <option value="">Select department...</option>
                    <option value="consultation" {{ old('department') === 'consultation' ? 'selected' : '' }}>Consultation</option>
                    <option value="lab" {{ old('department') === 'lab' ? 'selected' : '' }}>Laboratory</option>
                    <option value="radiology" {{ old('department') === 'radiology' ? 'selected' : '' }}>Radiology</option>
                    <option value="pharmacy" {{ old('department') === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                </select>
                @error('department') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="name" class="form-label">Service Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="code" class="form-label">Service Code</label>
                    <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}">
                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label for="hs_code" class="form-label">
                        HS Code
                        <span class="badge bg-info text-dark ms-1" style="font-size:.6rem;">FBR</span>
                    </label>
                    <input type="text" name="hs_code" id="hs_code" class="form-control @error('hs_code') is-invalid @enderror"
                           value="{{ old('hs_code') }}" placeholder="e.g. 9018.90.10">
                    @error('hs_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Harmonized System code for FBR IRIS. Default: <code>9018.90.10</code> (medical services).</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="category" class="form-label">Category</label>
                    <input type="text" name="category" id="category" class="form-control @error('category') is-invalid @enderror" value="{{ old('category') }}">
                    @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                    <input type="number" name="price" id="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price') }}" step="0.01" min="0" required>
                    @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="turnaround_time" class="form-label">Turnaround Time</label>
                    <input type="text" name="turnaround_time" id="turnaround_time" class="form-control @error('turnaround_time') is-invalid @enderror" value="{{ old('turnaround_time') }}" placeholder="e.g. 2 hours, 1 day">
                    @error('turnaround_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Add Service
                </button>
                <a href="{{ route('owner.service-catalog.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
