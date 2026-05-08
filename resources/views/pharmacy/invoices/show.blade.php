@extends('layouts.app')
@section('title', 'Pharmacy Order #' . $invoice->id . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Print Header --}}
    <div class="print-header">
        <h2>{{ config('app.name') }}</h2>
        <p>Pharmacy Order #{{ $invoice->id }} &mdash; {{ $invoice->created_at?->format('M d, Y') }}</p>
    </div>

    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-capsule me-2" style="color:var(--accent-success);"></i>Pharmacy Order #{{ $invoice->id }}</h2>
            <p class="page-subtitle mb-0">Medication Dispensing Order</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-outline-success btn-sm" data-no-disable="true"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
            <button onclick="window.print()" class="btn btn-outline-info btn-sm" data-no-disable="true"><i class="bi bi-printer me-1"></i>Print</button>
            <a href="{{ route('pharmacy.invoices.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Orders</a>
        </div>
    </div>

    {{-- Status & Info --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-info-circle me-2" style="color:var(--accent-info);"></i>Order Details</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Status</span>
                    @php
                        $sStyle = match($invoice->status) {
                            'completed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            'paid' => 'background:rgba(var(--accent-primary-rgb),0.15);color:var(--accent-primary);',
                            'cancelled' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                            default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $sStyle }}">{{ ucfirst($invoice->status ?? 'pending') }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Order Date</span>
                    <span class="info-value">{{ $invoice->created_at?->format('M d, Y H:i') ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value">{{ $invoice->patient?->full_name ?? 'Unknown Patient' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value">{{ $invoice->patient_id ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Prescription/Service</span>
                    <span class="info-value">{{ $invoice->service_name ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Invoice Amount</span>
                    <span class="info-value stat-value glow-primary" style="font-size:1.2rem;">{{ currency($invoice->total_amount ?? 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Doctor / Referrer --}}
    @if($invoice->prescribing_doctor_id || $invoice->referrer_name)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-person-badge me-2" style="color:var(--accent-secondary);"></i>Prescriber Info</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px,1fr));">
                @if($invoice->prescribing_doctor_id)
                <div class="info-grid-item">
                    <span class="info-label">Prescribed by Doctor</span>
                    <span class="info-value">{{ $invoice->prescribingDoctor?->name ?? 'N/A' }}</span>
                </div>
                @endif
                @if($invoice->referrer_name)
                <div class="info-grid-item">
                    <span class="info-label">Drug Referrer</span>
                    <span class="info-value">{{ $invoice->referrer_name ?? 'N/A' }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Dispensed Items --}}
    @if($invoice->items->count() > 0)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-box-seam me-2" style="color:var(--accent-success);"></i>Dispensed Items</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            @if(Auth::user()->hasRole('Owner'))
                            <th class="text-end">Cost (WAC)</th>
                            @endif
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-end">{{ currency($item->unit_price) }}</td>
                                @if(Auth::user()->hasRole('Owner'))
                                <td class="text-end" style="color:var(--text-muted);">{{ currency($item->cost_price) }}</td>
                                @endif
                                <td class="text-end fw-medium">{{ currency($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Prescription Reference Panel --}}
    @if($invoice->isPaid() && $invoice->performed_by_user_id && !$invoice->items->count() && $prescriptionItems->count())
    <div class="card mb-4 fade-in delay-3" style="border:1px solid rgba(var(--accent-info-rgb),0.3);">
        <div class="card-header"><i class="bi bi-file-medical me-2" style="color:var(--accent-info);"></i>Prescribed Medicines
            @php $allInStock = $prescriptionItems->every(fn($i) => $i['in_stock']); @endphp
            @if($allInStock)
                <span class="badge ms-2" style="background:rgba(var(--accent-success-rgb),0.2);color:var(--accent-success);">All in stock</span>
            @else
                <span class="badge ms-2" style="background:rgba(var(--accent-warning-rgb),0.2);color:var(--accent-warning);">Some unavailable</span>
            @endif
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Dosage</th>
                        <th class="text-center">Prescribed Qty</th>
                        <th class="text-center">In Stock</th>
                        <th>Inventory Match</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($prescriptionItems as $pi)
                    <tr>
                        <td class="fw-medium">{{ $pi['medication_name'] }}
                            @if($pi['duration']) <small class="d-block" style="color:var(--text-muted);">{{ $pi['duration'] }}</small> @endif
                        </td>
                        <td>{{ $pi['dosage'] ?? '—' }}
                            @if($pi['frequency']) <small class="d-block" style="color:var(--text-muted);">{{ $pi['frequency'] }}</small> @endif
                        </td>
                        <td class="text-center fw-bold">{{ $pi['quantity'] }}</td>
                        <td class="text-center">
                            @if($pi['inventory_item'])
                                @if($pi['in_stock'])
                                    <span style="color:var(--accent-success);"><i class="bi bi-check-circle-fill"></i> {{ $pi['current_stock'] }}</span>
                                @else
                                    <span style="color:var(--accent-danger);"><i class="bi bi-x-circle-fill"></i> {{ $pi['current_stock'] }}</span>
                                @endif
                            @else
                                <span style="color:var(--text-muted);" title="No matching item found in inventory"><i class="bi bi-question-circle"></i> Not found</span>
                            @endif
                        </td>
                        <td>
                            @if($pi['inventory_item'])
                                <small style="color:var(--text-muted);">{{ $pi['inventory_item']->name }}</small>
                            @else
                                <small style="color:var(--accent-warning);">Manual select required</small>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(!$allInStock)
        <div class="card-footer" style="background:rgba(var(--accent-warning-rgb),0.07); border-top:1px solid rgba(var(--accent-warning-rgb),0.2);">
            <small style="color:var(--accent-warning);"><i class="bi bi-exclamation-triangle me-1"></i>
                One or more prescribed medicines are out of stock. Dispense available items only — the invoice total will be adjusted to the dispensed amount and any difference should be refunded to the patient in cash.</small>
        </div>
        @endif
    </div>
    @endif

    {{-- Dispensing Form (paid invoice with performer assigned, no items yet) --}}
    @if($invoice->isPaid() && $invoice->performed_by_user_id && !$invoice->items->count())
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-prescription me-2" style="color:var(--accent-warning);"></i>Select Items to Dispense</div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Barcode Scanner --}}
            <div class="glass-card p-3 mb-3" style="border-left:3px solid var(--accent-info);">
                <h6 class="fw-bold mb-2"><i class="bi bi-upc-scan me-1" style="color:var(--accent-info);"></i>Scan to Add Items</h6>
                <x-barcode-scanner id="dispense-scanner" modes="usb,camera" placeholder="Scan medication barcode..." />
                <div id="scanFeedback" class="mt-2" style="display:none;"></div>
            </div>

            <form action="{{ route('pharmacy.invoices.mark-complete', $invoice) }}" method="POST" id="dispense-form">
                @csrf
                <div id="dispense-items">
                    {{-- Pre-populate from prescription for items with inventory matches --}}
                    @php
                        $preRows = $prescriptionItems->filter(fn($pi) => $pi['inventory_item'] && $pi['in_stock']);
                        $startIdx = 0;
                    @endphp
                    @if($preRows->count())
                        @foreach($preRows as $pi)
                        <div class="row mb-2 dispense-row">
                            <div class="col-md-6">
                                <select name="items[{{ $startIdx }}][inventory_item_id]" class="form-select item-select" required>
                                    <option value="">-- Select Item --</option>
                                    @foreach($pharmacyItems as $pharmacyItem)
                                        <option value="{{ $pharmacyItem->id }}"
                                            data-barcode="{{ $pharmacyItem->barcode ?? '' }}"
                                            data-price="{{ $pharmacyItem->selling_price }}"
                                            @selected($pharmacyItem->id === $pi['inventory_item']->id)>
                                            {{ $pharmacyItem->name }} ({{ currency($pharmacyItem->selling_price) }})
                                        </option>
                                    @endforeach
                                </select>
                                <small style="color:var(--text-muted);">Prescribed: {{ $pi['medication_name'] }}</small>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="items[{{ $startIdx }}][quantity]" class="form-control item-qty" placeholder="Qty" min="1" value="{{ $pi['quantity'] }}" required>
                                <small style="color:var(--text-muted);">Stock: {{ $pi['current_stock'] }}</small>
                            </div>
                            <div class="col-md-3 d-flex align-items-center gap-2">
                                <span style="color:var(--accent-success); font-size:0.8rem;"><i class="bi bi-check-circle-fill me-1"></i>In stock</span>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-row">Remove</button>
                            </div>
                        </div>
                        @php $startIdx++; @endphp
                        @endforeach
                    @else
                        {{-- No prescription or no matches — blank row --}}
                        <div class="row mb-2 dispense-row">
                            <div class="col-md-7">
                                <select name="items[0][inventory_item_id]" class="form-select item-select" required>
                                    <option value="">-- Select Item --</option>
                                    @foreach($pharmacyItems as $pharmacyItem)
                                        <option value="{{ $pharmacyItem->id }}" data-barcode="{{ $pharmacyItem->barcode ?? '' }}" data-price="{{ $pharmacyItem->selling_price }}">
                                            {{ $pharmacyItem->name }} ({{ currency($pharmacyItem->selling_price) }}) {{ $pharmacyItem->barcode ? '['.$pharmacyItem->barcode.']' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="items[0][quantity]" class="form-control item-qty" placeholder="Qty" min="1" value="1" required>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-row" style="display:none;">&times;</button>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="add-item-row">
                        <i class="bi bi-plus-circle me-1"></i>Add Another Item
                    </button>
                    <div class="text-end">
                        <div id="dispenseTotal" class="fw-bold" style="color:var(--accent-success);display:none;">
                            Est. Dispensed: <span id="dispenseTotalValue">{{ currency_symbol() }}0.00</span>
                        </div>
                        <div id="refundNotice" style="display:none; color:var(--accent-warning); font-size:0.82rem;">
                            <i class="bi bi-arrow-return-left me-1"></i>Refund due to patient: <span id="refundValue"></span>
                        </div>
                    </div>
                </div>

                <div class="glass-divider mb-3"></div>

                <div class="d-flex gap-2">
                    <a href="{{ route('pharmacy.invoices.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
                    <button type="submit" class="btn btn-success" onclick="return confirm('Confirm dispensing these items?')">
                        <i class="bi bi-check-circle me-1"></i>Mark as Dispensed
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('dispense-items');
            const addBtn = document.getElementById('add-item-row');
            const feedback = document.getElementById('scanFeedback');
            const paidAmount = {{ (float) ($invoice->total_amount ?? 0) }};
            const currSym = '{{ currency_symbol() }}';

            // Row index starts after pre-populated rows
            let rowIndex = container ? container.querySelectorAll('.dispense-row').length : 1;

            // Build barcode → item lookup from first select's options
            const barcodeMap = {};
            document.querySelectorAll('.item-select option[data-barcode]').forEach(function(opt) {
                const bc = opt.dataset.barcode;
                if (bc) barcodeMap[bc] = { id: opt.value, name: opt.textContent.trim(), price: parseFloat(opt.dataset.price) || 0 };
            });

            // Scanner integration
            document.addEventListener('barcode-scanned', function(e) {
                if (e.detail.scannerId !== 'dispense-scanner') return;
                const code = e.detail.code;
                feedback.style.display = 'block';

                const match = barcodeMap[code];
                if (!match) {
                    feedback.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Barcode <strong>' + code + '</strong> not found in pharmacy items.</span>';
                    return;
                }

                // Increment qty if already in a row
                let found = false;
                container.querySelectorAll('.item-select').forEach(function(sel) {
                    if (sel.value === match.id) {
                        const qtyInput = sel.closest('.dispense-row').querySelector('.item-qty');
                        qtyInput.value = parseInt(qtyInput.value || 0) + 1;
                        found = true;
                    }
                });

                if (!found) {
                    addBtn.click();
                    const newSelect = container.querySelector('.dispense-row:last-child .item-select');
                    if (newSelect) newSelect.value = match.id;
                }

                feedback.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Added: <strong>' + match.name + '</strong></span>';
                calculateDispenseTotal();
            });

            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    const row = document.createElement('div');
                    row.className = 'row mb-2 dispense-row';
                    row.innerHTML = `
                        <div class="col-md-7">
                            <select name="items[${rowIndex}][inventory_item_id]" class="form-select item-select" required>
                                <option value="">-- Select Item --</option>
                                @foreach($pharmacyItems as $pharmacyItem)
                                    <option value="{{ $pharmacyItem->id }}" data-barcode="{{ $pharmacyItem->barcode ?? '' }}" data-price="{{ $pharmacyItem->selling_price }}">{{ $pharmacyItem->name }} ({{ currency($pharmacyItem->selling_price) }}) {{ $pharmacyItem->barcode ? '['.$pharmacyItem->barcode.']' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="items[${rowIndex}][quantity]" class="form-control item-qty" placeholder="Qty" min="1" value="1" required>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-row">&times;</button>
                        </div>`;
                    container.appendChild(row);
                    rowIndex++;
                    updateRemoveButtons();
                    calculateDispenseTotal();
                });
            }

            if (container) {
                container.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-row')) {
                        e.target.closest('.dispense-row').remove();
                        updateRemoveButtons();
                        calculateDispenseTotal();
                    }
                });

                container.addEventListener('change', calculateDispenseTotal);
                container.addEventListener('input', calculateDispenseTotal);
            }

            function updateRemoveButtons() {
                const rows = container.querySelectorAll('.dispense-row');
                rows.forEach(function(row) {
                    const btn = row.querySelector('.remove-row');
                    if (btn) btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
                });
            }

            function calculateDispenseTotal() {
                let total = 0;
                container.querySelectorAll('.dispense-row').forEach(function(row) {
                    const sel = row.querySelector('.item-select');
                    const qty = parseInt(row.querySelector('.item-qty')?.value) || 0;
                    const opt = sel?.selectedOptions[0];
                    const price = parseFloat(opt?.dataset?.price) || 0;
                    total += price * qty;
                });
                const totalDiv = document.getElementById('dispenseTotal');
                if (totalDiv) {
                    document.getElementById('dispenseTotalValue').textContent = currSym + total.toFixed(2);
                    totalDiv.style.display = total > 0 ? 'block' : 'none';
                }
                // Refund notice
                const refundDiv = document.getElementById('refundNotice');
                const refundVal = document.getElementById('refundValue');
                if (refundDiv && refundVal && paidAmount > 0) {
                    const diff = paidAmount - total;
                    if (diff > 0.01) {
                        refundVal.textContent = currSym + diff.toFixed(2);
                        refundDiv.style.display = 'block';
                    } else {
                        refundDiv.style.display = 'none';
                    }
                }
            }

            // Trigger initial calculation for pre-populated rows
            calculateDispenseTotal();
            updateRemoveButtons();
        });
    </script>
    @endif

    {{-- Actions --}}
    @if(!$invoice->isPaid() || !$invoice->performed_by_user_id || $invoice->items->count())
    <div class="d-flex gap-2 mb-4 fade-in delay-3">
        <a href="{{ route('pharmacy.invoices.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
        @if($invoice->status === 'pending')
            <div class="alert alert-warning mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle"></i>
                <span><strong>Awaiting Payment</strong> — Payment must be collected before dispensing can begin.</span>
            </div>
        @endif
        @if($invoice->isPaid() && !$invoice->performed_by_user_id)
            <form action="{{ route('pharmacy.invoices.start-work', $invoice) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary" onclick="return confirm('Start work on this prescription?')">
                    <i class="bi bi-play-circle me-1"></i>Start Work
                </button>
            </form>
        @endif
        @if($invoice->status === 'pending')
            <form action="{{ route('pharmacy.invoices.cancel', $invoice) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this order? This cannot be undone.')">
                    <i class="bi bi-x-circle me-1"></i>Cancel Order
                </button>
            </form>
        @endif
    </div>
    @endif

    {{-- Discount Information --}}
    @if($invoice->discount_amount > 0 || ($invoice->discount_status ?? 'none') !== 'none')
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-percent me-2" style="color:var(--accent-warning);"></i>Discount</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Discount Amount</span>
                    <span class="info-value">{{ currency($invoice->discount_amount ?? 0) }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Status</span>
                    @php
                        $dStyle = match($invoice->discount_status ?? 'none') {
                            'pending' => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                            'approved' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            'rejected' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                            default => '',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $dStyle }}">{{ ucfirst($invoice->discount_status ?? 'none') }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Net Amount</span>
                    <span class="info-value">{{ currency($invoice->net_amount ?? ($invoice->total_amount - ($invoice->discount_amount ?? 0))) }}</span>
                </div>
            </div>
            @if($invoice->discount_reason)
                <p class="small mt-2" style="color:var(--text-muted);">Reason: {{ $invoice->discount_reason }}</p>
            @endif
        </div>
    </div>
    @endif

    {{-- Discount Request --}}
    @if(!$invoice->isPaid() && $invoice->status !== 'cancelled' && ($invoice->discount_status ?? 'none') !== 'pending')
    <div class="card mb-4 fade-in delay-4">
        <div class="card-header"><i class="bi bi-tag me-2" style="color:var(--accent-info);"></i>Request Discount</div>
        <div class="card-body">
            <form action="{{ route('invoices.discount.request', $invoice) }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="discount_amount" class="form-label">Amount</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $invoice->total_amount }}" name="discount_amount" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="discount_reason" class="form-label">Reason</label>
                        <input type="text" name="discount_reason" class="form-control" placeholder="Business justification...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-warning btn-sm w-100" onclick="return confirm('Submit discount request for Owner approval?')"><i class="bi bi-send me-1"></i>Request</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Collect Payment (pending invoices) --}}
    @if($invoice->status === 'pending' && !$invoice->isPaid())
    <div class="card mb-4 fade-in delay-4">
        @if(($invoice->discount_status ?? 'none') === 'pending')
            <div class="card-body">
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i><strong>Payment Blocked:</strong> A discount request is pending Owner approval. Payment cannot be collected until the discount is approved or rejected.
                </div>
            </div>
        @else
            <div class="card-header"><i class="bi bi-cash-coin me-2" style="color:var(--accent-success);"></i>Collect Payment</div>
            <div class="card-body">
                <form action="{{ route('pharmacy.invoices.mark-paid', $invoice) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="">Select payment method</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success" onclick="return confirm('Confirm payment received?')">
                        <i class="bi bi-check-circle me-1"></i>Mark as Paid
                    </button>
                </form>
            </div>
        @endif
    </div>
    @endif

    {{-- Performer & Payment Info --}}
    @if($invoice->performer || $invoice->payer)
    <div class="card mb-4 fade-in delay-5">
        <div class="card-header"><i class="bi bi-people me-2" style="color:var(--accent-secondary);"></i>Processing Info</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px,1fr));">
                @if($invoice->performer)
                <div class="info-grid-item">
                    <span class="info-label">Performed by</span>
                    <span class="info-value">{{ $invoice->performer->name }}</span>
                </div>
                @endif
                @if($invoice->payer)
                <div class="info-grid-item">
                    <span class="info-label">Paid by</span>
                    <span class="info-value">{{ $invoice->payer->name }}</span>
                </div>
                @endif
                @if($invoice->paid_at)
                <div class="info-grid-item">
                    <span class="info-label">Paid at</span>
                    <span class="info-value">{{ $invoice->paid_at->format('M d, Y H:i') }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

    @include('components.invoice-print-layout', ['invoice' => $invoice])
@endsection

@push('styles')
@include('components.invoice-print-styles')
@endpush

@push('scripts')
@if($invoice->isPaid() && $invoice->fbr_qr_code)
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSe2s9qnDN7oD6eblnBHyH3P1pAzrBDxhxNSw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function() {
    var c = document.getElementById('pi-qr-container');
    if (c) new QRCode(c, { text: {{ json_encode($invoice->fbr_qr_code) }}, width: 80, height: 80, colorDark: '#000', colorLight: '#fff', correctLevel: QRCode.CorrectLevel.M });
})();
</script>
@endif
@endpush
