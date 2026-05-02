@extends('layouts.app')
@section('title', 'Edit ' . $lab->name . ' — ' . config('app.name'))

@section('content')
<div class="fade-in" style="max-width:700px;">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-building-gear me-2"></i>Edit: {{ $lab->name }}</h1>
        </div>
        <a href="{{ route('owner.external-labs.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" action="{{ route('owner.external-labs.update', $lab) }}" enctype="multipart/form-data">
        @csrf @method('PATCH')
        <div class="glass-card mb-3">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Lab Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $lab->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Short Name</label>
                    <input type="text" name="short_name" class="form-control" value="{{ old('short_name', $lab->short_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="{{ old('city', $lab->city) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">MOU Commission %</label>
                    <div class="input-group">
                        <input type="number" name="mou_commission_pct" class="form-control" value="{{ old('mou_commission_pct', $lab->mou_commission_pct) }}" step="0.01" min="0" max="100">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $lab->contact_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $lab->contact_phone) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Contact Email</label>
                    <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $lab->contact_email) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address', $lab->address) }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">MOU / Pricing Notes</label>
                    <textarea name="pricing_notes" class="form-control" rows="3">{{ old('pricing_notes', $lab->pricing_notes) }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Replace MOU Document</label>
                    <input type="file" name="mou_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    @if($lab->mou_document_path)
                        <div class="form-text"><a href="{{ Storage::url($lab->mou_document_path) }}" target="_blank">View current document</a></div>
                    @endif
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" {{ old('is_active', $lab->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isActive">Active (available for referrals)</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update</button>
            <a href="{{ route('owner.external-labs.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
