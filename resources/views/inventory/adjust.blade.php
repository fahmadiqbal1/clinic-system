@extends('layouts.app')
@section('title', 'Stock Adjustment: ' . $item->name)

@section('content')
<div class="fade-in">
    <div class="page-header mb-3">
        <div>
            <h1 class="page-title"><i class="bi bi-plus-slash-minus me-2"></i>Stock Adjustment</h1>
            <p class="page-subtitle">{{ $item->name }} — {{ ucfirst($item->department) }}</p>
        </div>
        <a href="{{ route('inventory.edit', $item) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Edit</a>
    </div>

    <div class="row g-4">
        {{-- Left Column: Adjustment Form --}}
        <div class="col-lg-6">
            <div class="glass-card">
                <div class="row g-2 text-center mb-4">
                    <div class="col-4">
                        <div style="font-size:1.5rem;font-weight:700;color:{{ $currentStock <= $item->minimum_stock_level ? 'var(--accent-danger)' : 'var(--accent-success)' }};">{{ $currentStock }}</div>
                        <small style="color:var(--text-muted);">Current Stock</small>
                    </div>
                    <div class="col-4">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--accent-warning);">{{ $item->minimum_stock_level }}</div>
                        <small style="color:var(--text-muted);">Min Level</small>
                    </div>
                    <div class="col-4">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--text-primary);" id="newStockPreview">{{ $currentStock }}</div>
                        <small style="color:var(--text-muted);">After Adjustment</small>
                    </div>
                </div>

                <form action="{{ route('inventory.adjust.store', $item) }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="quantity" class="form-label">Adjustment Quantity *</label>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-danger" id="btnMinus" title="Negative (reduce stock)">
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <input type="number" name="quantity" id="quantity" value="{{ old('quantity', 0) }}"
                                class="form-control text-center @error('quantity') is-invalid @enderror" required>
                            <button type="button" class="btn btn-outline-success" id="btnPlus" title="Positive (add stock)">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <small class="form-text" style="color:var(--text-muted);">Use negative values to reduce stock, positive to add.</small>
                        @error('quantity')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason *</label>
                        <select name="reason" id="reason" class="form-select @error('reason') is-invalid @enderror" required>
                            <option value="">Select reason...</option>
                            <option value="physical_count" {{ old('reason') === 'physical_count' ? 'selected' : '' }}>Physical Count Correction</option>
                            <option value="breakage" {{ old('reason') === 'breakage' ? 'selected' : '' }}>Breakage / Damage</option>
                            <option value="expiry" {{ old('reason') === 'expiry' ? 'selected' : '' }}>Expired Items</option>
                            <option value="theft" {{ old('reason') === 'theft' ? 'selected' : '' }}>Loss / Theft</option>
                            <option value="spillage" {{ old('reason') === 'spillage' ? 'selected' : '' }}>Spillage</option>
                            <option value="returned" {{ old('reason') === 'returned' ? 'selected' : '' }}>Returned to Stock</option>
                            <option value="other" {{ old('reason') === 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror"
                            placeholder="Optional additional details...">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2 pt-3" style="border-top:1px solid var(--glass-border);">
                        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-warning" id="btnSubmit">
                            <i class="bi bi-check-lg me-1"></i>Submit Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right Column: Recent Movements --}}
        <div class="col-lg-6">
            <div class="glass-card">
                <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i>Recent Stock Movements</h6>
                @if($recentMovements->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" style="font-size:0.85rem;">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Qty</th>
                                    <th>Notes</th>
                                    <th>Date</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentMovements as $mv)
                                    <tr>
                                        <td><span class="badge-glass">{{ ucfirst($mv->type) }}</span></td>
                                        <td class="text-end fw-medium" style="color:{{ $mv->quantity >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' }};">
                                            {{ $mv->quantity >= 0 ? '+' : '' }}{{ $mv->quantity }}
                                        </td>
                                        <td><small style="color:var(--text-muted);">{{ Str::limit($mv->notes, 40) ?? '—' }}</small></td>
                                        <td style="color:var(--text-muted);">{{ $mv->created_at->format('d M H:i') }}</td>
                                        <td>{{ $mv->creator?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="small mb-0" style="color:var(--text-muted);">No stock movements yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const qtyInput = document.getElementById('quantity');
    const preview = document.getElementById('newStockPreview');
    const currentStock = {{ $currentStock }};

    function updatePreview() {
        const qty = parseInt(qtyInput.value) || 0;
        const newStock = currentStock + qty;
        preview.textContent = newStock;
        preview.style.color = newStock < 0 ? 'var(--accent-danger)' : 'var(--accent-success)';
    }

    qtyInput.addEventListener('input', updatePreview);

    document.getElementById('btnPlus').addEventListener('click', function() {
        qtyInput.value = Math.abs(parseInt(qtyInput.value) || 0) || 1;
        updatePreview();
    });

    document.getElementById('btnMinus').addEventListener('click', function() {
        const val = Math.abs(parseInt(qtyInput.value) || 0) || 1;
        qtyInput.value = -val;
        updatePreview();
    });
});
</script>
@endpush
