<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payout #{{ $payout->id }}</title>
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
        .hdr-tag-cell    { width: 160px; vertical-align: middle; text-align: right; }
        .hdr-tag { background: #2d6a4f; color: #fff; font-size: 12px; font-weight: bold; padding: 4px 12px; text-transform: uppercase; }
        .divider { border: none; border-top: 2px solid #2d6a4f; margin: 0 0 8px; }

        /* Meta blocks */
        .meta-table      { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta-box        { width: 50%; vertical-align: top; padding: 6px 8px; background: #f4f9f6; }
        .meta-box-right  { padding-left: 14px; text-align: right; }
        .meta-lbl        { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #2d6a4f; margin-bottom: 2px; }
        .meta-val        { font-size: 9px; color: #1a1a2e; line-height: 1.5; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }

        /* Summary boxes */
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .summary-cell { width: 25%; vertical-align: top; padding: 6px 8px; text-align: center; }
        .summary-num { font-size: 16px; font-weight: bold; color: #1a1a2e; }
        .summary-lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #5a6a85; margin-top: 2px; }
        .summary-accent-green { border-bottom: 3px solid #2d6a4f; }
        .summary-accent-blue  { border-bottom: 3px solid #1a56a0; }
        .summary-accent-amber { border-bottom: 3px solid #b45309; }
        .summary-accent-red   { border-bottom: 3px solid #dc2626; }

        /* Line items */
        .items-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 8px; }
        .items-table th { background: #2d6a4f; color: #fff; font-size: 8px; font-weight: bold; padding: 5px 4px; text-align: left; text-transform: uppercase; }
        .items-table td { font-size: 9px; padding: 5px 4px; border-bottom: 1px solid #dde4ef; color: #1a1a2e; }
        .items-table tr:last-child td { border-bottom: none; }
        .tr-alt td { background: #f8faf9; }

        /* Totals */
        .totals-outer  { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .totals-spacer { width: 54%; }
        .totals-box    { width: 46%; vertical-align: top; }
        .totals-inner  { width: 100%; border-collapse: collapse; }
        .totals-inner td { font-size: 9px; padding: 3px 6px; border-bottom: 1px solid #dde4ef; }
        .totals-inner .lbl { color: #5a6a85; }
        .totals-inner .val { text-align: right; font-weight: bold; }
        .row-grand td  { background: #2d6a4f; color: #fff; font-size: 11px; border-bottom: none; }

        /* Status badges */
        .badge-confirmed { background: #d1fae5; color: #065f46; font-weight: bold; font-size: 8px; padding: 1px 5px; }
        .badge-pending   { background: #fef3c7; color: #92400e; font-weight: bold; font-size: 8px; padding: 1px 5px; }
        .badge-approved  { background: #dbeafe; color: #1e40af; font-weight: bold; font-size: 8px; padding: 1px 5px; }
        .badge-rejected  { background: #fee2e2; color: #991b1b; font-weight: bold; font-size: 8px; padding: 1px 5px; }

        /* Footer */
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .footer-td    { font-size: 7px; color: #5a6a85; text-align: center; border-top: 1px solid #dde4ef; padding-top: 5px; }

        /* Signature area */
        .sig-table  { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .sig-cell   { width: 50%; vertical-align: bottom; padding: 0 10px; }
        .sig-line   { border-top: 1px solid #555; margin-top: 30px; padding-top: 3px; font-size: 8px; color: #5a6a85; }
    </style>
</head>
<body>
<div class="page">

@php
    $logoPath = public_path('images/logo JPEG.jpeg');
    if (!file_exists($logoPath)) $logoPath = public_path('images/logo.png');
    if (file_exists($logoPath)) {
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'png' : 'jpeg';
        $logoSrc = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
        list($origW, $origH) = getimagesize($logoPath);
        $displayW = min(120, $origW);
        $displayH = (int) round($displayW * $origH / $origW);
        if ($displayH > 56) { $displayH = 56; $displayW = (int) round($displayH * $origW / $origH); }
    } else {
        $logoSrc = null; $displayW = 120; $displayH = 50;
    }
    $clinicName = config('app.name');
    $salaryComponent = $payout->payout_type === 'monthly' ? ($payout->salary_amount ?? 0) : 0;
    $commissionComponent = $payout->total_amount - $salaryComponent;
    $statusClass = $payout->status === 'confirmed' ? 'badge-confirmed' : 'badge-pending';
    $approvalClass = match($payout->approval_status) { 'approved' => 'badge-approved', 'rejected' => 'badge-rejected', default => 'badge-pending' };
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
            <div class="hdr-clinic-name">{{ $clinicName }}</div>
            <div class="hdr-clinic-sub">Staff Commission & Salary Payout Statement</div>
        </td>
        <td class="hdr-tag-cell">
            <div class="hdr-tag">PAYOUT SLIP</div>
        </td>
    </tr>
</table>
<hr class="divider">

{{-- ── PAYEE / PAYOUT META ── --}}
<table class="meta-table">
    <tr>
        <td class="meta-box">
            <div class="meta-lbl">Payee</div>
            <div class="meta-val">
                <strong>{{ $payout->doctor?->name ?? 'Unknown' }}</strong><br>
                @if($payout->doctor?->email) Email: {{ $payout->doctor->email }}<br> @endif
                @foreach($payout->doctor?->roles ?? [] as $role)
                    Role: {{ $role->name }}<br>
                @endforeach
            </div>
        </td>
        <td class="meta-box meta-box-right">
            <div class="meta-lbl">Payout Details</div>
            <div class="meta-val">
                <strong>Payout #:</strong> {{ $payout->id }}<br>
                <strong>Type:</strong> {{ $payout->payout_type === 'monthly' ? 'Monthly (Salary + Commission)' : 'Daily Commission' }}<br>
                <strong>Period:</strong> {{ $payout->period_start?->format('d M Y') }} &mdash; {{ $payout->period_end?->format('d M Y') }}<br>
                <strong>Created:</strong> {{ $payout->created_at?->format('d M Y, H:i') }}<br>
                <strong>Status:</strong> <span class="{{ $statusClass }}">{{ strtoupper($payout->status) }}</span>
                @if($payout->approval_status)
                    &nbsp;<span class="{{ $approvalClass }}">{{ strtoupper($payout->approval_status) }}</span>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ── SUMMARY BOXES ── --}}
<table class="summary-table">
    <tr>
        <td class="summary-cell summary-accent-green" style="background:#f4f9f6;">
            <div class="summary-num">Rs. {{ number_format($payout->total_amount, 2) }}</div>
            <div class="summary-lbl">Total Amount</div>
        </td>
        <td class="summary-cell summary-accent-blue" style="background:#f4f7fc;">
            <div class="summary-num">Rs. {{ number_format($payout->paid_amount, 2) }}</div>
            <div class="summary-lbl">Paid Amount</div>
        </td>
        @if($payout->payout_type === 'monthly')
        <td class="summary-cell summary-accent-amber" style="background:#fffbeb;">
            <div class="summary-num">Rs. {{ number_format($salaryComponent, 2) }}</div>
            <div class="summary-lbl">Salary Component</div>
        </td>
        <td class="summary-cell summary-accent-green" style="background:#f4f9f6;">
            <div class="summary-num">Rs. {{ number_format($commissionComponent, 2) }}</div>
            <div class="summary-lbl">Commission Component</div>
        </td>
        @else
        <td class="summary-cell" style="background:#f9fafb;">
            <div class="summary-num">{{ $payout->revenueLedgers?->count() ?? 0 }}</div>
            <div class="summary-lbl">Revenue Entries</div>
        </td>
        <td class="summary-cell {{ $payout->outstanding_balance > 0 ? 'summary-accent-red' : '' }}" style="background:#f9fafb;">
            <div class="summary-num">Rs. {{ number_format($payout->outstanding_balance ?? 0, 2) }}</div>
            <div class="summary-lbl">Outstanding Balance</div>
        </td>
        @endif
    </tr>
</table>

{{-- ── REVENUE LEDGER ENTRIES ── --}}
@if($payout->revenueLedgers && $payout->revenueLedgers->count() > 0)
<table class="items-table">
    <thead>
        <tr>
            <th style="width:8%;">#</th>
            <th style="width:18%;">Invoice</th>
            <th style="width:24%;">Role Type</th>
            <th style="width:22%;" class="text-right">Amount</th>
            <th style="width:28%;">Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($payout->revenueLedgers as $i => $entry)
        <tr class="{{ $i % 2 === 1 ? 'tr-alt' : '' }}">
            <td>{{ $i + 1 }}</td>
            <td>#{{ $entry->invoice_id }}</td>
            <td>{{ $entry->role_type }}</td>
            <td class="text-right" style="font-weight:bold;">Rs. {{ number_format($entry->amount, 2) }}</td>
            <td>{{ $entry->created_at?->format('d M Y') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── TOTALS ── --}}
<table class="totals-outer">
    <tr>
        <td class="totals-spacer"></td>
        <td class="totals-box">
            <table class="totals-inner">
                @if($payout->payout_type === 'monthly')
                <tr>
                    <td class="lbl">Salary</td>
                    <td class="val">Rs. {{ number_format($salaryComponent, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Commission</td>
                    <td class="val">Rs. {{ number_format($commissionComponent, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="lbl">Total Amount</td>
                    <td class="val">Rs. {{ number_format($payout->total_amount, 2) }}</td>
                </tr>
                @if($payout->outstanding_balance > 0)
                <tr>
                    <td class="lbl">Outstanding Balance</td>
                    <td class="val" style="color:#dc2626;">Rs. {{ number_format($payout->outstanding_balance, 2) }}</td>
                </tr>
                @endif
                <tr class="row-grand">
                    <td class="lbl" style="color:#fff;">Net Paid</td>
                    <td class="val" style="color:#fff;">Rs. {{ number_format($payout->paid_amount, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── APPROVAL / AUDIT TRAIL ── --}}
<table class="meta-table">
    <tr>
        <td class="meta-box" style="width:100%; background:#f9fafb;">
            <div class="meta-lbl">Audit Trail</div>
            <div class="meta-val">
                <strong>Created by:</strong> {{ $payout->creator?->name ?? 'System' }}
                &nbsp;|&nbsp; {{ $payout->created_at?->format('d M Y, H:i') }}
                @if($payout->approver)
                    <br><strong>{{ $payout->approval_status === 'approved' ? 'Approved' : 'Reviewed' }} by:</strong> {{ $payout->approver->name }}
                    @if($payout->approved_at) &nbsp;|&nbsp; {{ $payout->approved_at->format('d M Y, H:i') }} @endif
                @endif
                @if($payout->confirmer)
                    <br><strong>Confirmed by:</strong> {{ $payout->confirmer->name }}
                    @if($payout->confirmed_at) &nbsp;|&nbsp; {{ $payout->confirmed_at->format('d M Y, H:i') }} @endif
                @endif
                @if($payout->correction_of_id)
                    <br><strong>Correction of:</strong> Payout #{{ $payout->correction_of_id }}
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ── SIGNATURE AREA ── --}}
<table class="sig-table">
    <tr>
        <td class="sig-cell">
            <div class="sig-line">Authorized Signature (Clinic)</div>
        </td>
        <td class="sig-cell">
            <div class="sig-line">Received by ({{ $payout->doctor?->name ?? 'Payee' }})</div>
        </td>
    </tr>
</table>

{{-- ── FOOTER ── --}}
<table class="footer-table">
    <tr>
        <td class="footer-td">
            This is a computer-generated payout statement. &nbsp;|&nbsp;
            {{ $clinicName }} &nbsp;|&nbsp; Generated: {{ now()->format('d M Y, H:i') }}
        </td>
    </tr>
</table>

</div>
</body>
</html>
