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

            <form action="{{ route('pharmacy.invoices.mark-complete', $invoice) }}" method="POST" id="dispense-form">
                @csrf
                <div id="dispense-items">
                    <div class="row mb-2 dispense-row">
                        <div class="col-md-7">
                            <select name="items[0][inventory_item_id]" class="form-select" required>
                                <option value="">-- Select Item --</option>
                                @foreach($pharmacyItems as $pharmacyItem)
                                    <option value="{{ $pharmacyItem->id }}">
                                        {{ $pharmacyItem->name }} ({{ currency($pharmacyItem->selling_price) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="items[0][quantity]" class="form-control" placeholder="Qty" min="1" value="1" required>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-row" style="display:none;">&times;</button>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="add-item-row">
                    <i class="bi bi-plus-circle me-1"></i>Add Another Item
                </button>

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
            let rowIndex = 1;
            const container = document.getElementById('dispense-items');
            const addBtn = document.getElementById('add-item-row');

            addBtn.addEventListener('click', function() {
                const row = document.createElement('div');
                row.className = 'row mb-2 dispense-row';
                row.innerHTML = `
                    <div class="col-md-7">
                        <select name="items[${rowIndex}][inventory_item_id]" class="form-select" required>
                            <option value="">-- Select Item --</option>
                            @foreach($pharmacyItems as $pharmacyItem)
                                <option value="{{ $pharmacyItem->id }}">{{ $pharmacyItem->name }} ({{ currency($pharmacyItem->selling_price) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="items[${rowIndex}][quantity]" class="form-control" placeholder="Qty" min="1" value="1" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-row">&times;</button>
                    </div>`;
                container.appendChild(row);
                rowIndex++;
                updateRemoveButtons();
            });

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-row')) {
                    e.target.closest('.dispense-row').remove();
                    updateRemoveButtons();
                }
            });

            function updateRemoveButtons() {
                const rows = container.querySelectorAll('.dispense-row');
                rows.forEach(function(row) {
                    const btn = row.querySelector('.remove-row');
                    if (btn) btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
                });
            }
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
@endsection
