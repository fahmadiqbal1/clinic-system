@extends('layouts.app')
@section('title', 'Price List — ' . $priceList->original_filename)

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-file-earmark-text me-2" style="color:var(--accent-primary);"></i>{{ $priceList->original_filename }}</h2>
            <p class="page-subtitle mb-0">{{ $vendor->name }} · Uploaded {{ $priceList->created_at->format('M d, Y') }}</p>
        </div>
        <a href="{{ route('vendor.price-lists.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success fade-in">{{ session('success') }}</div>
    @endif

    {{-- Status Card --}}
    @php
        $statusColors = ['pending'=>'warning','processing'=>'info','extracted'=>'primary','applied'=>'success','flagged'=>'danger','failed'=>'danger'];
        $statusColor = $statusColors[$priceList->status] ?? 'secondary';
    @endphp
    <div class="glass-card fade-in mb-4">
        <div class="row g-3">
            <div class="col-6 col-md-3 text-center">
                <div class="metric-label">Status</div>
                <span class="badge bg-{{ $statusColor }} fs-6">{{ ucfirst($priceList->status) }}</span>
            </div>
            <div class="col-6 col-md-3 text-center">
                <div class="metric-value">{{ $priceList->item_count ?? '—' }}</div>
                <div class="metric-label">Items Extracted</div>
            </div>
            <div class="col-6 col-md-3 text-center">
                <div class="metric-value {{ $priceList->flagged_count ? 'text-danger' : '' }}">{{ $priceList->flagged_count ?? '—' }}</div>
                <div class="metric-label">Flagged</div>
            </div>
            <div class="col-6 col-md-3 text-center">
                <div class="metric-value">{{ $priceList->applied_at?->format('M d, Y') ?? '—' }}</div>
                <div class="metric-label">Applied</div>
            </div>
        </div>
        @if($priceList->flag_reasons)
            <hr class="my-3">
            <div class="alert alert-warning mb-0">
                <strong><i class="bi bi-exclamation-triangle me-2"></i>Flag Reasons:</strong>
                <ul class="mb-0 mt-1">
                    @foreach($priceList->flag_reasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Extracted Items --}}
    <div class="glass-card fade-in">
        <h6 class="mb-3"><i class="bi bi-table me-2"></i>Extracted Items</h6>

        @if($priceList->status === 'pending' || $priceList->status === 'processing')
            <div class="text-center py-4">
                <div class="spinner-border text-info mb-2" role="status"></div>
                <p class="text-muted mb-0">AI extraction is in progress — check back shortly.</p>
            </div>
        @elseif($priceList->items->isEmpty())
            <p class="text-muted mb-0">No items were extracted from this price list.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>SKU</th>
                            <th>Unit</th>
                            <th>Detected Price</th>
                            <th>Current Price</th>
                            <th>Confidence</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($priceList->items as $item)
                    <tr class="{{ $item->needs_review ? 'table-warning' : '' }}">
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->sku_detected ?? '—' }}</td>
                        <td>{{ $item->unit_detected ?? '—' }}</td>
                        <td>PKR {{ number_format($item->detected_price, 2) }}</td>
                        <td>{{ $item->current_price ? 'PKR '.number_format($item->current_price, 2) : '—' }}</td>
                        <td>
                            @php $conf = $item->confidence * 100; @endphp
                            <span class="badge bg-{{ $conf >= 80 ? 'success' : ($conf >= 60 ? 'warning' : 'danger') }}">
                                {{ number_format($conf, 0) }}%
                            </span>
                        </td>
                        <td>
                            @if($item->applied)
                                <span class="badge bg-success">Applied</span>
                            @elseif($item->needs_review)
                                <span class="badge bg-warning text-dark">Needs Review</span>
                            @else
                                <span class="badge bg-secondary">Pending</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($priceList->status === 'extracted' || $priceList->status === 'flagged')
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    This price list is awaiting owner review and approval before any prices are updated in the system.
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
