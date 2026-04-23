<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription #{{ $prescription->id }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        html, body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; line-height: 1.4; color: #1a1a2e; background: #fff; }

        /* Header */
        .hdr-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .hdr-rx-cell { width: 40px; vertical-align: middle; }
        .hdr-rx { font-size: 28px; color: #047857; font-weight: bold; }
        .hdr-clinic-cell { vertical-align: middle; padding-left: 8px; }
        .hdr-clinic-name { font-size: 15px; font-weight: bold; color: #047857; }
        .hdr-clinic-sub  { font-size: 8px; color: #5a6a85; margin-top: 1px; }
        .hdr-doc-cell    { vertical-align: middle; text-align: right; }
        .hdr-doc-name    { font-size: 11px; font-weight: bold; color: #1a1a2e; }
        .hdr-doc-date    { font-size: 8px; color: #5a6a85; }
        .divider { border: none; border-top: 2px solid #047857; margin: 0 0 8px; }

        /* Patient info */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta-box { vertical-align: top; padding: 5px 8px; background: #f0fdf4; }
        .meta-lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #047857; margin-bottom: 1px; }
        .meta-val { font-size: 9px; color: #1a1a2e; line-height: 1.5; }

        /* Diagnosis */
        .diag-box { padding: 5px 8px; background: #fef3c7; border-left: 3px solid #f59e0b; margin-bottom: 8px; }
        .diag-lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #92400e; margin-bottom: 1px; }
        .diag-val { font-size: 9px; color: #1a1a2e; }

        /* Medications table */
        .meds-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 8px; }
        .meds-table th { background: #047857; color: #fff; font-size: 8px; font-weight: bold; padding: 4px 5px; text-align: left; text-transform: uppercase; }
        .meds-table td { font-size: 9px; padding: 4px 5px; border-bottom: 1px solid #dde4ef; color: #1a1a2e; }
        .meds-table tr:last-child td { border-bottom: none; }
        .tr-alt td { background: #f8faff; }
        .text-right { text-align: right; }

        /* Notes */
        .notes-box { padding: 5px 8px; background: #f3f4f6; margin-bottom: 8px; font-size: 9px; }
        .notes-lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #047857; margin-bottom: 1px; }

        /* Signature */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .sig-cell { width: 50%; vertical-align: bottom; }
        .sig-line { border-top: 1px solid #1a1a2e; margin-top: 24px; padding-top: 3px; font-size: 9px; color: #5a6a85; }

        /* Footer */
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .footer-td { font-size: 7px; color: #5a6a85; text-align: center; border-top: 1px solid #dde4ef; padding-top: 5px; }
    </style>
</head>
<body>

@php
    $logoPath = public_path('images/logo JPEG.jpeg');
    if (!file_exists($logoPath)) {
        $logoPath = public_path('images/logo.png');
    }
    $logoSrc = null;
    if (file_exists($logoPath)) {
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'png' : 'jpeg';
        $logoSrc = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
        list($origW, $origH) = getimagesize($logoPath);
        $displayW = min(100, $origW);
        $displayH = (int) round($displayW * $origH / $origW);
        if ($displayH > 45) { $displayH = 45; $displayW = (int) round($displayH * $origW / $origH); }
    }
@endphp

{{-- Header --}}
<table class="hdr-table">
    <tr>
        @if($logoSrc)
        <td style="width:{{ $displayW + 6 }}px; vertical-align:middle;">
            <img src="{{ $logoSrc }}" width="{{ $displayW }}" height="{{ $displayH }}" alt="Logo" style="display:block;">
        </td>
        @else
        <td class="hdr-rx-cell"><span class="hdr-rx">&#8478;</span></td>
        @endif
        <td class="hdr-clinic-cell">
            <div class="hdr-clinic-name">Aviva HealthCare</div>
            <div class="hdr-clinic-sub">Excellence in Medical Care</div>
        </td>
        <td class="hdr-doc-cell">
            <div class="hdr-doc-name">Dr. {{ $prescription->doctor->name }}</div>
            <div class="hdr-doc-date">{{ $prescription->created_at->format('d M Y') }}</div>
            <div class="hdr-doc-date">Prescription #{{ $prescription->id }}</div>
        </td>
    </tr>
</table>
<hr class="divider">

{{-- Patient Info --}}
<table class="meta-table">
    <tr>
        <td class="meta-box" style="width:50%;">
            <div class="meta-lbl">Patient</div>
            <div class="meta-val">
                <strong>{{ $prescription->patient->full_name }}</strong>
                @if($prescription->patient->date_of_birth)
                    &nbsp;&mdash;&nbsp;{{ $prescription->patient->date_of_birth->age }} years
                @endif
                @if($prescription->patient->gender)
                    &nbsp;({{ ucfirst($prescription->patient->gender) }})
                @endif
            </div>
        </td>
        <td class="meta-box" style="width:50%; text-align:right;">
            <div class="meta-lbl">Visit Information</div>
            <div class="meta-val">
                Date: {{ $prescription->created_at->format('d M Y, H:i') }}
            </div>
        </td>
    </tr>
</table>

{{-- Diagnosis --}}
@if($prescription->diagnosis)
<div class="diag-box">
    <div class="diag-lbl">Diagnosis</div>
    <div class="diag-val">{{ $prescription->diagnosis }}</div>
</div>
@endif

{{-- Rx Symbol + Medications --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:4px;">
    <tr>
        <td style="font-size:20px; color:#047857; font-weight:bold; width:30px; vertical-align:middle;">&#8478;</td>
        <td style="font-size:10px; font-weight:bold; color:#047857; text-transform:uppercase; vertical-align:middle;">Prescribed Medications</td>
    </tr>
</table>

<table class="meds-table">
    <thead>
        <tr>
            <th style="width:5%;">#</th>
            <th style="width:28%;">Medication</th>
            <th style="width:17%;">Dosage</th>
            <th style="width:17%;">Frequency</th>
            <th style="width:13%;">Duration</th>
            <th style="width:8%;" class="text-right">Qty</th>
            <th style="width:12%;">Instructions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($prescription->items as $i => $item)
        <tr class="{{ $i % 2 === 1 ? 'tr-alt' : '' }}">
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $item->medication_name }}</strong></td>
            <td>{{ $item->dosage ?? '—' }}</td>
            <td>{{ $item->frequency ?? '—' }}</td>
            <td>{{ $item->duration ?? '—' }}</td>
            <td class="text-right">{{ $item->quantity ?? '—' }}</td>
            <td style="font-size:8px;">{{ $item->instructions ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Notes --}}
@if($prescription->notes)
<div class="notes-box">
    <div class="notes-lbl">Additional Notes</div>
    {{ $prescription->notes }}
</div>
@endif

{{-- Signature --}}
<table class="sig-table">
    <tr>
        <td class="sig-cell">&nbsp;</td>
        <td class="sig-cell" style="text-align:right;">
            <div style="text-align:center; display:inline-block; width:180px;">
                <div class="sig-line">
                    <strong>Dr. {{ $prescription->doctor->name }}</strong><br>
                    Signature &amp; Stamp
                </div>
            </div>
        </td>
    </tr>
</table>

{{-- Footer --}}
<table class="footer-table">
    <tr>
        <td class="footer-td">
            This prescription is valid for 7 days from the date of issue. &nbsp;|&nbsp;
            Keep medicines out of reach of children. &nbsp;|&nbsp;
            Aviva HealthCare &mdash; Excellence in Medical Care
        </td>
    </tr>
</table>

</body>
</html>
