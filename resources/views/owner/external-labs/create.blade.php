@extends('layouts.app')
@section('title', 'Add External Lab — ' . config('app.name'))

@section('content')
<div class="fade-in" style="max-width:700px;">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-building-add me-2"></i>Add External Lab</h1>
            <p class="page-subtitle">Register an MOU partner lab for patient referrals</p>
        </div>
        <a href="{{ route('owner.external-labs.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" action="{{ route('owner.external-labs.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="glass-card mb-3">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Lab Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Chugtai Lab" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Short Name</label>
                    <input type="text" name="short_name" class="form-control" value="{{ old('short_name') }}" placeholder="e.g. CHUG">
                </div>
                <div class="col-md-6">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="{{ old('city') }}" placeholder="Lahore">
                </div>
                <div class="col-md-6">
                    <label class="form-label">MOU Commission %</label>
                    <div class="input-group">
                        <input type="number" name="mou_commission_pct" class="form-control" value="{{ old('mou_commission_pct', 0) }}" step="0.01" min="0" max="100">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Percentage we earn per referred patient</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Contact Email</label>
                    <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">MOU / Pricing Notes</label>
                    <textarea name="pricing_notes" class="form-control" rows="3" placeholder="Special rates, test-specific pricing, terms...">{{ old('pricing_notes') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">MOU Document (PDF/Image)</label>
                    <input type="file" name="mou_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Lab</button>
            <a href="{{ route('owner.external-labs.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
