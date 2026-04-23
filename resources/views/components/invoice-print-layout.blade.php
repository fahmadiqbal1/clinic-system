{{--
    Shared print-only invoice layout.
    Mirrors the DomPDF template (pdf/invoice.blade.php) so print output matches downloaded PDF exactly.
    Usage: @include('components.invoice-print-layout', ['invoice' => $invoice])
--}}
@php
    $fbrSettings = \App\Models\PlatformSetting::fbr();
    $ntn      = $fbrSettings?->getMeta('ntn')              ?? '';
    $strn     = $fbrSettings?->getMeta('strn')             ?? '';
    $bizName  = $fbrSettings?->getMeta('business_name')    ?? config('app.name');
    $bizAddr  = $fbrSettings?->getMeta('business_address') ?? '';
    $province = $fbrSettings?->getMeta('seller_province')  ?? '';
    $bizPhone = $fbrSettings?->getMeta('business_phone')   ?? '';
    $hasFbr   = $invoice->fbr_irn;
    $hasItems = $invoice->items->count() > 0;
    $deptLabel = match($invoice->department) {
        'lab'          => 'Laboratory',
        'radiology'    => 'Radiology',
        'pharmacy'     => 'Pharmacy',
        'consultation' => 'Consultation',
        default        => ucfirst($invoice->department ?? 'General'),
    };
    $statusClass = match($invoice->status) { 'paid' => 'pi-badge-paid', 'cancelled' => 'pi-badge-cancelled', default => 'pi-badge-pending' };
@endphp

<div class="print-invoice-layout">
    {{-- HEADER --}}
    <div class="pi-header">
        <div class="pi-header-left">
            <img src="{{ asset('images/logo JPEG.jpeg') }}" alt="Logo" class="pi-logo">
        </div>
        <div class="pi-header-center">
            <div class="pi-clinic-name">{{ $bizName ?: config('app.name') }}</div>
            @if($bizAddr)<div class="pi-clinic-sub">{{ $bizAddr }}{{ $province ? ', ' . $province : '' }}</div>@endif
            @if($bizPhone)<div class="pi-clinic-sub">Tel: {{ $bizPhone }}</div>@endif
            @if($ntn || $strn)
                <div class="pi-clinic-sub">
                    @if($ntn)NTN: <strong>{{ $ntn }}</strong>@endif
                    @if($ntn && $strn) | @endif
                    @if($strn)STRN: <strong>{{ $strn }}</strong>@endif
                </div>
            @endif
        </div>
        <div class="pi-header-right">
            <div class="pi-tag">TAX INVOICE</div>
        </div>
    </div>
    <hr class="pi-divider">

    {{-- BILL-TO / INVOICE META --}}
    <div class="pi-meta-row">
        <div class="pi-meta-box">
            <div class="pi-meta-lbl">Billed To</div>
            <div class="pi-meta-val">
                <strong>{{ $invoice->patient->full_name ?? 'Walk-in Patient' }}</strong><br>
                @if($invoice->patient?->phone) Phone: {{ $invoice->patient->phone }}<br> @endif
                @if($invoice->patient?->email) Email: {{ $invoice->patient->email }}<br> @endif
                @if($invoice->patient?->cnic) CNIC: {{ $invoice->patient->cnic }}<br> @endif
                Patient Type: {{ ucfirst($invoice->patient_type ?? 'walk_in') }}
            </div>
        </div>
        <div class="pi-meta-box pi-meta-box-right">
            <div class="pi-meta-lbl">Invoice Details</div>
            <div class="pi-meta-val">
                <strong>Invoice #:</strong> {{ $invoice->id }}<br>
                <strong>Date:</strong> {{ $invoice->created_at->format('d M Y') }}<br>
                @if($invoice->paid_at)<strong>Paid:</strong> {{ $invoice->paid_at->format('d M Y, H:i') }}<br>@endif
                @if($invoice->payment_method)<strong>Payment:</strong> {{ ucfirst($invoice->payment_method) }}<br>@endif
                <strong>Department:</strong> {{ $deptLabel }}<br>
                <strong>Status:</strong> <span class="{{ $statusClass }}">{{ strtoupper($invoice->status) }}</span>
                @if($invoice->prescribingDoctor)<br><strong>Doctor:</strong> {{ $invoice->prescribingDoctor->name }}@endif
                @if($invoice->performer && $invoice->performer->id !== $invoice->prescribing_doctor_id)<br><strong>Performed by:</strong> {{ $invoice->performer->name }}@endif
            </div>
        </div>
    </div>

    {{-- LINE ITEMS --}}
    <table class="pi-items">
        <thead>
            <tr>
                <th style="width:6%">#</th>
                <th style="width:34%">Description</th>
                <th style="width:12%" class="pi-tc">HS Code</th>
                <th style="width:8%" class="pi-tr">Qty</th>
                <th style="width:16%" class="pi-tr">Unit Price</th>
                <th style="width:10%" class="pi-tr">Discount</th>
                <th style="width:14%" class="pi-tr">Total</th>
            </tr>
        </thead>
        <tbody>
            @if($hasItems)
                @foreach($invoice->items as $i => $item)
                <tr class="{{ $i % 2 === 1 ? 'pi-alt' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->description ?? $item->serviceCatalog?->name ?? 'Service' }}</td>
                    <td class="pi-tc" style="color:#5a6a85;">{{ $item->serviceCatalog?->hs_code ?? '9813.0000' }}</td>
                    <td class="pi-tr">{{ $item->quantity }}</td>
                    <td class="pi-tr">Rs. {{ number_format($item->unit_price, 2) }}</td>
                    <td class="pi-tr">&mdash;</td>
                    <td class="pi-tr">Rs. {{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            @else
                @php $catalog = $invoice->serviceCatalog; @endphp
                <tr>
                    <td>1</td>
                    <td>{{ $invoice->service_name ?? $catalog?->name ?? $deptLabel . ' Service' }}
                        @if($catalog?->code)<br><span style="font-size:7px;color:#5a6a85;">Code: {{ $catalog->code }}</span>@endif
                    </td>
                    <td class="pi-tc" style="color:#5a6a85;">{{ $catalog?->hs_code ?? '9813.0000' }}</td>
                    <td class="pi-tr">1</td>
                    <td class="pi-tr">Rs. {{ number_format($invoice->total_amount, 2) }}</td>
                    <td class="pi-tr">
                        @if(($invoice->discount_amount ?? 0) > 0 && $invoice->discount_status === 'approved')
                            Rs. {{ number_format($invoice->discount_amount, 2) }}
                        @else &mdash; @endif
                    </td>
                    <td class="pi-tr">Rs. {{ number_format($invoice->net_amount ?? $invoice->total_amount, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- TOTALS --}}
    <div class="pi-totals-row">
        <div class="pi-totals-spacer"></div>
        <div class="pi-totals-box">
            <div class="pi-total-line">
                <span class="pi-total-lbl">Subtotal</span>
                <span class="pi-total-val">Rs. {{ number_format($invoice->total_amount, 2) }}</span>
            </div>
            @if(($invoice->discount_amount ?? 0) > 0 && $invoice->discount_status === 'approved')
            <div class="pi-total-line">
                <span class="pi-total-lbl">Discount</span>
                <span class="pi-total-val" style="color:#b91c1c;">&minus; Rs. {{ number_format($invoice->discount_amount, 2) }}</span>
            </div>
            @endif
            @php $taxRate = (float)($fbrSettings?->getMeta('tax_rate', 0) ?? 0); @endphp
            @if($taxRate > 0)
            <div class="pi-total-line">
                <span class="pi-total-lbl">GST ({{ $taxRate }}%)</span>
                <span class="pi-total-val">Rs. {{ number_format(round((float)$invoice->net_amount * $taxRate / (100 + $taxRate), 2), 2) }}</span>
            </div>
            @endif
            @if($invoice->payment_method)
            <div class="pi-total-line">
                <span class="pi-total-lbl">Payment Method</span>
                <span class="pi-total-val">{{ ucfirst($invoice->payment_method) }}</span>
            </div>
            @endif
            <div class="pi-total-line pi-grand">
                <span class="pi-total-lbl">Net Payable</span>
                <span class="pi-total-val">Rs. {{ number_format($invoice->net_amount ?? $invoice->total_amount, 2) }}</span>
            </div>
        </div>
    </div>

    {{-- REFERRER --}}
    @if($invoice->referrer_name)
    <div class="pi-referrer">
        <div class="pi-meta-lbl" style="color:#92400e;">External Referrer</div>
        <div class="pi-meta-val">
            <strong>{{ $invoice->referrer_name }}</strong>
            @if($invoice->referrer_percentage) &mdash; Commission: {{ $invoice->referrer_percentage }}% @endif
        </div>
    </div>
    @endif

    {{-- FBR COMPLIANCE BLOCK --}}
    @if($hasFbr)
    <div class="pi-fbr">
        <div class="pi-fbr-data">
            @if($invoice->fbr_invoice_number)
                <div class="pi-fbr-lbl">FBR Invoice Number</div>
                <div class="pi-fbr-val">{{ $invoice->fbr_invoice_number }}</div>
            @endif
            @if($invoice->fbr_irn && $invoice->fbr_irn !== $invoice->fbr_invoice_number)
                <div class="pi-fbr-lbl">FBR IRN</div>
                <div class="pi-fbr-val">{{ $invoice->fbr_irn }}</div>
            @endif
            @if($invoice->fbr_invoice_seq)
                <div class="pi-fbr-lbl">FBR Sequence #</div>
                <div class="pi-fbr-val">{{ number_format($invoice->fbr_invoice_seq) }}</div>
            @endif
            @if($ntn)
                <div class="pi-fbr-lbl">Seller NTN</div>
                <div class="pi-fbr-val">{{ $ntn }}</div>
            @endif
            @if($strn)
                <div class="pi-fbr-lbl">Seller STRN</div>
                <div class="pi-fbr-val">{{ $strn }}</div>
            @endif
            @if($invoice->fbr_submitted_at)
                <div class="pi-fbr-lbl">Submitted to FBR</div>
                <div class="pi-fbr-val">{{ $invoice->fbr_submitted_at->format('d M Y, H:i:s') }}</div>
            @endif
            @if($invoice->fbr_signature)
                <div class="pi-fbr-lbl">Digital Signature (HMAC-SHA256)</div>
                <div class="pi-fbr-val" style="font-size:6px;word-break:break-all;">{{ $invoice->fbr_signature }}</div>
            @endif
            <div style="margin-top:4px;font-size:7px;color:#1a56a0;">
                &#10004; This invoice has been electronically submitted to the Federal Board of Revenue (PRAL DI API v1.12).
            </div>
        </div>
        @if($invoice->fbr_qr_code)
        <div class="pi-fbr-qr" id="pi-qr-container"></div>
        @endif
    </div>
    @endif

    {{-- FOOTER --}}
    <div class="pi-footer">
        This is a computer-generated invoice and does not require a signature. |
        {{ $bizName ?: config('app.name') }}
        @if($bizAddr) | {{ $bizAddr }} @endif
        @if($bizPhone) | Tel: {{ $bizPhone }} @endif
    </div>
</div>
