<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contract — {{ $contract->user?->name ?? 'Staff' }} v{{ $contract->version }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        html, body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 9px; line-height: 1.4; color: #1a1a2e; background: #fff; }

        /* Header */
        .hdr-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .hdr-logo-cell { width: 60px; vertical-align: middle; }
        .hdr-logo-cell img { height: 36px; width: auto; }
        .hdr-clinic-cell { vertical-align: middle; padding-left: 8px; }
        .hdr-clinic-name { font-size: 13px; font-weight: bold; color: #1a56a0; }
        .hdr-clinic-sub  { font-size: 7px; color: #5a6a85; margin-top: 1px; }
        .hdr-tag-cell    { width: 120px; vertical-align: middle; text-align: right; }
        .hdr-tag { background: #1a56a0; color: #fff; font-size: 11px; font-weight: bold; padding: 3px 10px; text-transform: uppercase; }
        .divider { border: none; border-top: 2px solid #1a56a0; margin: 0 0 8px; }

        /* Meta grid - DomPDF table-based layout */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta-cell { width: 50%; vertical-align: top; padding: 5px 8px; background: #f4f7fc; }
        .meta-lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #1a56a0; margin-bottom: 1px; }
        .meta-val { font-size: 8px; color: #1a1a2e; line-height: 1.4; }

        /* Status badge */
        .badge-active { background: #d1fae5; color: #065f46; font-weight: bold; font-size: 8px; padding: 2px 6px; }
        .badge-draft { background: #fef3c7; color: #92400e; font-weight: bold; font-size: 8px; padding: 2px 6px; }
        .badge-superseded { background: #e0e7ff; color: #3730a3; font-weight: bold; font-size: 8px; padding: 2px 6px; }
        .badge-exit { background: #fee2e2; color: #991b1b; font-weight: bold; font-size: 8px; padding: 2px 6px; }

        /* Contract body */
        .contract-body { margin-bottom: 8px; }
        .contract-body h1 { font-size: 13px; color: #1a56a0; margin: 8px 0 4px; border-bottom: 1px solid #dde4ef; padding-bottom: 2px; }
        .contract-body h2 { font-size: 11px; color: #1a56a0; margin: 6px 0 3px; }
        .contract-body h3 { font-size: 10px; color: #334155; margin: 5px 0 2px; }
        .contract-body p { margin: 0 0 4px; font-size: 9px; }
        .contract-body ul, .contract-body ol { margin: 0 0 5px; padding-left: 16px; }
        .contract-body li { font-size: 9px; margin-bottom: 2px; }
        .contract-body strong { font-weight: bold; }
        .contract-body em { font-style: italic; }
        .contract-body hr { border: none; border-top: 1px solid #dde4ef; margin: 6px 0; }
        .contract-body table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        .contract-body th { background: #f4f7fc; font-size: 8px; padding: 3px 5px; border: 1px solid #dde4ef; text-align: left; }
        .contract-body td { font-size: 8px; padding: 3px 5px; border: 1px solid #dde4ef; }
        .contract-body blockquote { border-left: 3px solid #1a56a0; padding: 3px 8px; margin: 5px 0; background: #f8faff; font-style: italic; }

        /* Signature block */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .sig-cell { width: 50%; vertical-align: bottom; padding: 0 10px; }
        .sig-line { border-top: 1px solid #1a1a2e; margin-top: 24px; padding-top: 3px; font-size: 8px; color: #5a6a85; }
        .sig-name { font-size: 9px; font-weight: bold; color: #1a1a2e; }

        /* Footer */
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .footer-td { font-size: 7px; color: #5a6a85; text-align: center; border-top: 1px solid #dde4ef; padding-top: 4px; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

@php
    $logoPath = public_path('images/logo JPEG.jpeg');
    if (!file_exists($logoPath)) {
        $logoPath = public_path('images/logo.png');
    }
    $logoSrc = '';
    if (file_exists($logoPath)) {
        $ext  = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'png' : 'jpeg';
        $logoSrc = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }
@endphp

{{-- Header --}}
<table class="hdr-table">
    <tr>
        @if($logoSrc)
        <td class="hdr-logo-cell">
            <img src="{{ $logoSrc }}" alt="Logo">
        </td>
        @endif
        <td class="hdr-clinic-cell">
            <div class="hdr-clinic-name">{{ config('app.name', 'Clinic') }}</div>
            <div class="hdr-clinic-sub">Employment Contract &mdash; Confidential</div>
        </td>
        <td class="hdr-tag-cell">
            <span class="hdr-tag">CONTRACT</span>
        </td>
    </tr>
</table>
<hr class="divider">

{{-- Metadata --}}
<table class="meta-table">
    <tr>
        <td class="meta-cell">
            <div class="meta-lbl">Staff Member</div>
            <div class="meta-val"><strong>{{ $contract->user?->name ?? 'N/A' }}</strong></div>
            <div class="meta-lbl" style="margin-top:5px;">Role</div>
            <div class="meta-val">{{ $contract->user?->roles?->pluck('name')->join(', ') ?? 'N/A' }}</div>
        </td>
        <td class="meta-cell text-right">
            <div class="meta-lbl">Contract Version</div>
            <div class="meta-val"><strong>v{{ $contract->version }}</strong></div>
            <div class="meta-lbl" style="margin-top:5px;">Status</div>
            <div class="meta-val">
                @if($contract->early_exit_flag)
                    <span class="badge-exit">EARLY EXIT</span>
                @elseif($contract->status === 'active')
                    <span class="badge-active">ACTIVE</span>
                @elseif($contract->status === 'draft')
                    <span class="badge-draft">AWAITING SIGNATURE</span>
                @else
                    <span class="badge-superseded">SUPERSEDED</span>
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="meta-table">
    <tr>
        <td class="meta-cell" style="width:33%;">
            <div class="meta-lbl">Effective From</div>
            <div class="meta-val">{{ $contract->effective_from?->format('M d, Y') ?? 'N/A' }}</div>
        </td>
        <td class="meta-cell" style="width:33%;">
            <div class="meta-lbl">Minimum Term</div>
            <div class="meta-val">{{ $contract->minimum_term_months }} months</div>
        </td>
        <td class="meta-cell" style="width:34%;">
            <div class="meta-lbl">
                @if($contract->isSigned())
                    Signed On
                @else
                    Signature Status
                @endif
            </div>
            <div class="meta-val">
                @if($contract->isSigned())
                    {{ $contract->signed_at->format('M d, Y') }}
                @else
                    Pending
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- Contract Content --}}
<div class="contract-body">
    {!! strip_tags($contract->contract_html_snapshot, '<p><br><h1><h2><h3><h4><h5><h6><strong><em><b><i><u><ul><ol><li><table><thead><tbody><tr><th><td><div><span><hr><blockquote><pre><code><sub><sup><small>') !!}
</div>

{{-- Signature Block --}}
<table class="sig-table">
    <tr>
        <td class="sig-cell">
            <div class="sig-name">{{ $contract->user?->name ?? '________________' }}</div>
            <div class="sig-line">
                Staff Member
                @if($contract->isSigned())
                    &mdash; Signed {{ $contract->signed_at->format('M d, Y') }}
                @endif
            </div>
        </td>
        <td class="sig-cell">
            <div class="sig-name">{{ $contract->creator?->name ?? '________________' }}</div>
            <div class="sig-line">Authorized Representative ({{ config('app.name', 'Clinic') }})</div>
        </td>
    </tr>
</table>

{{-- Footer --}}
<table class="footer-table">
    <tr>
        <td class="footer-td">
            {{ config('app.name', 'Clinic') }} &bull;
            Contract v{{ $contract->version }} &bull;
            Generated {{ now()->format('M d, Y H:i A') }} &bull;
            This document is confidential
        </td>
    </tr>
</table>

</body>
</html>
