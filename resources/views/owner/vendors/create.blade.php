@extends('layouts.app')
@section('title', 'Add Vendor — ' . config('app.name'))

@section('content')
<div class="fade-in" style="max-width:700px;">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-building-add me-2"></i>Add Approved Vendor</h1>
        </div>
        <a href="{{ route('owner.vendors.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" action="{{ route('owner.vendors.store') }}">
        @csrf
        <div class="glass-card mb-3">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Muller & Phipps Pakistan (Pvt) Ltd" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Short Name</label>
                    <input type="text" name="short_name" class="form-control" value="{{ old('short_name') }}" placeholder="M&P">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">General Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">PO Email <span class="text-muted small">(for auto-dispatch)</span></label>
                    <input type="email" name="po_email" class="form-control" value="{{ old('po_email') }}" placeholder="orders@vendor.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vendor Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                        <option value="">— Select —</option>
                        <option value="pharmaceutical" {{ old('category') === 'pharmaceutical' ? 'selected' : '' }}>Pharmaceutical</option>
                        <option value="lab_supplies"   {{ old('category') === 'lab_supplies'   ? 'selected' : '' }}>Lab Supplies</option>
                        <option value="external_lab"   {{ old('category') === 'external_lab'   ? 'selected' : '' }}>External Lab</option>
                        <option value="general"        {{ old('category') === 'general'        ? 'selected' : '' }}>General / Other</option>
                    </select>
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Terms</label>
                    <input type="text" name="payment_terms" class="form-control" value="{{ old('payment_terms') }}" placeholder="Net 30, COD, ...">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Account rep contact, special terms...">{{ old('notes') }}</textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="auto_send_po" id="autoSendPo" value="1" {{ old('auto_send_po') ? 'checked' : '' }}>
                        <label class="form-check-label" for="autoSendPo">
                            <strong>Auto-send purchase orders</strong>
                            <div class="small text-muted">When enabled, approved POs for items linked to this vendor will be automatically dispatched to the PO email above.</div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Vendor</button>
            <a href="{{ route('owner.vendors.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
