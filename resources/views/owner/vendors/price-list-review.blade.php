@extends('layouts.app')
@section('title', 'Review Price List — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-file-earmark-check me-2"></i>Review Price List</h1>
            <p class="text-muted mb-0">
                {{ $priceList->vendor->name }} &mdash; {{ $priceList->original_filename }}
                <span class="badge bg-secondary ms-2">{{ $priceList->item_count ?? 0 }} items</span>
                @if($priceList->flagged_count)
                    <span class="badge bg-warning text-dark ms-1">{{ $priceList->flagged_count }} flagged</span>
                @endif
            </p>
        </div>
        <a href="{{ route('owner.vendors.edit', $priceList->vendor) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div>
            <strong>Pharmaceutical price updates affect purchase cost only.</strong>
            Selling price is never changed by this process. Your onsite lab pricing in the
            service catalog is completely isolated from these updates.
        </div>
    </div>

    @if($priceList->items->isEmpty())
        <div class="glass-card text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <p class="mt-3 text-muted">No items were extracted from this price list.</p>
        </div>
    @else
        <form method="POST" action="{{ route('owner.vendors.price-list.apply', $priceList) }}">
            @csrf

            <div class="glass-card mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label fw-semibold" for="selectAll">Select all reviewable items</label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-all me-1"></i>Apply Selected Prices
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Item Name</th>
                                <th>Detected Price</th>
                                <th>Current Price</th>
                                <th>Matched Item</th>
                                <th>Confidence</th>
                                <th>Review?</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($priceList->items as $item)
                            <tr class="{{ $item->needs_review ? 'table-warning' : '' }}">
                                <td>
                                    <input class="form-check-input item-checkbox" type="checkbox"
                                           name="approved_items[]" value="{{ $item->id }}"
                                           {{ $item->applied ? 'disabled' : '' }}>
                                </td>
                                <td>
                                    <span class="fw-medium">{{ $item->item_name }}</span>
                                    @if($item->sku_detected)
                                        <br><small class="text-muted">SKU: {{ $item->sku_detected }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($item->detected_price !== null)
                                        <span class="fw-semibold">PKR {{ number_format($item->detected_price, 2) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    @if($item->unit_detected)
                                        <small class="text-muted d-block">/ {{ $item->unit_detected }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($item->current_price !== null)
                                        PKR {{ number_format($item->current_price, 2) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->inventoryItem)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-link-45deg"></i> {{ $item->inventoryItem->name }}
                                        </span>
                                    @elseif($item->test_name_normalized)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <i class="bi bi-flask"></i> {{ $item->test_name_normalized }}
                                        </span>
                                    @else
                                        <span class="text-muted">Not matched</span>
                                    @endif
                                </td>
                                <td>
                                    @php $pct = (int) round($item->confidence * 100); @endphp
                                    <span class="badge {{ $pct >= 80 ? 'bg-success' : ($pct >= 60 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                        {{ $pct }}%
                                    </span>
                                </td>
                                <td>
                                    @if($item->applied)
                                        <span class="badge bg-secondary">Applied</span>
                                    @elseif($item->needs_review)
                                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Review</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">OK</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-all me-1"></i>Apply Selected Prices
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>

@push('scripts')
<script>
document.getElementById('selectAll').addEventListener('change', function () {
    document.querySelectorAll('.item-checkbox:not(:disabled)').forEach(cb => {
        cb.checked = this.checked;
    });
});
</script>
@endpush
@endsection
