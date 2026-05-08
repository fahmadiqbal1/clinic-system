@extends('layouts.app')
@section('title', 'Review Price List — ' . config('app.name'))

@push('styles')
<style>
/* Price-list review: table rows and inputs need dark text — the global theme is white-on-dark */
#reviewWrapper table td,
#reviewWrapper table th {
    color: #e2e8f0 !important;
}
#reviewWrapper .table-warning td,
#reviewWrapper .table-warning th {
    color: #1a1a2e !important;
}
#reviewWrapper .table-warning .badge {
    color: inherit;
}
#reviewWrapper .form-control,
#reviewWrapper .form-select {
    color: #1a1a2e !important;
    background-color: #f8f9fa !important;
    border-color: #ced4da !important;
}
#reviewWrapper .form-control::placeholder {
    color: #6c757d !important;
}
#reviewWrapper .form-control.is-invalid {
    border-color: #dc3545 !important;
}
#reviewWrapper .input-group-text {
    color: #1a1a2e !important;
    background-color: #e9ecef !important;
    border-color: #ced4da !important;
}
</style>
@endpush

@section('content')
<div class="container mt-4 fade-in">

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-file-earmark-check me-2" style="color:var(--accent-primary);"></i>Review Price List</h2>
            <p class="page-subtitle mb-0">
                {{ $priceList->vendor->name }} &mdash; {{ $priceList->original_filename }}
                <span class="badge bg-secondary ms-2">{{ $priceList->item_count ?? 0 }} items</span>
                @if($priceList->flagged_count)
                    <span class="badge bg-warning text-dark ms-1">{{ $priceList->flagged_count }} flagged</span>
                @endif
            </p>
        </div>
        <a href="{{ route('owner.vendors.edit', $priceList->vendor) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Vendor
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @php
        $matched   = $priceList->items->filter(fn($i) => $i->inventory_item_id && !$i->applied);
        $unmatched = $priceList->items->filter(fn($i) => !$i->inventory_item_id && !$i->applied && !$i->test_name_normalized);
        $labItems  = $priceList->items->filter(fn($i) => $i->test_name_normalized && !$i->applied);
        $applied   = $priceList->items->filter(fn($i) => $i->applied);
    @endphp

    @if($priceList->items->isEmpty())
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="mt-3 text-muted">No items were extracted from this price list yet. Check back after processing completes.</p>
            </div>
        </div>
    @else
        {{-- No real form POST — JS collects data and sends JSON to avoid max_input_vars --}}
        <div id="reviewWrapper">
        @php $applyUrl = route('owner.vendors.price-list.apply', $priceList); @endphp

            {{-- Matched items (price update) --}}
            @if($matched->isNotEmpty())
            <div class="card mb-4 fade-in delay-1">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="badge bg-success">{{ $matched->count() }}</span>
                    <span class="fw-semibold">Matched Inventory Items</span>
                    <small class="text-muted ms-1">— will update purchase price only</small>
                    <div class="ms-auto">
                        <div class="form-check mb-0">
                            <input class="form-check-input select-group" type="checkbox" data-group="matched" id="selectMatched">
                            <label class="form-check-label small" for="selectMatched">Select all</label>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>SKU</th>
                                    <th>Description</th>
                                    <th>Pack Size</th>
                                    <th>Matched Item</th>
                                    <th>Trade Price</th>
                                    <th>Current Price</th>
                                    <th>Δ Change</th>
                                    <th>Confidence</th>
                                    <th style="width:80px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($matched as $item)
                                @php
                                    $pct   = (int) round($item->confidence * 100);
                                    $delta = $item->current_price !== null && $item->detected_price !== null
                                           ? $item->detected_price - $item->current_price : null;
                                @endphp
                                <tr class="{{ $item->needs_review ? 'table-warning' : '' }}">
                                    <td>
                                        <input class="form-check-input item-checkbox matched-cb" type="checkbox"
                                               name="approved_items[]" value="{{ $item->id }}">
                                    </td>
                                    <td class="font-monospace small">{{ $item->sku_detected ?? '—' }}</td>
                                    <td class="fw-medium">{{ $item->item_name }}</td>
                                    <td class="small text-muted">{{ $item->pack_size ?? $item->unit_detected ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-link-45deg"></i> {{ $item->inventoryItem->name }}
                                        </span>
                                    </td>
                                    <td class="fw-semibold">
                                        @if($item->needs_review || ($item->detected_price ?? 0) == 0)
                                            <div class="input-group input-group-sm" style="min-width:130px;">
                                                <span class="input-group-text">PKR</span>
                                                <input type="number" step="0.01" min="0"
                                                       class="form-control item-price-input"
                                                       data-item-id="{{ $item->id }}"
                                                       value="{{ $item->detected_price > 0 ? $item->detected_price : '' }}"
                                                       placeholder="Enter price">
                                            </div>
                                        @else
                                            PKR {{ number_format($item->detected_price, 2) }}
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
                                        @if($delta !== null)
                                            <span class="{{ $delta > 0 ? 'text-danger' : ($delta < 0 ? 'text-success' : 'text-muted') }}">
                                                {{ $delta >= 0 ? '+' : '' }}{{ number_format($delta, 2) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $pct >= 80 ? 'bg-success' : ($pct >= 60 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                            {{ $pct }}%
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger deny-btn" data-item-id="{{ $item->id }}" title="Deny — do not update this item">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Unmatched items (new inventory creation) --}}
            @if($unmatched->isNotEmpty())
            <div class="card mb-4 fade-in delay-2">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="badge bg-primary">{{ $unmatched->count() }}</span>
                    <span class="fw-semibold">New Items — Not in Inventory</span>
                    <small class="text-muted ms-1">— will be <strong>created</strong> in inventory, tagged to this vendor</small>
                    <div class="ms-auto">
                        <div class="form-check mb-0">
                            <input class="form-check-input select-group" type="checkbox" data-group="new" id="selectNew">
                            <label class="form-check-label small" for="selectNew">Select all</label>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="alert alert-info m-3 d-flex align-items-start gap-2 mb-0">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <span>These items have no match in your inventory. Approving them will create new inventory entries tagged to <strong>{{ $priceList->vendor->name }}</strong>. Set the department before applying. Selling price must be set by the department head separately.</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>SKU</th>
                                    <th>Description</th>
                                    <th>Pack Size</th>
                                    <th>Trade Price</th>
                                    <th>Prev Price</th>
                                    <th>Δ Change</th>
                                    <th>Department</th>
                                    <th>Confidence</th>
                                    <th>Review?</th>
                                    <th style="width:80px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($unmatched as $item)
                                @php
                                    $pct            = (int) round($item->confidence * 100);
                                    $defaultDept    = $priceList->vendor->category === 'pharmaceutical' ? 'pharmacy' : ($priceList->vendor->category === 'lab_supplies' ? 'lab' : 'general');
                                    $prevPriceKey   = $item->sku_detected ? 'sku:' . strtoupper(trim($item->sku_detected)) : null;
                                    $prevPrice      = ($prevPriceKey && $previousPrices->has($prevPriceKey))
                                                        ? $previousPrices->get($prevPriceKey)
                                                        : $previousPrices->get('name:' . strtolower(trim($item->item_name)));
                                    $unmatchedDelta = ($prevPrice !== null && $item->detected_price !== null)
                                                        ? $item->detected_price - $prevPrice : null;
                                @endphp
                                <tr class="{{ $item->needs_review ? 'table-warning' : '' }}">
                                    <td>
                                        <input class="form-check-input item-checkbox new-cb" type="checkbox"
                                               name="approved_items[]" value="{{ $item->id }}">
                                    </td>
                                    <td class="font-monospace small">{{ $item->sku_detected ?? '—' }}</td>
                                    <td class="fw-medium">
                                        {{ $item->item_name }}
                                        <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:0.68rem;">New</span>
                                    </td>
                                    <td class="small text-muted">{{ $item->pack_size ?? $item->unit_detected ?? '—' }}</td>
                                    <td class="fw-semibold">
                                        @if($item->needs_review || ($item->detected_price ?? 0) == 0)
                                            <div class="input-group input-group-sm" style="min-width:130px;">
                                                <span class="input-group-text">PKR</span>
                                                <input type="number" step="0.01" min="0"
                                                       class="form-control item-price-input"
                                                       data-item-id="{{ $item->id }}"
                                                       value="{{ ($item->detected_price ?? 0) > 0 ? $item->detected_price : '' }}"
                                                       placeholder="Enter price">
                                            </div>
                                        @else
                                            PKR {{ number_format($item->detected_price, 2) }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($prevPrice !== null)
                                            <span class="text-muted">PKR {{ number_format($prevPrice, 2) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($unmatchedDelta !== null)
                                            <span class="{{ $unmatchedDelta > 0 ? 'text-danger' : ($unmatchedDelta < 0 ? 'text-success' : 'text-muted') }}">
                                                {{ $unmatchedDelta >= 0 ? '+' : '' }}{{ number_format($unmatchedDelta, 2) }}
                                            </span>
                                        @else
                                            <span class="text-muted">New</span>
                                        @endif
                                    </td>
                                    <td>
                                        <select name="item_departments[{{ $item->id }}]" class="form-select form-select-sm" style="min-width:120px;">
                                            <option value="pharmacy" {{ $defaultDept === 'pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                                            <option value="lab"      {{ $defaultDept === 'lab'      ? 'selected' : '' }}>Laboratory</option>
                                            <option value="radiology">Radiology</option>
                                            <option value="general">General</option>
                                        </select>
                                    </td>
                                    <td>
                                        <span class="badge {{ $pct >= 80 ? 'bg-success' : ($pct >= 60 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                            {{ $pct }}%
                                        </span>
                                    </td>
                                    <td>
                                        @if($item->needs_review)
                                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Review</span>
                                        @else
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">OK</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger deny-btn" data-item-id="{{ $item->id }}" title="Deny — do not create inventory entry">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- External lab test prices --}}
            @if($labItems->isNotEmpty())
            <div class="card mb-4 fade-in delay-2">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="badge bg-info">{{ $labItems->count() }}</span>
                    <span class="fw-semibold">External Lab Test Prices</span>
                    <div class="ms-auto">
                        <div class="form-check mb-0">
                            <input class="form-check-input select-group" type="checkbox" data-group="lab" id="selectLab">
                            <label class="form-check-label small" for="selectLab">Select all</label>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Test Name</th>
                                    <th>Detected Price</th>
                                    <th>Confidence</th>
                                    <th style="width:80px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($labItems as $item)
                                @php $pct = (int) round($item->confidence * 100); @endphp
                                <tr>
                                    <td>
                                        <input class="form-check-input item-checkbox lab-cb" type="checkbox"
                                               name="approved_items[]" value="{{ $item->id }}">
                                    </td>
                                    <td class="fw-medium">{{ $item->item_name }}</td>
                                    <td class="fw-semibold">
                                        @if($item->needs_review || ($item->detected_price ?? 0) == 0)
                                            <div class="input-group input-group-sm" style="min-width:130px;">
                                                <span class="input-group-text">PKR</span>
                                                <input type="number" step="0.01" min="0"
                                                       class="form-control item-price-input"
                                                       data-item-id="{{ $item->id }}"
                                                       value="{{ ($item->detected_price ?? 0) > 0 ? $item->detected_price : '' }}"
                                                       placeholder="Enter price">
                                            </div>
                                        @else
                                            PKR {{ number_format($item->detected_price, 2) }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $pct >= 80 ? 'bg-success' : ($pct >= 60 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                            {{ $pct }}%
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger deny-btn" data-item-id="{{ $item->id }}" title="Deny — do not create lab test price">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Already-applied items --}}
            @if($applied->isNotEmpty())
            <div class="card mb-4 fade-in delay-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="badge bg-secondary">{{ $applied->count() }}</span>
                    <span class="fw-semibold text-muted">Already Applied</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" style="opacity:.65;">
                            <thead class="table-light">
                                <tr><th>Item Name</th><th>Applied Price</th><th>Applied By</th></tr>
                            </thead>
                            <tbody>
                                @foreach($applied as $item)
                                <tr>
                                    <td>{{ $item->item_name }}</td>
                                    <td>PKR {{ number_format($item->detected_price, 2) }}</td>
                                    <td><small class="text-muted">{{ $item->reviewer?->name ?? '—' }} {{ $item->reviewed_at?->format('d M Y') }}</small></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Sticky action bar --}}
            @if($matched->isNotEmpty() || $unmatched->isNotEmpty() || $labItems->isNotEmpty())
            <div class="card" style="position:sticky;bottom:1rem;z-index:100;">
                <div class="card-body py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="text-muted small">
                        <span id="selectedCount">0</span> item(s) selected
                    </div>
                    <div id="applyStatus" class="text-muted small d-none"></div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('owner.vendors.price-list.reject', $priceList) }}"
                              onsubmit="return confirm('Reject this entire price list? This cannot be undone.')">
                            @csrf
                            <input type="hidden" name="reason" value="Rejected by owner from review page">
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-trash me-1"></i>Reject Entire List
                            </button>
                        </form>
                        <button type="button" id="applyBtn" class="btn btn-primary" onclick="applySelected()">
                            <i class="bi bi-check2-all me-1"></i>Apply Selected Items
                        </button>
                    </div>
                </div>
            </div>
            @endif

        </div>{{-- #reviewWrapper --}}
    @endif
</div>

@push('scripts')
<script>
(function () {
    // Track denied item IDs
    const deniedIds = new Set();

    // Deny button handler
    document.querySelectorAll('.deny-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = parseInt(btn.dataset.itemId, 10);
            const row = btn.closest('tr');
            if (deniedIds.has(id)) {
                // Un-deny
                deniedIds.delete(id);
                row.classList.remove('table-danger');
                row.style.opacity = '';
                btn.innerHTML = '<i class="bi bi-x-lg"></i>';
                btn.title = 'Deny';
                // Re-enable checkbox
                const cb = row.querySelector('.item-checkbox');
                if (cb) cb.disabled = false;
            } else {
                // Deny
                deniedIds.add(id);
                row.classList.remove('table-warning');
                row.classList.add('table-danger');
                row.style.opacity = '0.55';
                btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i>';
                btn.title = 'Undo deny';
                // Uncheck and disable checkbox
                const cb = row.querySelector('.item-checkbox');
                if (cb) { cb.checked = false; cb.disabled = true; }
            }
            updateCount();
        });
    });

    // Per-group select-all
    document.querySelectorAll('.select-group').forEach(function (groupCb) {
        const group = groupCb.dataset.group;
        const selector = group === 'matched' ? '.matched-cb' : (group === 'new' ? '.new-cb' : '.lab-cb');
        groupCb.addEventListener('change', function () {
            document.querySelectorAll(selector).forEach(cb => cb.checked = groupCb.checked);
            updateCount();
        });
    });

    document.querySelectorAll('.item-checkbox').forEach(cb => cb.addEventListener('change', updateCount));

    function updateCount() {
        const count = document.querySelectorAll('.item-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = count;
    }

    window.applySelected = function () {
        const approvedItems = [];
        const itemDepartments = {};
        const itemPrices = {};
        const missingPrices = [];

        document.querySelectorAll('.item-checkbox:checked').forEach(function (cb) {
            const id = parseInt(cb.value, 10);
            approvedItems.push(id);

            const deptSelect = document.querySelector('[name="item_departments[' + id + ']"]');
            if (deptSelect && deptSelect.value) {
                itemDepartments[id] = deptSelect.value;
            }

            const priceInput = document.querySelector('.item-price-input[data-item-id="' + id + '"]');
            if (priceInput) {
                const val = parseFloat(priceInput.value);
                if (!val || val <= 0) {
                    missingPrices.push(priceInput.closest('tr').querySelector('td:nth-child(2)')?.textContent?.trim() || 'Item #' + id);
                    priceInput.classList.add('is-invalid');
                } else {
                    priceInput.classList.remove('is-invalid');
                    itemPrices[id] = val;
                }
            }
        });

        if (approvedItems.length === 0 && deniedIds.size === 0) {
            alert('No items selected or denied. Please approve or deny at least one item.');
            return;
        }

        if (missingPrices.length > 0) {
            alert('Please enter a price for the following flagged items before applying:\n\n' + missingPrices.slice(0, 10).join('\n') + (missingPrices.length > 10 ? '\n…and ' + (missingPrices.length - 10) + ' more' : ''));
            return;
        }

        const btn    = document.getElementById('applyBtn');
        const status = document.getElementById('applyStatus');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying…';
        status.textContent = 'Sending ' + approvedItems.length + ' items…';
        status.classList.remove('d-none');

        fetch('{{ $applyUrl }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                approved_items: approvedItems,
                item_departments: itemDepartments,
                item_prices: itemPrices,
                denied_items: Array.from(deniedIds)
            })
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Apply Selected Items';
            if (res.ok && res.data.success) {
                status.textContent = '';
                status.classList.add('d-none');
                // Show success banner then redirect to vendor page
                const banner = document.createElement('div');
                banner.className = 'alert alert-success alert-dismissible fade show mb-3';
                banner.innerHTML = '<i class="bi bi-check2-circle me-1"></i><strong>' + res.data.count + ' item(s) applied</strong> — redirecting…<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                document.querySelector('.container').prepend(banner);
                setTimeout(function () { window.location.href = res.data.redirect; }, 1500);
            } else {
                status.textContent = res.data.message || res.data.error || 'An error occurred.';
                status.className = 'text-danger small';
            }
        })
        .catch(function (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Apply Selected Items';
            status.textContent = 'Network error: ' + err.message;
            status.className = 'text-danger small';
        });
    };
}());
</script>
@endpush
@endsection
