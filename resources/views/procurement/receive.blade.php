@extends('layouts.app')
@section('title', 'Receive Procurement #' . $procurementRequest->id . ' — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header mb-3">
        <div>
            <h1 class="page-title"><i class="bi bi-box-arrow-in-down me-2"></i>Receive Procurement <span class="code-tag">#{{ $procurementRequest->id }}</span></h1>
            <p class="page-subtitle">Three-way reconciliation: PO vs Invoice vs Physical Count</p>
        </div>
        <a href="{{ route('procurement.show', $procurementRequest) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
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

    {{-- PO Summary --}}
    <div class="glass-card p-3 mb-4" style="border-left:3px solid var(--accent-info);">
        <div class="d-flex flex-wrap gap-4">
            <div><strong><i class="bi bi-building me-1"></i>Department:</strong> {{ ucfirst($procurementRequest->department) }}</div>
            <div><strong><i class="bi bi-person me-1"></i>Requested By:</strong> {{ $procurementRequest->requester?->name ?? 'Unknown' }}</div>
            <div><strong><i class="bi bi-check-circle me-1"></i>Approved By:</strong> {{ $procurementRequest->approver?->name ?? 'Unknown' }}</div>
            <div><strong><i class="bi bi-hash me-1"></i>Items:</strong> {{ $procurementRequest->items->count() }}</div>
        </div>
    </div>

    {{-- Step Indicators --}}
    <div class="d-flex justify-content-center mb-4">
        <div class="d-flex align-items-center gap-2">
            <span class="step-indicator active" id="stepBadge1" style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;background:var(--accent-primary);color:#fff;">1</span>
            <span class="small fw-medium" id="stepLabel1">Invoice</span>
            <span style="width:40px;height:2px;background:var(--glass-border);display:block;" id="stepLine1"></span>
            <span class="step-indicator" id="stepBadge2" style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;background:var(--glass-border);color:var(--text-muted);">2</span>
            <span class="small" id="stepLabel2" style="color:var(--text-muted);">Physical Count</span>
            <span style="width:40px;height:2px;background:var(--glass-border);display:block;" id="stepLine2"></span>
            <span class="step-indicator" id="stepBadge3" style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;background:var(--glass-border);color:var(--text-muted);">3</span>
            <span class="small" id="stepLabel3" style="color:var(--text-muted);">Review & Confirm</span>
        </div>
    </div>

    <form action="{{ route('procurement.receive.store', $procurementRequest) }}" method="POST" id="receiptForm">
        @csrf

        {{-- ═══════════ STEP 1: Supplier Invoice ═══════════ --}}
        <div id="step1" class="wizard-step">
            <div class="glass-card mb-4">
                <h5 class="fw-bold mb-2"><i class="bi bi-file-earmark-arrow-up me-2" style="color:var(--accent-info);"></i>Step 1: Supplier Invoice</h5>
                <p style="color:var(--text-muted);" class="mb-3">Upload the supplier's invoice. Optionally use OCR to extract text, then manually enter invoice quantities and prices.</p>

                <div class="row g-4">
                    <div class="col-lg-6">
                        {{-- File Upload --}}
                        <div class="mb-3">
                            <label class="form-label">Upload Invoice (PDF, JPG, PNG - max 10MB)</label>
                            <input type="file" id="invoiceFile" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="invoiceUploadStatus" class="mt-2" style="display:none;"></div>
                        </div>

                        {{-- OCR Panel (images only) --}}
                        <div id="ocrPanel" style="display:none;">
                            <x-invoice-ocr id="receipt-ocr" />
                        </div>
                    </div>
                    <div class="col-lg-6">
                        {{-- Invoice Items Table --}}
                        <label class="form-label">Invoice Data (enter from supplier invoice)</label>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th style="width:90px;">PO Qty</th>
                                        <th style="width:100px;">Invoice Qty</th>
                                        <th style="width:120px;">Invoice Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($procurementRequest->items as $item)
                                        <tr>
                                            <td>
                                                <strong>{{ $item->inventoryItem?->name ?? 'Unknown' }}</strong>
                                                <small class="d-block" style="color:var(--text-muted);">{{ $item->inventoryItem?->unit ?? '' }}</small>
                                            </td>
                                            <td>{{ $item->quantity_requested }}</td>
                                            <td>
                                                <input type="number" name="quantities_invoiced[{{ $item->id }}]"
                                                    class="form-control form-control-sm inv-qty"
                                                    data-item-id="{{ $item->id }}"
                                                    value="{{ old('quantities_invoiced.' . $item->id, $item->quantity_requested) }}"
                                                    min="0">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="unit_prices_invoiced[{{ $item->id }}]"
                                                    class="form-control form-control-sm inv-price"
                                                    data-item-id="{{ $item->id }}"
                                                    value="{{ old('unit_prices_invoiced.' . $item->id, $item->quoted_unit_price) }}"
                                                    min="0">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <small style="color:var(--text-muted);">Pre-filled from PO. Update based on actual supplier invoice.</small>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-primary" onclick="wizard.goTo(2)">Next: Physical Count <i class="bi bi-arrow-right ms-1"></i></button>
            </div>
        </div>

        {{-- ═══════════ STEP 2: Physical Count via Scanning ═══════════ --}}
        <div id="step2" class="wizard-step" style="display:none;">
            <div class="glass-card mb-4">
                <h5 class="fw-bold mb-2"><i class="bi bi-upc-scan me-2" style="color:var(--accent-warning);"></i>Step 2: Physical Count</h5>
                <p style="color:var(--text-muted);" class="mb-3">Scan barcodes or manually enter the actual quantities received and prices.</p>

                <div class="row g-4 mb-4">
                    <div class="col-lg-5">
                        <x-barcode-scanner id="receipt-scanner" modes="usb,camera" placeholder="Scan item barcode..." />
                        <div id="scanMatchResult" class="mt-2" style="display:none;"></div>
                    </div>
                    <div class="col-lg-7">
                        <div class="glass-card" style="background:rgba(var(--accent-info-rgb),0.05);">
                            <small style="color:var(--text-muted);"><i class="bi bi-info-circle me-1"></i>Scanning a barcode will find the matching item below and increment its received quantity by 1. You can also manually type quantities.</small>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Barcode</th>
                                <th style="width:90px;">PO Qty</th>
                                <th style="width:100px;">Inv. Qty</th>
                                <th style="width:110px;">Received *</th>
                                <th style="width:130px;">PO Price</th>
                                <th style="width:140px;">Actual Price *</th>
                                <th style="width:110px;">Subtotal</th>
                                <th style="width:60px;">Match</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($procurementRequest->items as $index => $item)
                                @php
                                    $quotedPrice = $item->quoted_unit_price;
                                    $defaultPrice = old('unit_prices.' . $item->id, $quotedPrice ?? $item->inventoryItem?->purchase_price ?? '');
                                    $barcode = $item->inventoryItem?->barcode ?? '';
                                @endphp
                                <tr id="row-{{ $item->id }}" data-barcode="{{ $barcode }}" data-item-id="{{ $item->id }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <strong>{{ $item->inventoryItem?->name ?? 'Unknown' }}</strong>
                                        <small class="d-block" style="color:var(--text-muted);">{{ $item->inventoryItem?->unit ?? '' }}</small>
                                    </td>
                                    <td><small style="color:var(--text-muted);">{{ $barcode ?: '—' }}</small></td>
                                    <td class="text-center">{{ $item->quantity_requested }}</td>
                                    <td class="text-center inv-qty-display" data-item-id="{{ $item->id }}">{{ $item->quantity_requested }}</td>
                                    <td>
                                        <input type="number" name="quantities_received[{{ $item->id }}]"
                                            class="form-control form-control-sm qty-received"
                                            data-item-id="{{ $item->id }}"
                                            data-po-qty="{{ $item->quantity_requested }}"
                                            data-row="{{ $index }}"
                                            value="{{ old('quantities_received.' . $item->id, $item->quantity_requested) }}"
                                            min="0" required>
                                    </td>
                                    <td>
                                        @if($quotedPrice)
                                            <span style="color:var(--text-muted);">{{ currency($quotedPrice) }}</span>
                                        @else
                                            <span style="color:var(--text-muted);font-style:italic;">Not set</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">{{ currency_symbol() }}</span>
                                            <input type="number" name="unit_prices[{{ $item->id }}]"
                                                class="form-control unit-price"
                                                step="0.01" min="0.01"
                                                data-row="{{ $index }}"
                                                data-quoted="{{ $quotedPrice ?? '' }}"
                                                data-item-id="{{ $item->id }}"
                                                value="{{ $defaultPrice }}"
                                                required>
                                        </div>
                                    </td>
                                    <td class="subtotal fw-semibold" id="subtotal-{{ $index }}" style="color:var(--accent-success);">—</td>
                                    <td class="text-center match-icon" id="match-{{ $item->id }}">
                                        <i class="bi bi-dash" style="color:var(--text-muted);"></i>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="border-top:2px solid var(--glass-border);">
                                <td colspan="8" class="text-end"><strong>Total:</strong></td>
                                <td id="grandTotal"><strong style="color:var(--accent-warning);">—</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" onclick="wizard.goTo(1)"><i class="bi bi-arrow-left me-1"></i>Back: Invoice</button>
                <button type="button" class="btn btn-primary" onclick="wizard.goTo(3)">Next: Review <i class="bi bi-arrow-right ms-1"></i></button>
            </div>
        </div>

        {{-- ═══════════ STEP 3: Reconciliation Review ═══════════ --}}
        <div id="step3" class="wizard-step" style="display:none;">
            <div class="glass-card mb-4">
                <h5 class="fw-bold mb-2"><i class="bi bi-clipboard-check me-2" style="color:var(--accent-success);"></i>Step 3: Review & Confirm</h5>
                <p style="color:var(--text-muted);" class="mb-3">Review the three-way reconciliation. Discrepancies are highlighted in red.</p>

                <div id="reconciliationSummary"></div>
            </div>

            <div id="discrepancyWarning" class="glass-card mb-4" style="display:none;border-left:3px solid var(--accent-danger);background:rgba(var(--accent-danger-rgb),0.05);">
                <h6 style="color:var(--accent-danger);"><i class="bi bi-exclamation-triangle-fill me-2"></i>Discrepancies Detected</h6>
                <p style="color:var(--text-muted);" class="mb-0">Some quantities or prices don't match across PO, Invoice, and Physical Count. Review carefully before confirming.</p>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" onclick="wizard.goTo(2)"><i class="bi bi-arrow-left me-1"></i>Back: Physical Count</button>
                <button type="submit" class="btn btn-success btn-lg" id="btnConfirm">
                    <i class="bi bi-check-circle me-1"></i>Confirm Receipt & Update Stock
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Wizard Navigation ──
    const wizard = {
        current: 1,
        goTo: function(step) {
            document.getElementById('step' + this.current).style.display = 'none';
            document.getElementById('step' + step).style.display = 'block';

            // Update step indicators
            for (let i = 1; i <= 3; i++) {
                const badge = document.getElementById('stepBadge' + i);
                const label = document.getElementById('stepLabel' + i);
                if (i <= step) {
                    badge.style.background = 'var(--accent-primary)';
                    badge.style.color = '#fff';
                    label.style.color = 'var(--text-primary)';
                    label.classList.add('fw-medium');
                } else {
                    badge.style.background = 'var(--glass-border)';
                    badge.style.color = 'var(--text-muted)';
                    label.style.color = 'var(--text-muted)';
                    label.classList.remove('fw-medium');
                }
                if (i < step) {
                    badge.innerHTML = '<i class="bi bi-check-lg"></i>';
                } else {
                    badge.textContent = i;
                }
            }

            // Update connector lines
            for (let i = 1; i <= 2; i++) {
                const line = document.getElementById('stepLine' + i);
                line.style.background = i < step ? 'var(--accent-primary)' : 'var(--glass-border)';
            }

            this.current = step;

            if (step === 2) calculateTotals();
            if (step === 3) buildReconciliation();
        }
    };
    window.wizard = wizard;

    // ── Step 1: Invoice Upload + OCR ──
    const invoiceFile = document.getElementById('invoiceFile');
    const ocrPanel = document.getElementById('ocrPanel');
    const uploadStatus = document.getElementById('invoiceUploadStatus');

    invoiceFile.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;

        // Upload via AJAX
        const formData = new FormData();
        formData.append('receipt_invoice', file);

        uploadStatus.style.display = 'block';
        uploadStatus.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div> Uploading...';

        axios.post('{{ route("procurement.upload-invoice", $procurementRequest) }}', formData)
            .then(function(response) {
                uploadStatus.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Invoice uploaded successfully.</span>';

                // Show OCR panel for image files
                if (file.type.startsWith('image/')) {
                    ocrPanel.style.display = 'block';
                    // Pass the file to the OCR component
                    const ocrInput = ocrPanel.querySelector('input[type="file"]');
                    if (ocrInput) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        ocrInput.files = dt.files;
                        ocrInput.dispatchEvent(new Event('change'));
                    }
                }
            })
            .catch(function(err) {
                const msg = err.response?.data?.message || 'Upload failed.';
                uploadStatus.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + msg + '</span>';
            });
    });

    // Sync invoice qty to Step 2 display
    document.querySelectorAll('.inv-qty').forEach(function(input) {
        input.addEventListener('input', function() {
            const display = document.querySelector('.inv-qty-display[data-item-id="' + this.dataset.itemId + '"]');
            if (display) display.textContent = this.value || '—';
        });
    });

    // ── Step 2: Scanner + Calculations ──
    document.addEventListener('barcode-scanned', function(e) {
        if (e.detail.scannerId !== 'receipt-scanner') return;
        const code = e.detail.code;
        const resultDiv = document.getElementById('scanMatchResult');
        resultDiv.style.display = 'block';

        // Find matching row by barcode
        const row = document.querySelector('tr[data-barcode="' + code + '"]');
        if (row) {
            const itemId = row.dataset.itemId;
            const qtyInput = row.querySelector('.qty-received');
            qtyInput.value = parseInt(qtyInput.value || 0) + 1;
            qtyInput.dispatchEvent(new Event('input'));

            const itemName = row.querySelector('strong').textContent;
            resultDiv.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Matched: <strong>' + itemName + '</strong> — qty now ' + qtyInput.value + '</span>';

            row.style.background = 'rgba(var(--accent-success-rgb),0.08)';
            setTimeout(function() { row.style.background = ''; }, 1500);
        } else {
            resultDiv.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Barcode <strong>' + code + '</strong> not found in this PO.</span>';
        }
    });

    function calculateTotals() {
        let total = 0;
        document.querySelectorAll('.unit-price').forEach(function(input) {
            const qty = parseInt(document.querySelector('.qty-received[data-item-id="' + input.dataset.itemId + '"]')?.value) || 0;
            const price = parseFloat(input.value) || 0;
            const sub = qty * price;
            total += sub;
            document.getElementById('subtotal-' + input.dataset.row).textContent = '{{ currency_symbol() }}' + sub.toFixed(2);

            // Match icon
            const itemId = input.dataset.itemId;
            const poQty = parseInt(document.querySelector('.qty-received[data-item-id="' + itemId + '"]')?.dataset.poQty) || 0;
            const recQty = qty;
            const matchIcon = document.getElementById('match-' + itemId);
            if (matchIcon) {
                if (recQty === poQty) {
                    matchIcon.innerHTML = '<i class="bi bi-check-circle-fill" style="color:var(--accent-success);"></i>';
                } else if (recQty < poQty) {
                    matchIcon.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="color:var(--accent-danger);"></i>';
                } else {
                    matchIcon.innerHTML = '<i class="bi bi-arrow-up-circle-fill" style="color:var(--accent-warning);"></i>';
                }
            }
        });
        document.getElementById('grandTotal').innerHTML = '<strong style="color:var(--accent-warning);">{{ currency_symbol() }}' + total.toFixed(2) + '</strong>';
    }

    document.querySelectorAll('.unit-price, .qty-received').forEach(function(input) {
        input.addEventListener('input', calculateTotals);
    });

    // ── Step 3: Build Reconciliation Summary ──
    function buildReconciliation() {
        @php
            $receiptItemsJson = $procurementRequest->items->map(fn($i) => [
                'id'       => $i->id,
                'name'     => $i->inventoryItem?->name ?? 'Unknown',
                'unit'     => $i->inventoryItem?->unit ?? '',
                'po_qty'   => $i->quantity_requested,
                'po_price' => $i->quoted_unit_price,
            ]);
        @endphp
        const items = @json($receiptItemsJson);

        let html = '<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr>';
        html += '<th>Item</th><th class="text-center">PO Qty</th><th class="text-center">Invoice Qty</th><th class="text-center">Received Qty</th>';
        html += '<th class="text-end">PO Price</th><th class="text-end">Invoice Price</th><th class="text-end">Actual Price</th><th class="text-end">Total</th><th>Status</th>';
        html += '</tr></thead><tbody>';

        let hasDiscrepancy = false;
        let grandTotal = 0;

        items.forEach(function(item) {
            const invQty = parseInt(document.querySelector('.inv-qty[data-item-id="' + item.id + '"]')?.value) || 0;
            const invPrice = parseFloat(document.querySelector('.inv-price[data-item-id="' + item.id + '"]')?.value) || 0;
            const recQty = parseInt(document.querySelector('.qty-received[data-item-id="' + item.id + '"]')?.value) || 0;
            const actPrice = parseFloat(document.querySelector('.unit-price[data-item-id="' + item.id + '"]')?.value) || 0;
            const total = recQty * actPrice;
            grandTotal += total;

            const qtyMatch = item.po_qty === invQty && invQty === recQty;
            const priceMatch = (!item.po_price || Math.abs(actPrice - item.po_price) < 0.02) && Math.abs(actPrice - invPrice) < 0.02;
            const allMatch = qtyMatch && priceMatch;
            if (!allMatch) hasDiscrepancy = true;

            const dangerStyle = 'style="color:var(--accent-danger);font-weight:700;"';
            const successStyle = 'style="color:var(--accent-success);"';

            html += '<tr' + (!allMatch ? ' style="background:rgba(var(--accent-danger-rgb),0.05);"' : '') + '>';
            html += '<td><strong>' + item.name + '</strong><br><small style="color:var(--text-muted);">' + item.unit + '</small></td>';

            // Quantities
            html += '<td class="text-center">' + item.po_qty + '</td>';
            html += '<td class="text-center" ' + (invQty !== item.po_qty ? dangerStyle : '') + '>' + invQty + '</td>';
            html += '<td class="text-center" ' + (recQty !== item.po_qty ? dangerStyle : '') + '>' + recQty + '</td>';

            // Prices
            html += '<td class="text-end">' + (item.po_price ? '{{ currency_symbol() }}' + parseFloat(item.po_price).toFixed(2) : '—') + '</td>';
            html += '<td class="text-end" ' + (item.po_price && Math.abs(invPrice - item.po_price) > 0.01 ? dangerStyle : '') + '>{{ currency_symbol() }}' + invPrice.toFixed(2) + '</td>';
            html += '<td class="text-end">{{ currency_symbol() }}' + actPrice.toFixed(2) + '</td>';
            html += '<td class="text-end fw-bold">{{ currency_symbol() }}' + total.toFixed(2) + '</td>';

            // Status
            if (allMatch) {
                html += '<td><span class="badge-glass" ' + successStyle + '><i class="bi bi-check-circle me-1"></i>Match</span></td>';
            } else {
                html += '<td><span class="badge-glass" ' + dangerStyle + '><i class="bi bi-exclamation-triangle me-1"></i>Mismatch</span></td>';
            }
            html += '</tr>';
        });

        html += '</tbody><tfoot><tr style="border-top:2px solid var(--glass-border);">';
        html += '<td colspan="7" class="text-end"><strong>Grand Total:</strong></td>';
        html += '<td class="text-end"><strong style="color:var(--accent-warning);">{{ currency_symbol() }}' + grandTotal.toFixed(2) + '</strong></td><td></td>';
        html += '</tr></tfoot></table></div>';

        document.getElementById('reconciliationSummary').innerHTML = html;
        document.getElementById('discrepancyWarning').style.display = hasDiscrepancy ? 'block' : 'none';
    }

    // Initial calculation
    calculateTotals();
});
</script>
@endpush
