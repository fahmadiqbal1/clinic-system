@extends('layouts.app')
@section('title', 'Edit ' . $vendor->name . ' — ' . config('app.name'))

@section('content')
<div class="fade-in" style="max-width:700px;">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-building-gear me-2"></i>Edit: {{ $vendor->name }}</h1>
        </div>
        <a href="{{ route('owner.vendors.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" action="{{ route('owner.vendors.update', $vendor) }}">
        @csrf @method('PATCH')
        <div class="glass-card mb-3">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $vendor->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Short Name</label>
                    <input type="text" name="short_name" class="form-control" value="{{ old('short_name', $vendor->short_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $vendor->contact_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $vendor->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">General Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $vendor->email) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">PO Email</label>
                    <input type="email" name="po_email" class="form-control" value="{{ old('po_email', $vendor->po_email) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Terms</label>
                    <input type="text" name="payment_terms" class="form-control" value="{{ old('payment_terms', $vendor->payment_terms) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address', $vendor->address) }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $vendor->notes) }}</textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="auto_send_po" id="autoSendPo" value="1" {{ old('auto_send_po', $vendor->auto_send_po) ? 'checked' : '' }}>
                        <label class="form-check-label" for="autoSendPo"><strong>Auto-send purchase orders</strong></label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_approved" id="isApproved" value="1" {{ old('is_approved', $vendor->is_approved) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isApproved">Approved vendor (visible in PO creation)</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update</button>
            <a href="{{ route('owner.vendors.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
