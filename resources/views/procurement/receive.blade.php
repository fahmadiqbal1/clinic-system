@extends('layouts.app')
@section('title', 'Receive Procurement #' . $procurementRequest->id . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-box-arrow-in-down me-2" style="color:var(--accent-info);"></i>Receive Procurement <span class="code-tag">#{{ $procurementRequest->id }}</span></h1>
            <p class="page-subtitle">Record received quantities and unit prices</p>
        </div>
        <a href="{{ route('procurement.show', $procurementRequest) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Request</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger fade-in">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="glass-card p-3 mb-4 fade-in delay-1" style="border-left:3px solid var(--accent-info);">
        <div class="d-flex flex-wrap gap-4">
            <div><strong><i class="bi bi-building me-1"></i>Department:</strong> {{ ucfirst($procurementRequest->department) }}</div>
            <div><strong><i class="bi bi-person me-1"></i>Requested By:</strong> {{ $procurementRequest->requester?->name ?? 'Unknown' }}</div>
            <div><strong><i class="bi bi-check-circle me-1"></i>Approved By:</strong> {{ $procurementRequest->approver?->name ?? 'Unknown' }}</div>
        </div>
    </div>

    <form action="{{ route('procurement.receive.store', $procurementRequest) }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="glass-card p-4 fade-in delay-2">
            <h5 class="fw-bold mb-2"><i class="bi bi-cash-coin me-2" style="color:var(--accent-warning);"></i>Confirm Received Items &amp; Prices</h5>
            <p style="color:var(--text-muted);" class="mb-3">Unit prices are pre-filled from the approved Purchase Order. Update if the supplier invoice shows a different price.</p>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Inventory Item</th>
                            <th style="width:110px;">PO Qty</th>
                            <th style="width:130px;">PO Price ({{ currency_symbol() }})</th>
                            <th style="width:160px;">Actual Price ({{ currency_symbol() }}) <span class="text-danger">*</span></th>
                            <th style="width:110px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($procurementRequest->items as $index => $item)
                            @php
                                $quotedPrice = $item->quoted_unit_price;
                                $defaultPrice = old('unit_prices.' . $item->id, $quotedPrice ?? $item->inventoryItem?->purchase_price ?? '');
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $item->inventoryItem?->name ?? 'Unknown Item' }}</strong>
                                    <small class="text-muted d-block">{{ $item->inventoryItem?->unit ?? '' }}</small>
                                </td>
                                <td>{{ $item->quantity_requested }}</td>
                                <td>
                                    @if($quotedPrice)
                                        <span class="fw-semibold text-muted">{{ currency_symbol() }}{{ number_format($quotedPrice, 2) }}</span>
                                    @else
                                        <span class="text-muted fst-italic">Not set</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">{{ currency_symbol() }}</span>
                                        <input type="number"
                                               name="unit_prices[{{ $item->id }}]"
                                               class="form-control unit-price"
                                               min="0.01"
                                               step="0.01"
                                               data-qty="{{ $item->quantity_requested }}"
                                               data-row="{{ $index }}"
                                               data-quoted="{{ $quotedPrice ?? '' }}"
                                               value="{{ $defaultPrice }}"
                                               required>
                                    </div>
                                    <small class="text-warning price-variance-note d-none" id="variance-{{ $index }}">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Price differs from PO
                                    </small>
                                </td>
                                <td class="subtotal fw-semibold" id="subtotal-{{ $index }}" style="color:var(--accent-success);">{{ currency_symbol() }}0.00</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid var(--glass-border);">
                            <td colspan="5" class="text-end"><strong>Total:</strong></td>
                            <td id="grandTotal"><strong style="color:var(--accent-warning);">{{ currency_symbol() }}0.00</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Supplier Invoice Upload --}}
        <div class="glass-card p-4 mt-4 fade-in delay-3">
            <h5 class="fw-bold mb-2"><i class="bi bi-file-earmark-arrow-up me-2" style="color:var(--accent-info);"></i>Supplier Invoice</h5>
            <p style="color:var(--text-muted);" class="mb-3">Upload the supplier's invoice or delivery note to match this receipt (PDF, JPG, PNG — max 10MB).</p>
            <input type="file" name="receipt_invoice" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            @error('receipt_invoice')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="text-end mt-3">
            <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-circle me-1"></i>Confirm Receipt & Update Stock</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const priceInputs = document.querySelectorAll('.unit-price');

    function calculate() {
        let total = 0;
        priceInputs.forEach(function(input) {
            const qty = parseInt(input.dataset.qty) || 0;
            const price = parseFloat(input.value) || 0;
            const sub = qty * price;
            total += sub;
            document.getElementById('subtotal-' + input.dataset.row).textContent = '{{ currency_symbol() }}' + sub.toFixed(2);

            // Show variance note if price differs from PO quoted price by more than 1 cent
            const quoted = parseFloat(input.dataset.quoted) || 0;
            const varianceNote = document.getElementById('variance-' + input.dataset.row);
            if (varianceNote && quoted > 0 && price > 0 && Math.abs(price - quoted) > 0.01) {
                varianceNote.classList.remove('d-none');
            } else if (varianceNote) {
                varianceNote.classList.add('d-none');
            }
        });
        document.getElementById('grandTotal').innerHTML = '<strong style="color:var(--accent-warning);">{{ currency_symbol() }}' + total.toFixed(2) + '</strong>';
    }

    priceInputs.forEach(function(input) {
        input.addEventListener('input', calculate);
    });

    calculate();
});
</script>
@endsection
