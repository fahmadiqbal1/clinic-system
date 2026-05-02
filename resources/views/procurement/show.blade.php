@extends('layouts.app')
@section('title', 'Procurement Request #' . $request->id . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-cart3 me-2" style="color:var(--accent-primary);"></i>Procurement Request <span class="code-tag">#{{ $request->id }}</span></h1>
            <p class="page-subtitle">{{ ucfirst($request->department) }} &middot; {{ ucfirst($request->type) }} Request</p>
        </div>
        <a href="{{ route('procurement.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to List</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- AI auto-approval badge --}}
    @if($request->ai_approved_at)
    <div class="alert alert-info py-2 fade-in" style="font-size:0.85rem;">
        <i class="bi bi-robot me-1"></i>
        <strong>AI Auto-Approved</strong> on {{ $request->ai_approved_at->format('M d, Y H:i') }}
        — {{ $request->ai_approval_reason }}
    </div>
    @endif

    {{-- Receipt overdue warning --}}
    @if($request->status === 'approved' && $request->type === 'inventory' && $request->receipt_deadline_at && !$request->received_at)
        @php $hoursOverdue = $request->receipt_deadline_at->isPast() ? (int) now()->diffInHours($request->receipt_deadline_at) : null; @endphp
        @if($hoursOverdue !== null)
        <div class="alert alert-danger py-2 fade-in" style="font-size:0.85rem;">
            <i class="bi bi-clock-history me-1"></i>
            <strong>Receipt Overdue</strong> — this procurement is {{ $hoursOverdue }}h past the 48-hour deadline.
            @if(!Auth::user()->hasRole('Owner'))
                <a href="{{ route('procurement.receive', $request) }}" class="alert-link ms-2">Record Receipt Now</a>
            @endif
        </div>
        @else
        <div class="alert alert-warning py-2 fade-in" style="font-size:0.85rem;">
            <i class="bi bi-clock me-1"></i>
            Receipt due by {{ $request->receipt_deadline_at->format('M d, Y H:i') }}
            ({{ $request->receipt_deadline_at->diffForHumans() }}).
        </div>
        @endif
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="glass-card p-4 mb-4 fade-in delay-1">
                <h5 class="fw-bold mb-3"><i class="bi bi-clipboard-data me-2" style="color:var(--accent-info);"></i>Request Details</h5>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Department:</strong></div>
                    <div class="col-md-9">{{ ucfirst($request->department) }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Type:</strong></div>
                    <div class="col-md-9">
                        <span class="badge {{ $request->type === 'inventory' ? 'badge-glass-primary' : 'badge-glass-info' }}">
                            {{ ucfirst($request->type) }}
                        </span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Status:</strong></div>
                    <div class="col-md-9">
                        <span class="badge
                            @if ($request->status === 'pending') badge-glass-warning
                            @elseif ($request->status === 'approved') badge-glass-success
                            @elseif ($request->status === 'rejected') badge-glass-danger
                            @elseif ($request->status === 'received') badge-glass-info
                            @else badge-glass-secondary
                            @endif
                        ">{{ ucfirst($request->status) }}</span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Requested By:</strong></div>
                    <div class="col-md-9">{{ $request->requester?->name ?? 'Unknown' }}</div>
                </div>
                @if ($request->approver)
                    <div class="row mb-3">
                        <div class="col-md-3"><strong>{{ $request->status === 'rejected' ? 'Rejected' : 'Approved' }} By:</strong></div>
                        <div class="col-md-9">{{ $request->approver->name }}</div>
                    </div>
                @endif
                @if ($request->notes)
                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Notes:</strong></div>
                        <div class="col-md-9">{{ $request->notes }}</div>
                    </div>
                @endif
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Created:</strong></div>
                    <div class="col-md-9" style="color:var(--text-muted);">{{ $request->created_at?->format('M d, Y H:i A') ?? 'N/A' }}</div>
                </div>
                @if($request->received_at)
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Received:</strong></div>
                    <div class="col-md-9" style="color:var(--text-muted);">{{ $request->received_at->format('M d, Y H:i A') }}</div>
                </div>
                @endif
            </div>

            {{-- Supplier Invoice Attachment --}}
            @if($request->receipt_invoice_path)
            <div class="glass-card p-4 mb-4 fade-in delay-1" style="border-left:3px solid var(--accent-success);">
                <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-check me-2" style="color:var(--accent-success);"></i>Supplier Invoice</h5>
                @php
                    $invoiceUrl = Storage::disk('public')->url($request->receipt_invoice_path);
                    $isPdf = str_ends_with(strtolower($request->receipt_invoice_path), '.pdf');
                @endphp
                @if($isPdf)
                    <a href="{{ $invoiceUrl }}" target="_blank" class="d-flex align-items-center gap-2 text-decoration-none p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                        <i class="bi bi-file-earmark-pdf" style="font-size:2rem; color:var(--accent-danger);"></i>
                        <div>
                            <div class="fw-medium">View Invoice PDF</div>
                            <small style="color:var(--text-muted);">Click to open in new tab</small>
                        </div>
                    </a>
                @else
                    <a href="{{ $invoiceUrl }}" target="_blank">
                        <img src="{{ $invoiceUrl }}" alt="Supplier Invoice" class="img-fluid rounded" style="max-height:400px; border:1px solid var(--glass-border);">
                    </a>
                @endif
            </div>
            @endif

            <div class="glass-card p-4 fade-in delay-2">
                <h5 class="fw-bold mb-3"><i class="bi bi-list-check me-2" style="color:var(--accent-warning);"></i>
                    @if($request->isChangeRequest())
                        Proposed Changes
                    @else
                        Items ({{ $request->items->count() }})
                    @endif
                </h5>

                @if($request->isChangeRequest())
                    {{-- Change request detail view --}}
                    <div class="mb-3">
                        <span class="badge {{ match($request->change_action) { 'create' => 'badge-glass-success', 'update' => 'badge-glass-warning', 'delete' => 'badge-glass-danger', default => 'badge-glass-secondary' } }} mb-2" style="font-size:0.85rem;">
                            <i class="bi {{ match($request->change_action) { 'create' => 'bi-plus-circle', 'update' => 'bi-pencil', 'delete' => 'bi-trash', default => 'bi-question-circle' } }} me-1"></i>
                            {{ ucfirst($request->change_action) }} {{ $request->type === 'equipment_change' ? 'Equipment' : 'Catalog Entry' }}
                        </span>
                        @if($request->target_id)
                            <span class="ms-2" style="color:var(--text-muted);">Target ID: #{{ $request->target_id }}</span>
                        @endif
                    </div>

                    @if($request->change_payload)
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:200px;">Field</th>
                                        <th>Proposed Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($request->change_payload as $field => $value)
                                        <tr>
                                            <td class="fw-medium">{{ ucfirst(str_replace('_', ' ', $field)) }}</td>
                                            <td>
                                                @if(is_bool($value))
                                                    <span class="badge {{ $value ? 'badge-glass-success' : 'badge-glass-danger' }}">{{ $value ? 'Yes' : 'No' }}</span>
                                                @elseif(is_null($value))
                                                    <span style="color:var(--text-muted);">—</span>
                                                @elseif(is_numeric($value) && in_array($field, ['price', 'unit_price', 'purchase_price', 'selling_price']))
                                                    <span style="color:var(--accent-success);">{{ currency($value) }}</span>
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @else
                    {{-- Standard procurement items --}}
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Item</th>
                                    <th>Qty Requested</th>
                                    <th>Qty Received</th>
                                    <th>Unit Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($request->items as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $item->inventoryItem?->name ?? 'Service Item' }}</td>
                                        <td>{{ $item->quantity_requested }}</td>
                                        <td>{{ $item->quantity_received ?? '-' }}</td>
                                        <td>{{ $item->unit_price ? currency($item->unit_price) : '-' }}</td>
                                        <td class="fw-semibold" style="color:var(--accent-success);">{{ $item->unit_price ? currency($item->quantity_requested * $item->unit_price) : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Three-Way Reconciliation (only for received inventory procurements) --}}
            @if($request->status === 'received' && $request->type === 'inventory')
                @php
                    $hasInvoiceData = $request->items->contains(fn($i) => $i->quantity_invoiced !== null);
                    $hasDiscrepancy = $request->items->contains(function($i) {
                        return ($i->quantity_invoiced !== null && $i->quantity_invoiced != $i->quantity_requested)
                            || ($i->quantity_received !== null && $i->quantity_received != $i->quantity_requested)
                            || ($i->unit_price_invoiced !== null && $i->unit_price !== null && abs($i->unit_price_invoiced - $i->unit_price) > 0.01);
                    });
                @endphp
                <div class="glass-card p-4 fade-in delay-3" style="border-left:3px solid {{ $hasDiscrepancy ? 'var(--accent-danger)' : 'var(--accent-success)' }};">
                    <h5 class="fw-bold mb-3">
                        @if($hasDiscrepancy)
                            <i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--accent-danger);"></i>Reconciliation — Discrepancies Found
                        @else
                            <i class="bi bi-check-circle-fill me-2" style="color:var(--accent-success);"></i>Reconciliation — All Matched
                        @endif
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">PO Qty</th>
                                    @if($hasInvoiceData)<th class="text-center">Invoice Qty</th>@endif
                                    <th class="text-center">Received Qty</th>
                                    @if($hasInvoiceData)<th class="text-end">Invoice Price</th>@endif
                                    <th class="text-end">Actual Price</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($request->items as $item)
                                    @php
                                        $qtyOk = ($item->quantity_received ?? $item->quantity_requested) == $item->quantity_requested;
                                        $invQtyOk = $item->quantity_invoiced === null || $item->quantity_invoiced == $item->quantity_requested;
                                        $priceOk = $item->unit_price_invoiced === null || $item->unit_price === null || abs($item->unit_price_invoiced - $item->unit_price) < 0.02;
                                        $allOk = $qtyOk && $invQtyOk && $priceOk;
                                    @endphp
                                    <tr @if(!$allOk) style="background:rgba(var(--accent-danger-rgb),0.05);" @endif>
                                        <td>{{ $item->inventoryItem?->name ?? 'Unknown' }}</td>
                                        <td class="text-center">{{ $item->quantity_requested }}</td>
                                        @if($hasInvoiceData)
                                            <td class="text-center" @if(!$invQtyOk) style="color:var(--accent-danger);font-weight:700;" @endif>
                                                {{ $item->quantity_invoiced ?? '—' }}
                                            </td>
                                        @endif
                                        <td class="text-center" @if(!$qtyOk) style="color:var(--accent-danger);font-weight:700;" @endif>
                                            {{ $item->quantity_received ?? '—' }}
                                        </td>
                                        @if($hasInvoiceData)
                                            <td class="text-end" @if(!$priceOk) style="color:var(--accent-danger);font-weight:700;" @endif>
                                                {{ $item->unit_price_invoiced !== null ? currency($item->unit_price_invoiced) : '—' }}
                                            </td>
                                        @endif
                                        <td class="text-end">{{ $item->unit_price ? currency($item->unit_price) : '—' }}</td>
                                        <td class="text-center">
                                            @if($allOk)
                                                <span style="color:var(--accent-success);"><i class="bi bi-check-circle-fill"></i></span>
                                            @else
                                                <span style="color:var(--accent-danger);"><i class="bi bi-exclamation-triangle-fill"></i></span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-4">
            {{-- Owner Actions --}}
            @if ($request->status === 'pending' && auth()->user()->hasRole('Owner'))
                <div class="glass-card p-4 mb-4 fade-in delay-1" style="border-left:3px solid var(--accent-success);">
                    <h5 class="fw-bold mb-3"><i class="bi bi-check-circle me-2" style="color:var(--accent-success);"></i>Approve Request</h5>
                    <form action="{{ route('procurement.approve', $request) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-lg me-1"></i>Approve</button>
                    </form>
                </div>

                <div class="glass-card p-4 mb-4 fade-in delay-2" style="border-left:3px solid var(--accent-danger);">
                    <h5 class="fw-bold mb-3"><i class="bi bi-x-circle me-2" style="color:var(--accent-danger);"></i>Reject Request</h5>
                    <form action="{{ route('procurement.reject', $request) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                            <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100"><i class="bi bi-x-lg me-1"></i>Reject</button>
                    </form>
                </div>
            @endif

            {{-- Receive Action (department staff only — pharmacist/lab/radiology records receipt when stock arrives) --}}
            @if ($request->status === 'approved' && $request->type === 'inventory' && !Auth::user()->hasRole('Owner'))
                <div class="glass-card p-4 mb-4 fade-in delay-1" style="border-left:3px solid var(--accent-info);">
                    <h5 class="fw-bold mb-3"><i class="bi bi-box-arrow-in-down me-2" style="color:var(--accent-info);"></i>Receive Inventory</h5>
                    <p class="small mb-3" style="color:var(--text-muted);">Upload the supplier invoice and confirm quantities received.</p>
                    <a href="{{ route('procurement.receive', $request) }}" class="btn btn-info w-100"><i class="bi bi-clipboard-check me-1"></i>Record Receipt</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
