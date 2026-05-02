@extends('layouts.app')
@section('title', 'Upload Price List — ' . config('app.name'))

@section('content')
<div class="fade-in" style="max-width:600px;">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-cloud-upload me-2" style="color:var(--accent-primary);"></i>Upload Price List</h1>
            <p class="page-subtitle">Submit a new vendor price list — changes go to the owner for approval before taking effect</p>
        </div>
        <a href="{{ route('pharmacy.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <div class="glass-card mb-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle me-2" style="color:var(--accent-info);"></i>CSV Format Required</h6>
        <p class="small mb-2" style="color:var(--text-muted);">Your CSV must have a header row with at least a <strong>price</strong> column. Optionally include <strong>sku</strong> and/or <strong>name</strong> to match items.</p>
        <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border); font-family:monospace; font-size:0.82rem;">
            sku,name,price<br>
            62038,Co-Diovan 80/12.5 Tablets,1650.00<br>
            62039,Co-Diovan 160/12.5 Tablets,2700.00<br>
            …
        </div>
        <p class="small mt-2 mb-0" style="color:var(--text-muted);">Items that don't match any existing medicine by SKU or name are skipped. Only changed prices create approval requests.</p>
    </div>

    <form method="POST" action="{{ route('pharmacy.price-list.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="glass-card mb-3">
            <div class="mb-3">
                <label class="form-label">Price List CSV <span class="text-danger">*</span></label>
                <input type="file" name="price_list" class="form-control @error('price_list') is-invalid @enderror" accept=".csv,.txt" required>
                @error('price_list')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="form-label">Notes <span class="text-muted small">(optional)</span></label>
                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="e.g. Muller & Phipps May 2026 price list">
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-upload me-1"></i>Submit for Approval</button>
            <a href="{{ route('pharmacy.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
