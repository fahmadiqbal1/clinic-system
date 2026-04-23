<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $invoice->id }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        html, body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; line-height: 1.4; color: #1a1a2e; background: #fff; }
        .page { padding: 0; }

        /* Header */
        .hdr-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .hdr-logo-cell { width: 120px; vertical-align: middle; }
        .hdr-clinic-cell { vertical-align: middle; padding-left: 10px; }
        .hdr-clinic-name { font-size: 15px; font-weight: bold; color: #1a56a0; }
        .hdr-clinic-sub  { font-size: 8px; color: #5a6a85; margin-top: 1px; }
        .hdr-tag-cell    { width: 130px; vertical-align: middle; text-align: right; }
        .hdr-tag { background: #1a56a0; color: #fff; font-size: 13px; font-weight: bold; padding: 4px 12px; text-transform: uppercase; }
        .divider { border: none; border-top: 2px solid #1a56a0; margin: 0 0 8px; }

        /* Meta blocks */
        .meta-table      { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta-box        { width: 50%; vertical-align: top; padding: 6px 8px; background: #f4f7fc; }
        .meta-box-right  { padding-left: 14px; text-align: right; }
        .meta-lbl        { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #1a56a0; margin-bottom: 2px; }
        .meta-val        { font-size: 9px; color: #1a1a2e; line-height: 1.5; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }

        /* Line items */
        .items-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 8px; }
        .items-table th { background: #1a56a0; color: #fff; font-size: 8px; font-weight: bold; padding: 5px 4px; text-align: left; text-transform: uppercase; }
        .items-table td { font-size: 9px; padding: 5px 4px; border-bottom: 1px solid #dde4ef; color: #1a1a2e; }
        .items-table tr:last-child td { border-bottom: none; }
        .tr-alt td { background: #f8faff; }

        /* Totals */
        .totals-outer  { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .totals-spacer { width: 54%; }
        .totals-box    { width: 46%; vertical-align: top; }
        .totals-inner  { width: 100%; border-collapse: collapse; }
        .totals-inner td { font-size: 9px; padding: 3px 6px; border-bottom: 1px solid #dde4ef; }
        .totals-inner .lbl { color: #5a6a85; }
        .totals-inner .val { text-align: right; font-weight: bold; }
        .row-grand td  { background: #1a56a0; color: #fff; font-size: 11px; border-bottom: none; }

        /* Report block */
        .report-outer { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .report-cell  { padding: 6px 8px; background: #f9fafb; border: 1px solid #dde4ef; font-size: 9px; line-height: 1.5; }
        .report-lbl   { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #1a56a0; margin-bottom: 3px; }

        /* FBR block */
        .fbr-outer     { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .fbr-data-cell { vertical-align: top; padding: 6px 8px; background: #eef4ff; border: 1px solid #c3d0ea; }
        .fbr-qr-cell   { width: 84px; vertical-align: middle; text-align: center; padding: 5px; background: #eef4ff; border: 1px solid #c3d0ea; border-left: none; }
        .fbr-lbl       { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #1a56a0; margin-bottom: 1px; }
        .fbr-val       { font-size: 8px; color: #1a1a2e; margin-bottom: 4px; }
        .fbr-caption   { font-size: 7px; color: #5a6a85; margin-top: 3px; text-align: center; }

        /* Footer */
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .footer-td    { font-size: 7px; color: #5a6a85; text-align: center; border-top: 1px solid #dde4ef; padding-top: 5px; }

        /* Badges */
        .badge-paid      { background: #d1fae5; color: #065f46; font-weight: bold; font-size: 8px; padding: 1px 5px; }
        .badge-pending   { background: #fef3c7; color: #92400e; font-weight: bold; font-size: 8px; padding: 1px 5px; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; font-weight: bold; font-size: 8px; padding: 1px 5px; }
    </style>
</head>
<body>
<div class="page">

@php
    $logoPath = public_path('images/logo JPEG.jpeg');
    if (!file_exists($logoPath)) {
        $logoPath = public_path('images/logo.png');
    }
    if (file_exists($logoPath)) {
        $ext      = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime     = ($ext === 'png') ? 'png' : 'jpeg';
        $logoSrc  = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
        list($origW, $origH) = getimagesize($logoPath);
        $displayW = min(120, $origW);
        $displayH = (int) round($displayW * $origH / $origW);
        if ($displayH > 56) { $displayH = 56; $displayW = (int) round($displayH * $origW / $origH); }
    } else {
        $logoSrc = null; $displayW = 120; $displayH = 50;
    }
    $ntn      = $fbr?->getMeta('ntn')              ?? '';
    $strn     = $fbr?->getMeta('strn')             ?? '';
    $bizName  = $fbr?->getMeta('business_name')    ?? config('app.name');
    $bizAddr  = $fbr?->getMeta('business_address') ?? '';
    $province = $fbr?->getMeta('seller_province')  ?? '';
    $bizPhone = $fbr?->getMeta('business_phone')   ?? '';
    $hasFbr   = $invoice->fbr_irn;
    $hasItems = $invoice->items->count() > 0;
    $deptLabel = match($invoice->department) {
        'lab'          => 'Laboratory',
        'radiology'    => 'Radiology',
        'pharmacy'     => 'Pharmacy',
        'consultation' => 'Consultation',
        default        => ucfirst($invoice->department ?? 'General'),
    };
@endphp

{{-- ── HEADER ── --}}
<table class="hdr-table">
    <tr>
        <td class="hdr-logo-cell" style="width:{{ $displayW + 8 }}px;">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" width="{{ $displayW }}" height="{{ $displayH }}" alt="Logo" style="display:block;">
            @endif
        </td>
        <td class="hdr-clinic-cell">
            <div class="hdr-clinic-name">{{ $bizName ?: config('app.name') }}</div>
            @if($bizAddr)
                <div class="hdr-clinic-sub">{{ $bizAddr }}{{ $province ? ', ' . $province : '' }}</div>
            @endif
            @if($bizPhone)
                <div class="hdr-clinic-sub">Tel: {{ $bizPhone }}</div>
            @endif
            @if($ntn || $strn)
                <div class="hdr-clinic-sub">
                    @if($ntn)NTN: <strong>{{ $ntn }}</strong>@endif
                    @if($ntn && $strn) &nbsp;|&nbsp; @endif
                    @if($strn)STRN: <strong>{{ $strn }}</strong>@endif
                </div>
            @endif
        </td>
        <td class="hdr-tag-cell">
            <div class="hdr-tag">TAX INVOICE</div>
        </td>
    </tr>
</table>
<hr class="divider">

{{-- ── BILL-TO / INVOICE META ── --}}
<table class="meta-table">
    <tr>
        <td class="meta-box">
            <div class="meta-lbl">Billed To</div>
            <div class="meta-val">
                <strong>{{ $invoice->patient->full_name ?? 'Walk-in Patient' }}</strong><br>
                @if($invoice->patient->phone) Phone: {{ $invoice->patient->phone }}<br> @endif
                @if($invoice->patient->email) Email: {{ $invoice->patient->email }}<br> @endif
                @if($invoice->patient->cnic) CNIC: {{ $invoice->patient->cnic }}<br> @endif
                Patient Type: {{ ucfirst($invoice->patient_type ?? 'walk_in') }}
            </div>
        </td>
        <td class="meta-box meta-box-right">
            <div class="meta-lbl">Invoice Details</div>
            <div class="meta-val">
                <strong>Invoice #:</strong> {{ $invoice->id }}<br>
                <strong>Date:</strong> {{ $invoice->created_at->format('d M Y') }}<br>
                @if($invoice->paid_at)
                    <strong>Paid:</strong> {{ $invoice->paid_at->format('d M Y, H:i') }}<br>
                @endif
                @if($invoice->payment_method)
                    <strong>Payment:</strong> {{ ucfirst($invoice->payment_method) }}<br>
                @endif
                <strong>Department:</strong> {{ $deptLabel }}<br>
                <strong>Status:</strong>
                @php $statusClass = match($invoice->status) { 'paid' => 'badge-paid', 'cancelled' => 'badge-cancelled', default => 'badge-pending' }; @endphp
                <span class="{{ $statusClass }}">{{ strtoupper($invoice->status) }}</span>
                @if($invoice->prescribingDoctor)
                    <br><strong>Doctor:</strong> {{ $invoice->prescribingDoctor->name }}
                @endif
                @if($invoice->performer && $invoice->performer->id !== $invoice->prescribing_doctor_id)
                    <br><strong>Performed by:</strong> {{ $invoice->performer->name }}
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ── LINE ITEMS ── --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width:6%;">#</th>
            <th style="width:34%;">Description</th>
            <th style="width:12%;" class="text-center">HS Code</th>
            <th style="width:8%;"  class="text-right">Qty</th>
            <th style="width:16%;" class="text-right">Unit Price</th>
            <th style="width:10%;" class="text-right">Discount</th>
            <th style="width:14%;" class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @if($hasItems)
            {{-- Pharmacy / multi-item invoices --}}
            @foreach($invoice->items as $i => $item)
            <tr class="{{ $i % 2 === 1 ? 'tr-alt' : '' }}">
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->description ?? $item->serviceCatalog?->name ?? 'Service' }}</td>
                <td class="text-center" style="color:#5a6a85;">{{ $item->serviceCatalog?->hs_code ?? '9813.0000' }}</td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">Rs. {{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">&mdash;</td>
                <td class="text-right">Rs. {{ number_format($item->line_total, 2) }}</td>
            </tr>
            @endforeach
        @else
            {{-- Service-only invoices (consultation, lab, radiology) --}}
            @php
                $catalog = $invoice->serviceCatalog;
                $hsCode = $catalog?->hs_code ?? '9813.0000';
            @endphp
            <tr>
                <td>1</td>
                <td>
                    {{ $invoice->service_name ?? $catalog?->name ?? $deptLabel . ' Service' }}
                    @if($catalog?->code)
                        <br><span style="font-size:7px; color:#5a6a85;">Code: {{ $catalog->code }}</span>
                    @endif
                </td>
                <td class="text-center" style="color:#5a6a85;">{{ $hsCode }}</td>
                <td class="text-right">1</td>
                <td class="text-right">Rs. {{ number_format($invoice->total_amount, 2) }}</td>
                <td class="text-right">
                    @if(($invoice->discount_amount ?? 0) > 0 && $invoice->discount_status === 'approved')
                        Rs. {{ number_format($invoice->discount_amount, 2) }}
                    @else
                        &mdash;
                    @endif
                </td>
                <td class="text-right">Rs. {{ number_format($invoice->net_amount ?? $invoice->total_amount, 2) }}</td>
            </tr>
        @endif
    </tbody>
</table>

{{-- ── TOTALS ── --}}
<table class="totals-outer">
    <tr>
        <td class="totals-spacer"></td>
        <td class="totals-box">
            <table class="totals-inner">
                <tr>
                    <td class="lbl">Subtotal</td>
                    <td class="val">Rs. {{ number_format($invoice->total_amount, 2) }}</td>
                </tr>
                @if(($invoice->discount_amount ?? 0) > 0 && $invoice->discount_status === 'approved')
                <tr>
                    <td class="lbl">Discount</td>
                    <td class="val" style="color:#b91c1c;">&minus; Rs. {{ number_format($invoice->discount_amount, 2) }}</td>
                </tr>
                @endif
                @php $taxRate = (float)($fbr?->getMeta('tax_rate', 0) ?? 0); @endphp
                @if($taxRate > 0)
                <tr>
                    <td class="lbl">GST ({{ $taxRate }}%)</td>
                    <td class="val">Rs. {{ number_format(round((float)$invoice->net_amount * $taxRate / (100 + $taxRate), 2), 2) }}</td>
                </tr>
                @endif
                @if($invoice->payment_method)
                <tr>
                    <td class="lbl">Payment Method</td>
                    <td class="val">{{ ucfirst($invoice->payment_method) }}</td>
                </tr>
                @endif
                <tr class="row-grand">
                    <td class="lbl" style="color:#fff;">Net Payable</td>
                    <td class="val" style="color:#fff;">Rs. {{ number_format($invoice->net_amount ?? $invoice->total_amount, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── REPORT TEXT (Lab / Radiology results) ── --}}
@if($invoice->report_text && in_array($invoice->department, ['lab', 'radiology']))
<table class="report-outer">
    <tr>
        <td class="report-cell">
            <div class="report-lbl">{{ $invoice->department === 'lab' ? 'Laboratory Report' : 'Radiology Report' }}</div>
            {!! nl2br(e($invoice->report_text)) !!}
        </td>
    </tr>
</table>
@endif

{{-- ── REFERRER INFORMATION ── --}}
@if($invoice->referrer_name)
<table class="meta-table">
    <tr>
        <td class="meta-box" style="width:100%; background:#fff8f0; border:1px solid #f0d8b5;">
            <div class="meta-lbl" style="color:#92400e;">External Referrer</div>
            <div class="meta-val">
                <strong>{{ $invoice->referrer_name }}</strong>
                @if($invoice->referrer_percentage)
                    &nbsp;&mdash;&nbsp;Commission: {{ $invoice->referrer_percentage }}%
                @endif
            </div>
        </td>
    </tr>
</table>
@endif

{{-- ── FBR COMPLIANCE BLOCK ── --}}
@if($hasFbr || $qrCode)
<table class="fbr-outer">
    <tr>
        <td class="fbr-data-cell">
            @if($invoice->fbr_invoice_number)
                <div class="fbr-lbl">FBR Invoice Number</div>
                <div class="fbr-val">{{ $invoice->fbr_invoice_number }}</div>
            @endif
            @if($invoice->fbr_irn && $invoice->fbr_irn !== $invoice->fbr_invoice_number)
                <div class="fbr-lbl">FBR IRN</div>
                <div class="fbr-val">{{ $invoice->fbr_irn }}</div>
            @endif
            @if($invoice->fbr_invoice_seq)
                <div class="fbr-lbl">FBR Sequence #</div>
                <div class="fbr-val">{{ number_format($invoice->fbr_invoice_seq) }}</div>
            @endif
            @if($ntn)
                <div class="fbr-lbl">Seller NTN</div>
                <div class="fbr-val">{{ $ntn }}</div>
            @endif
            @if($strn)
                <div class="fbr-lbl">Seller STRN</div>
                <div class="fbr-val">{{ $strn }}</div>
            @endif
            @if($invoice->fbr_submitted_at)
                <div class="fbr-lbl">Submitted to FBR</div>
                <div class="fbr-val">{{ $invoice->fbr_submitted_at->format('d M Y, H:i:s') }}</div>
            @endif
            @if($invoice->fbr_signature)
                <div class="fbr-lbl">Digital Signature (HMAC-SHA256)</div>
                <div class="fbr-val" style="font-size:6px; word-break:break-all;">{{ $invoice->fbr_signature }}</div>
            @endif
            <div style="margin-top:4px; font-size:7px; color:#1a56a0;">
                &#10004; This invoice has been electronically submitted to the Federal Board of Revenue (PRAL DI API v1.12).
                Scan the QR code to verify at <strong>iris.fbr.gov.pk</strong>.
            </div>
        </td>
        @if($qrCode)
        <td class="fbr-qr-cell">
            <div class="qr-wrap" style="width:72px; height:72px;">{!! preg_replace('/<svg /', '<svg width="72" height="72" ', $qrCode, 1) !!}</div>
            <div class="fbr-caption">Scan to verify<br>FBR Digital Invoicing</div>
        </td>
        @endif
    </tr>
</table>
@endif

{{-- ── FOOTER ── --}}
<table class="footer-table">
    <tr>
        <td class="footer-td">
            This is a computer-generated invoice and does not require a signature. &nbsp;|&nbsp;
            {{ $bizName ?: config('app.name') }}
            @if($bizAddr) &nbsp;|&nbsp; {{ $bizAddr }} @endif
            @if($bizPhone) &nbsp;|&nbsp; Tel: {{ $bizPhone }} @endif
        </td>
    </tr>
</table>

</div>
</body>
</html>
