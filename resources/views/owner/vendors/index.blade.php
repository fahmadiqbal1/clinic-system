@extends('layouts.app')
@section('title', 'Approved Vendors — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-building-check me-2" style="color:var(--accent-primary);"></i>Approved Vendors</h1>
            <p class="page-subtitle">Authorised suppliers — purchase orders are auto-dispatched to vendors with auto-send enabled</p>
        </div>
        <a href="{{ route('owner.vendors.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Vendor</a>
    </div>

    @if($vendors->count() > 0)
    <div class="glass-card fade-in delay-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Contact</th>
                        <th>PO Email</th>
                        <th>Payment Terms</th>
                        <th>Linked Items</th>
                        <th>Auto-Send PO</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($vendors as $vendor)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $vendor->name }}</div>
                            @if($vendor->short_name)<div class="small" style="color:var(--text-muted);">{{ $vendor->short_name }}</div>@endif
                        </td>
                        <td>
                            @if($vendor->contact_name)<div class="small">{{ $vendor->contact_name }}</div>@endif
                            @if($vendor->phone)<div class="small" style="color:var(--text-muted);">{{ $vendor->phone }}</div>@endif
                        </td>
                        <td class="small">{{ $vendor->po_email ?: '—' }}</td>
                        <td class="small">{{ $vendor->payment_terms ?: '—' }}</td>
                        <td class="fw-medium">{{ $vendor->inventory_items_count }}</td>
                        <td>
                            @if($vendor->auto_send_po)
                                <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);"><i class="bi bi-send-check me-1"></i>Auto</span>
                            @else
                                <span class="badge-glass"><i class="bi bi-send me-1"></i>Manual</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('owner.vendors.edit', $vendor) }}" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('owner.vendors.destroy', $vendor) }}" class="d-inline" onsubmit="return confirm('Remove this vendor?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="empty-state fade-in delay-1">
        <i class="bi bi-building" style="font-size:2rem;opacity:0.3;"></i>
        <h6 class="mt-3 mb-1">No vendors yet</h6>
        <p class="small mb-2" style="color:var(--text-muted);">Add Muller & Phipps and other approved suppliers</p>
        <a href="{{ route('owner.vendors.create') }}" class="btn btn-sm btn-primary">Add First Vendor</a>
    </div>
    @endif
</div>
@endsection
