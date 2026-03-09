@extends('layouts.app')
@section('title', 'Invoice #' . $invoice->id . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Print Header (visible only when printing) --}}
    <div class="print-header">
        <h2>{{ config('app.name') }}</h2>
        <p>Invoice #{{ $invoice->id }} &mdash; {{ $invoice->created_at?->format('M d, Y') }}</p>
    </div>

    <div class="glass-card fade-in">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3" style="border-bottom:1px solid var(--glass-border);">
            <div>
                <h2 class="h4 fw-bold mb-1"><i class="bi bi-receipt me-2" style="color:var(--accent-primary);"></i>Invoice <span class="code-tag">#{{ $invoice->id }}</span></h2>
                <p class="page-subtitle mb-0">Invoice Details</p>
            </div>
            <div class="d-flex gap-2 no-print">
                <button onclick="window.print()" class="btn btn-outline-info btn-sm" data-no-disable="true"><i class="bi bi-printer me-1"></i>Print</button>
                <a href="{{ route('receptionist.invoices.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Invoices
                </a>
            </div>
        </div>

            <!-- Invoice Status -->
            <div class="d-flex align-items-center justify-content-between pb-4 mb-4" style="border-bottom:1px solid var(--glass-border);">
                <div>
                    <p class="small mb-1" style="color:var(--text-muted);">Status</p>
                    <p class="fs-5 fw-semibold mb-0">
                        <span class="badge {{ match($invoice->status) { 'completed' => 'badge-glass-success', 'paid' => 'badge-glass-primary', 'cancelled' => 'badge-glass-danger', default => 'badge-glass-warning' } }}">
                            {{ ucfirst($invoice->status ?? 'pending') }}
                        </span>
                    </p>
                </div>
                <div>
                    <p class="small mb-1" style="color:var(--text-muted);">Created at</p>
                    <p class="fs-5 fw-semibold mb-0">{{ $invoice->created_at?->format('M d, Y H:i') ?? 'N/A' }}</p>
                </div>
            </div>

            <!-- Patient Information -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <p class="small mb-1" style="color:var(--text-muted);">Patient Name</p>
                    <p class="fs-5 fw-semibold">{{ $invoice->patient?->full_name ?? 'Unknown Patient' }}</p>
                </div>
                <div class="col-md-6">
                    <p class="small mb-1" style="color:var(--text-muted);">Patient ID</p>
                    <p class="fs-5 fw-semibold">{{ $invoice->patient_id ?? 'N/A' }}</p>
                </div>
            </div>

            <!-- Service Information -->
            <div class="row mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                <div class="col-md-6">
                    <p class="small mb-1" style="color:var(--text-muted);">Department</p>
                    <p class="fs-5 fw-semibold">{{
                        match($invoice->department ?? '') {
                            'lab' => 'Laboratory',
                            'radiology' => 'Radiology',
                            'pharmacy' => 'Pharmacy',
                            'consultation' => 'Consultation',
                            default => ucfirst($invoice->department ?? 'N/A')
                        }
                    }}</p>
                </div>
                <div class="col-md-6">
                    <p class="small mb-1" style="color:var(--text-muted);">Service Name</p>
                    <p class="fs-5 fw-semibold">{{ $invoice->service_name ?? 'N/A' }}</p>
                </div>
            </div>

            <!-- Pricing Information -->
            <div class="row mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                <div class="col-md-6">
                    <p class="small mb-1" style="color:var(--text-muted);">Total Amount</p>
                    <p class="h4 fw-bold" style="color:var(--accent-info);">{{ currency($invoice->total_amount ?? 0) }}</p>
                </div>
                @if($invoice->prescribing_doctor_id)
                    <div class="col-md-6">
                        <p class="small mb-1" style="color:var(--text-muted);">Prescribing Doctor</p>
                        <p class="fs-5 fw-semibold">{{ $invoice->prescribingDoctor?->name ?? 'N/A' }}</p>
                    </div>
                @endif
            </div>

            <!-- Referrer Information -->
            @if($invoice->referrer_name)
                <div class="row mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    <div class="col-md-6">
                        <p class="small mb-1" style="color:var(--text-muted);">Referrer Name</p>
                        <p class="fs-5 fw-semibold">{{ $invoice->referrer_name ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="small mb-1" style="color:var(--text-muted);">Referrer Percentage</p>
                        <p class="fs-5 fw-semibold">{{ $invoice->referrer_percentage ?? 0 }}%</p>
                    </div>
                </div>
            @endif

            <!-- Discount Information -->
            @if($invoice->discount_amount > 0 || ($invoice->discount_status ?? 'none') !== 'none')
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    <h5 class="fw-bold mb-3"><i class="bi bi-tag me-2" style="color:var(--accent-warning);"></i>Discount</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <p class="small mb-1" style="color:var(--text-muted);">Discount Amount</p>
                            <p class="fs-5 fw-semibold" style="color:var(--accent-warning);">{{ currency($invoice->discount_amount ?? 0) }}</p>
                        </div>
                        <div class="col-md-4">
                            <p class="small mb-1" style="color:var(--text-muted);">Status</p>
                            <p class="fs-5 fw-semibold">
                                <span class="badge {{ match($invoice->discount_status ?? 'none') { 'pending' => 'badge-glass-warning', 'approved' => 'badge-glass-success', 'rejected' => 'badge-glass-danger', default => 'badge-glass-secondary' } }}">
                                    {{ ucfirst($invoice->discount_status ?? 'none') }}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <p class="small mb-1" style="color:var(--text-muted);">Net Amount</p>
                            <p class="fs-5 fw-semibold" style="color:var(--accent-success);">{{ currency($invoice->net_amount ?? ($invoice->total_amount - ($invoice->discount_amount ?? 0))) }}</p>
                        </div>
                    </div>
                    @if($invoice->discount_reason)
                        <p class="small mb-0" style="color:var(--text-muted);">Reason: {{ $invoice->discount_reason }}</p>
                    @endif
                    @if($invoice->discountRequester)
                        <p class="small mb-0" style="color:var(--text-muted);">Requested by: {{ $invoice->discountRequester->name }} at {{ $invoice->discount_requested_at?->format('M d, Y H:i') }}</p>
                    @endif
                    @if($invoice->discountApprover && $invoice->discount_status !== 'pending')
                        <p class="small mb-0" style="color:var(--text-muted);">{{ $invoice->discount_status === 'approved' ? 'Approved' : 'Rejected' }} by: {{ $invoice->discountApprover->name }} at {{ $invoice->discount_approved_at?->format('M d, Y H:i') }}</p>
                    @endif
                </div>
            @endif

            <!-- Discount Request (staff can request before payment) -->
            @if(!$invoice->isPaid() && $invoice->status !== 'cancelled' && ($invoice->discount_status ?? 'none') !== 'pending')
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    <h5 class="fw-semibold mb-3"><i class="bi bi-tag me-2" style="color:var(--accent-warning);"></i>Request Discount</h5>
                    <p class="small mb-3" style="color:var(--text-muted);">Submit a discount request for Owner approval.</p>
                    <form action="{{ route('invoices.discount.request', $invoice) }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label for="discount_amount" class="form-label">Discount Amount *</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $invoice->total_amount }}" name="discount_amount" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label for="discount_reason" class="form-label">Reason</label>
                                <input type="text" name="discount_reason" class="form-control" placeholder="Business justification...">
                            </div>
                            <div class="col-md-2 mb-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('Submit discount request for Owner approval?')"><i class="bi bi-send me-1"></i>Request</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            <!-- Consultation Invoice Lifecycle (managed by receptionist) -->
            @if($invoice->department === 'consultation' && $invoice->status === 'pending')
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    <h5 class="fw-semibold mb-3"><i class="bi bi-play-circle me-2" style="color:var(--accent-primary);"></i>Start Consultation</h5>
                    <p class="small mb-3" style="color:var(--text-muted);">Mark this consultation as in progress.</p>
                    <form action="{{ route('receptionist.invoices.start-work', $invoice) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Start work on this consultation invoice?')">
                            <i class="bi bi-play-fill me-1"></i>Start Consultation
                        </button>
                    </form>
                </div>
            @endif

            @if($invoice->department === 'consultation' && $invoice->status === 'in_progress')
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    <h5 class="fw-semibold mb-3"><i class="bi bi-check-circle me-2" style="color:var(--accent-success);"></i>Complete Consultation</h5>
                    <p class="small mb-3" style="color:var(--text-muted);">Mark this consultation as completed. It will then be ready for payment.</p>
                    <form action="{{ route('receptionist.invoices.mark-complete', $invoice) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success" onclick="return confirm('Mark this consultation as completed?')">
                            <i class="bi bi-check-lg me-1"></i>Mark as Completed
                        </button>
                    </form>
                </div>
            @endif

            <!-- Upfront Payment (lab/radiology pending invoices) -->
            @if($invoice->status === 'pending' && in_array($invoice->department, ['lab', 'radiology']))
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    @if(($invoice->discount_status ?? 'none') === 'pending')
                        <div class="alert alert-warning">
                            <strong><i class="bi bi-exclamation-triangle me-1"></i>Payment Blocked:</strong> A discount request is pending Owner approval.
                        </div>
                    @else
                        <h5 class="fw-semibold mb-3"><i class="bi bi-cash-coin me-2" style="color:var(--accent-success);"></i>Collect Upfront Payment</h5>
                        <p class="small mb-3" style="color:var(--text-muted);">{{ $invoice->department === 'lab' ? 'Laboratory' : 'Radiology' }} services require upfront payment before work begins.</p>
                        <form action="{{ route('receptionist.invoices.mark-paid', $invoice) }}" method="POST">
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
                            <button type="submit" class="btn btn-success" onclick="return confirm('Collect upfront payment for this invoice?')">
                                <i class="bi bi-cash-coin me-1"></i>Collect Payment
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            <!-- Mark as Paid (in-progress non-pharmacy invoices) -->
            @if($invoice->status === 'in_progress' && $invoice->department !== 'pharmacy')
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    @if(($invoice->discount_status ?? 'none') === 'pending')
                        <div class="alert alert-warning">
                            <strong><i class="bi bi-exclamation-triangle me-1"></i>Payment Blocked:</strong> A discount request is pending Owner approval.
                        </div>
                    @else
                        <h5 class="fw-semibold mb-3"><i class="bi bi-cash-coin me-2" style="color:var(--accent-success);"></i>Mark as Paid</h5>
                        <p class="small mb-3" style="color:var(--text-muted);">This invoice is currently in progress. You can collect payment now.</p>
                        <form action="{{ route('receptionist.invoices.mark-paid', $invoice) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="payment_method_ip" class="form-label">Payment Method *</label>
                                <select name="payment_method" id="payment_method_ip" class="form-select" required>
                                    <option value="">Select payment method</option>
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="transfer">Transfer</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success" onclick="return confirm('Mark this in-progress invoice as paid?')">
                                <i class="bi bi-check-circle me-1"></i>Mark as Paid
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            <!-- Mark as Paid (completed non-pharmacy invoices) -->
            @if($invoice->status === 'completed' && $invoice->department !== 'pharmacy')
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    @if(($invoice->discount_status ?? 'none') === 'pending')
                        <div class="alert alert-warning">
                            <strong><i class="bi bi-exclamation-triangle me-1"></i>Payment Blocked:</strong> A discount request is pending Owner approval. You cannot collect payment until the discount is approved or rejected.
                        </div>
                    @else
                        <h5 class="fw-semibold mb-3"><i class="bi bi-cash-coin me-2" style="color:var(--accent-success);"></i>Mark as Paid</h5>
                        <form action="{{ route('receptionist.invoices.mark-paid', $invoice) }}" method="POST">
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
                            <button type="submit" class="btn btn-success" onclick="return confirm('Mark this invoice as paid?')">
                                <i class="bi bi-check-circle me-1"></i>Mark as Paid
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            <!-- Pharmacy Payment Notice -->
            @if($invoice->department === 'pharmacy' && in_array($invoice->status, ['completed', 'pending']))
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    <div class="alert" style="background:rgba(var(--accent-info-rgb),0.1); border:1px solid rgba(var(--accent-info-rgb),0.3); color:var(--text-primary);">
                        <i class="bi bi-info-circle me-2" style="color:var(--accent-info);"></i>
                        <strong>Pharmacy Payment:</strong> Pharmacy invoices are collected at the pharmacy counter. This invoice will be marked as paid by the Pharmacy staff.
                    </div>
                </div>
            @endif

            <!-- Performer & Payment Info -->
            @if($invoice->performer || $invoice->creator || $invoice->payer)
                <div class="mb-4 pt-4" style="border-top:1px solid var(--glass-border);">
                    <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2" style="color:var(--accent-info);"></i>Activity</h5>
                    <div class="row">
                        @if($invoice->creator)
                            <div class="col-md-3">
                                <p class="small mb-1" style="color:var(--text-muted);">Created by</p>
                                <p class="fw-semibold">{{ $invoice->creator->name }}</p>
                            </div>
                        @endif
                        @if($invoice->performer)
                            <div class="col-md-3">
                                <p class="small mb-1" style="color:var(--text-muted);">Performed by</p>
                                <p class="fw-semibold">{{ $invoice->performer->name }}</p>
                            </div>
                        @endif
                        @if($invoice->payer)
                            <div class="col-md-3">
                                <p class="small mb-1" style="color:var(--text-muted);">Paid by</p>
                                <p class="fw-semibold">{{ $invoice->payer->name }}</p>
                            </div>
                        @endif
                        @if($invoice->paid_at)
                            <div class="col-md-3">
                                <p class="small mb-1" style="color:var(--text-muted);">Paid at</p>
                                <p class="fw-semibold">{{ $invoice->paid_at->format('M d, Y H:i') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Actions -->
            <div class="d-flex gap-2 pt-4" style="border-top:1px solid var(--glass-border);">
                <a href="{{ route('receptionist.invoices.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                @if(in_array($invoice->status, ['pending', 'in_progress']))
                    <form action="{{ route('receptionist.invoices.cancel', $invoice) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this invoice? This cannot be undone.')">
                            <i class="bi bi-x-circle me-1"></i>Cancel Invoice
                        </button>
                    </form>
                @endif
            </div>

            {{-- FBR IRIS Digital Invoice Block --}}
            @php $fbrSettings = \App\Models\PlatformSetting::fbr(); @endphp
            @if($invoice->isPaid())
            <div class="mt-4 pt-4" style="border-top:1px solid var(--glass-border);">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-receipt-cutoff me-2" style="color:var(--accent-success);"></i>FBR Digital Invoice
                    @if($invoice->fbr_status === 'submitted')
                        <span class="badge bg-success ms-2" style="font-size:.65rem;">Submitted</span>
                    @elseif($invoice->fbr_status === 'pending')
                        <span class="badge bg-warning text-dark ms-2" style="font-size:.65rem;">Submitting…</span>
                    @elseif($invoice->fbr_status === 'failed')
                        <span class="badge bg-danger ms-2" style="font-size:.65rem;">Failed</span>
                    @elseif($invoice->fbr_status === 'not_configured')
                        <span class="badge bg-secondary ms-2" style="font-size:.65rem;">Not Configured</span>
                    @else
                        <span class="badge bg-secondary ms-2" style="font-size:.65rem;">Not Submitted</span>
                    @endif
                </h5>

                @if($invoice->fbr_irn)
                    <div class="row g-3 align-items-start">
                        <div class="col-md-8">
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <p class="small mb-1" style="color:var(--text-muted);">IRN (Invoice Reference Number)</p>
                                    <p class="fw-semibold mb-0 font-monospace small">{{ $invoice->fbr_irn }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="small mb-1" style="color:var(--text-muted);">FBR Invoice Number</p>
                                    <p class="fw-semibold mb-0">{{ $invoice->fbr_invoice_number ?? $invoice->id }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="small mb-1" style="color:var(--text-muted);">Submitted At</p>
                                    <p class="fw-semibold mb-0">{{ $invoice->fbr_submitted_at?->format('M d, Y H:i') ?? '—' }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="small mb-1" style="color:var(--text-muted);">STRN</p>
                                    <p class="fw-semibold mb-0">{{ $fbrSettings->getMeta('strn') ?? '—' }}</p>
                                </div>
                            </div>
                        </div>
                        @if($invoice->fbr_qr_code)
                        <div class="col-md-4 text-center">
                            <p class="small mb-1" style="color:var(--text-muted);">Scan to Verify</p>
                            <div id="fbr-qr-container" class="d-inline-block p-2 bg-white rounded border"></div>
                            <p class="small mt-1" style="color:var(--text-muted);">FBR Verification QR</p>
                        </div>
                        @endif
                    </div>
                @elseif($invoice->fbr_status === 'failed' || is_null($invoice->fbr_status))
                    <p class="small mb-3" style="color:var(--text-muted);">
                        @if($invoice->fbr_status === 'failed')
                            FBR submission failed for this invoice.
                        @elseif(!$fbrSettings->isFbrReady())
                            FBR IRIS is not configured. Ask the Owner to set up FBR settings in their profile.
                        @else
                            This invoice has not yet been submitted to FBR.
                        @endif
                    </p>
                    @if($fbrSettings->isFbrReady())
                    <form action="{{ route('receptionist.invoices.fbr-resubmit', $invoice) }}" method="POST" class="no-print">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm"
                                onclick="return confirm('Submit this invoice to FBR IRIS now?')">
                            <i class="bi bi-send me-1"></i>Submit to FBR IRIS
                        </button>
                    </form>
                    @endif
                @elseif($invoice->fbr_status === 'not_configured')
                    <div class="alert alert-info py-2">
                        <i class="bi bi-info-circle me-2"></i>
                        FBR integration is not yet configured. Please set up your FBR IRIS credentials in
                        <a href="{{ route('profile.edit') }}#fbr">Owner Profile → FBR Settings</a>.
                    </div>
                @endif

                @if($invoice->isPaid() && $invoice->fbr_status === 'submitted' && $fbrSettings->isFbrReady())
                <div class="mt-2 no-print">
                    <form action="{{ route('receptionist.invoices.fbr-resubmit', $invoice) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm"
                                onclick="return confirm('Resubmit this invoice to FBR IRIS? Use only if the original submission needs correction.')">
                            <i class="bi bi-arrow-repeat me-1"></i>Resubmit to FBR
                        </button>
                    </form>
                </div>
                @endif
            </div>
            @endif
    </div>
</div>
@endsection

@push('scripts')
@if($invoice->isPaid() && $invoice->fbr_qr_code)
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSe2s9qnDN7oD6eblnBHyH3P1pAzrBDxhxNSw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function() {
    var container = document.getElementById('fbr-qr-container');
    if (container) {
        new QRCode(container, {
            text: {{ json_encode($invoice->fbr_qr_code) }},
            width: 120,
            height: 120,
            colorDark : '#000000',
            colorLight : '#ffffff',
            correctLevel : QRCode.CorrectLevel.M
        });
    }
})();
</script>
@endif
@endpush

