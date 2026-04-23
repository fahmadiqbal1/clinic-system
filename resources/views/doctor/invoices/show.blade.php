@extends('layouts.app')
@section('title', 'Invoice Details — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Print Header --}}
    <div class="print-header">
        <h2>{{ config('app.name') }}</h2>
        <p>Invoice #{{ $invoice->id }} &mdash; {{ $invoice->created_at?->format('M d, Y') }}</p>
    </div>

    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-receipt me-2" style="color:var(--accent-primary);"></i>Invoice #{{ $invoice->id }}</h2>
            <p class="page-subtitle mb-0">Doctor Invoice View</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-outline-success btn-sm" data-no-disable="true"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
            <button onclick="window.print()" class="btn btn-outline-info btn-sm" data-no-disable="true"><i class="bi bi-printer me-1"></i>Print</button>
            <a href="{{ route('doctor.invoices.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Invoices</a>
        </div>
    </div>

    {{-- Status & Date --}}
    <div class="card mb-4 fade-in delay-1">
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
                    <span class="info-label">Created</span>
                    <span class="info-value">{{ $invoice->created_at?->format('M d, Y H:i') ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value">{{ $invoice->patient?->first_name }} {{ $invoice->patient?->last_name }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Department</span>
                    <span class="info-value">{{
                        match($invoice->department ?? '') {
                            'lab' => 'Laboratory', 'radiology' => 'Radiology',
                            'pharmacy' => 'Pharmacy', 'consultation' => 'Consultation',
                            default => ucfirst($invoice->department ?? 'N/A')
                        }
                    }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Service Name</span>
                    <span class="info-value">{{ $invoice->service_name ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Payment</span>
                    @php
                        $payStyle = match($invoice->status) {
                            'paid' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            'completed' => 'background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);',
                            default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $payStyle }}">{{ $invoice->status === 'paid' ? 'Paid' : 'Unpaid' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Revenue from this Invoice --}}
    @if($invoice->revenueLedgers && $invoice->revenueLedgers->count() > 0)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-graph-up-arrow me-2" style="color:var(--accent-success);"></i>Your Commission</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->revenueLedgers as $entry)
                            <tr>
                                <td>{{ $entry->role_type ?? 'N/A' }}</td>
                                <td class="fw-semibold">{{ number_format($entry->amount, 2) }}</td>
                                <td>
                                    @if($entry->payout_id)
                                        <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);">Paid</span>
                                    @else
                                        <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);">Unpaid</span>
                                    @endif
                                </td>
                                <td style="color:var(--text-muted);">{{ $entry->created_at?->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Report Text --}}
    @if($invoice->report_text)
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-file-text me-2" style="color:var(--accent-info);"></i>Report</div>
        <div class="card-body">
            <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                {!! nl2br(e($invoice->report_text)) !!}
            </div>
        </div>
    </div>
    @endif

    {{-- Structured Lab Results --}}
    @if($invoice->department === 'lab' && $invoice->lab_results && count((array)$invoice->lab_results) > 0)
    @php
        $rawDr = $invoice->lab_results;
        $isLegacyDr = is_array($rawDr) && array_is_list($rawDr);
        $groupedDr = $isLegacyDr ? ['General' => $rawDr] : (array) $rawDr;
        $itemMapDr = $invoice->items->keyBy('id');
    @endphp
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-table me-2" style="color:var(--accent-warning);"></i>Lab Results</div>
        <div class="card-body">
            @foreach($groupedDr as $gKey => $gRows)
                @if(count($gRows) > 0)
                @php
                    $sectionLabel = $gKey === 'general' || $gKey === 'General'
                        ? 'General Results'
                        : ($itemMapDr[$gKey]?->description ?? $itemMapDr[$gKey]?->serviceCatalog?->name ?? 'Test');
                @endphp
                <h6 class="fw-semibold mb-2 {{ !$loop->first ? 'mt-4' : '' }}">
                    <i class="bi bi-clipboard2-pulse me-1" style="color:var(--accent-info);"></i>{{ $sectionLabel }}
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Result</th>
                                <th>Unit</th>
                                <th>Reference Range</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gRows as $row)
                                <tr>
                                    <td class="fw-medium">{{ $row['test_name'] ?? '' }}</td>
                                    <td class="fw-semibold" style="color:var(--accent-primary);">{{ $row['result'] ?? '' }}</td>
                                    <td style="color:var(--text-muted);">{{ $row['unit'] ?? '—' }}</td>
                                    <td style="color:var(--text-muted);">{{ $row['reference_range'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Radiology Images --}}
    @if($invoice->department === 'radiology' && $invoice->radiology_images && count($invoice->radiology_images) > 0)
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-images me-2" style="color:var(--accent-secondary);"></i>Radiology Images ({{ count($invoice->radiology_images) }})</div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($invoice->radiology_images as $idx => $imagePath)
                    <div class="col-md-4 col-lg-3">
                        <div class="rounded overflow-hidden" style="border:1px solid var(--glass-border);">
                            @if(str_ends_with(strtolower($imagePath), '.pdf'))
                                <a href="{{ Storage::url($imagePath) }}" target="_blank" class="d-flex flex-column align-items-center justify-content-center p-4 text-decoration-none" style="min-height:160px; background:var(--glass-bg);">
                                    <i class="bi bi-file-earmark-pdf" style="font-size:3rem; color:var(--accent-danger);"></i>
                                    <span class="small mt-2" style="color:var(--text-muted);">PDF Document</span>
                                </a>
                            @else
                                <a href="{{ Storage::url($imagePath) }}" target="_blank">
                                    <img src="{{ Storage::url($imagePath) }}" alt="Radiology Image {{ $idx + 1 }}" class="img-fluid w-100" style="min-height:160px; object-fit:cover;">
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="d-flex gap-2 fade-in delay-4">
        <a href="{{ route('doctor.invoices.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    @include('components.invoice-print-layout', ['invoice' => $invoice])
</div>
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
