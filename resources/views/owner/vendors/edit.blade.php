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

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

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
                    <label class="form-label">Vendor Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <option value="pharmaceutical" @selected(old('category', $vendor->category) === 'pharmaceutical')>Pharmaceutical</option>
                        <option value="lab_supplies"   @selected(old('category', $vendor->category) === 'lab_supplies')>Lab Supplies</option>
                        <option value="external_lab"   @selected(old('category', $vendor->category) === 'external_lab')>External Lab</option>
                        <option value="general"        @selected(old('category', $vendor->category) === 'general')>General / Other</option>
                    </select>
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

    {{-- Upload Price List --}}
    <div class="card mt-4">
        <div class="card-header"><i class="bi bi-file-earmark-arrow-up me-2" style="color:var(--accent-primary);"></i>Upload Price List / Checklist</div>
        <div class="card-body">
            <form method="POST" action="{{ route('owner.vendors.price-list.upload', $vendor) }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-8">
                    <label class="form-label">File <span class="text-muted small">(PDF, CSV, or image — max 20 MB)</span></label>
                    <input type="file" name="price_list_file" class="form-control" accept=".pdf,.csv,.jpg,.jpeg,.png" required>
                    <div class="form-text">CSV is processed locally. PDF/images are sent to the AI sidecar for extraction.</div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-cpu me-1"></i>Upload &amp; Extract
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Price list history --}}
    @if($vendor->priceLists->isNotEmpty())
    <div class="card mt-4">
        <div class="card-header"><i class="bi bi-clock-history me-2" style="color:var(--accent-info);"></i>Price List History</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>File</th><th>Uploaded</th><th>Status</th><th>Items</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($vendor->priceLists()->latest()->get() as $pl)
                        <tr>
                            <td>
                                <i class="bi bi-file-earmark-{{ $pl->file_type === 'csv' ? 'spreadsheet' : ($pl->file_type === 'pdf' ? 'pdf' : 'image') }} me-1"></i>
                                {{ $pl->original_filename }}
                            </td>
                            <td><small>{{ $pl->created_at->format('d M Y H:i') }}</small></td>
                            <td>
                                @php $colors = ['pending'=>'secondary','processing'=>'info','extracted'=>'primary','flagged'=>'warning','applied'=>'success','failed'=>'danger','pending_sidecar'=>'warning']; @endphp
                                <span class="badge bg-{{ $colors[$pl->status] ?? 'secondary' }} {{ in_array($pl->status,['flagged','pending_sidecar']) ? 'text-dark' : '' }}">
                                    {{ $pl->status === 'pending_sidecar' ? 'Awaiting AI' : ucfirst($pl->status) }}
                                </span>
                                @if($pl->flagged_count) <span class="badge bg-warning text-dark ms-1">{{ $pl->flagged_count }} flagged</span> @endif
                            </td>
                            <td>{{ $pl->item_count ?? '—' }}</td>
                            <td>
                                @if($pl->is_reviewable)
                                    <a href="{{ route('owner.vendors.price-list.review', $pl) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>Review
                                    </a>
                                @elseif($pl->status === 'applied')
                                    <a href="{{ route('owner.vendors.price-list.review', $pl) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-check2 me-1"></i>Applied
                                    </a>
                                @elseif(in_array($pl->status, ['failed', 'pending_sidecar']))
                                    <form method="POST" action="{{ route('owner.vendors.price-list.retry', $pl) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Retry
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted small">{{ ucfirst($pl->status) }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
