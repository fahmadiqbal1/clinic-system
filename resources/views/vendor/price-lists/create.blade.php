@extends('layouts.app')
@section('title', 'Upload Price List — Vendor Portal')

@section('content')
<div class="container mt-4" style="max-width: 640px;">
    <div class="page-header fade-in">
        <h2 class="mb-1"><i class="bi bi-upload me-2" style="color:var(--accent-primary);"></i>Upload Price List</h2>
        <p class="page-subtitle mb-0">{{ $vendor->name }} · Upload your latest pricing sheet</p>
    </div>

    <div class="glass-card fade-in">
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Accepted formats:</strong> PDF, JPG, PNG, CSV (max 20 MB).<br>
            Our system will automatically extract pricing. Items that cannot be read clearly will be flagged for manual review before any prices are updated.
        </div>

        <form method="POST" action="{{ route('vendor.price-lists.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label class="form-label fw-semibold">Price List File <span class="text-danger">*</span></label>
                <input type="file" name="price_file" class="form-control @error('price_file') is-invalid @enderror"
                       accept=".pdf,.jpg,.jpeg,.png,.csv" required>
                @error('price_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Scanned PDF, photo of brochure, or CSV export accepted.</div>
            </div>
            <div class="mb-4">
                <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
                <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Effective from 1st June 2026, replaces previous list..."></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload & Extract</button>
                <a href="{{ route('vendor.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
