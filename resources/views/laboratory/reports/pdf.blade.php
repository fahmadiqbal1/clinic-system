<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lab Report #{{ $invoice->id }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; background: #fff; line-height: 1.3; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 6px; margin-bottom: 8px; }
        .clinic-name { font-size: 15px; font-weight: bold; color: #2563eb; }
        .clinic-sub { font-size: 9px; color: #555; margin-top: 1px; }
        .report-title { font-size: 12px; font-weight: bold; text-align: right; color: #1a1a1a; }
        .report-id { font-size: 9px; color: #555; text-align: right; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .info-table td { padding: 3px 6px; font-size: 9px; }
        .info-table .label { font-weight: bold; color: #555; width: 110px; }
        .info-table .value { color: #1a1a1a; }
        .section-title { font-size: 10px; font-weight: bold; background: #e8f0fe; color: #2563eb; padding: 3px 6px; margin: 6px 0 4px 0; border-left: 3px solid #2563eb; }
        .results-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .results-table th { background: #f1f5f9; font-size: 8px; font-weight: bold; color: #555; padding: 3px 5px; border: 1px solid #cbd5e1; text-align: left; }
        .results-table td { padding: 3px 5px; border: 1px solid #e2e8f0; font-size: 9px; vertical-align: middle; }
        .results-table tr:nth-child(even) td { background: #f8fafc; }
        .flag { font-weight: bold; color: #dc2626; }
        .report-section { margin-bottom: 8px; }
        .report-text { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px; font-size: 9px; line-height: 1.5; white-space: pre-wrap; }
        .footer { margin-top: 12px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .stamp-area { display: inline-block; width: 45%; vertical-align: top; }
        .stamp-line { border-top: 1px solid #aaa; margin-top: 24px; padding-top: 3px; font-size: 8px; color: #555; text-align: center; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 6px 0; }
        .badge-completed { display: inline-block; background: #dcfce7; color: #166534; border: 1px solid #86efac; padding: 1px 6px; font-size: 8px; font-weight: bold; }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:60%; vertical-align:top;">
                <div class="clinic-name">{{ config('app.name') }}</div>
                <div class="clinic-sub">Laboratory Department</div>
            </td>
            <td style="width:40%; text-align:right; vertical-align:top;">
                <div class="report-title">Laboratory Report</div>
                <div class="report-id">Report #{{ $invoice->id }} &nbsp;|&nbsp; {{ $invoice->created_at?->format('d M Y') }}</div>
                @if($invoice->isWorkCompleted() || $invoice->status === 'completed')
                    <div style="margin-top:4px;"><span class="badge-completed">&#10003; Completed</span></div>
                @endif
            </td>
        </tr>
    </table>
</div>

{{-- Patient & Order Info --}}
@php
    $patient = $invoice->patient;
    $doctor  = $invoice->prescribingDoctor;
    $performer = $invoice->performedBy ?? null;
@endphp
<table class="info-table" style="border:1px solid #e2e8f0;">
    <tr>
        <td class="label">Patient Name</td>
        <td class="value">{{ $patient ? ($patient->first_name . ' ' . $patient->last_name) : 'N/A' }}</td>
        <td class="label">Order Date</td>
        <td class="value">{{ $invoice->created_at?->format('d M Y H:i') }}</td>
    </tr>
    <tr>
        <td class="label">Patient ID</td>
        <td class="value">{{ $patient?->id ?? 'N/A' }}</td>
        <td class="label">Completed Date</td>
        <td class="value">{{ $invoice->updated_at?->format('d M Y H:i') }}</td>
    </tr>
    <tr>
        <td class="label">Referring Doctor</td>
        <td class="value">{{ $doctor?->name ?? 'N/A' }}</td>
        <td class="label">Performed By</td>
        <td class="value">{{ $performer?->name ?? 'Laboratory Staff' }}</td>
    </tr>
    <tr>
        <td class="label">Order #</td>
        <td class="value">{{ $invoice->id }}</td>
        <td class="label">Status</td>
        <td class="value">{{ ucfirst($invoice->status) }}</td>
    </tr>
</table>

{{-- Ordered Tests --}}
@if($invoice->items->count() > 0)
<div class="section-title">Ordered Tests</div>
<table class="results-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Test Name</th>
            <th>Code</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $item->description ?? $item->serviceCatalog?->name ?? 'N/A' }}</td>
            <td>{{ $item->serviceCatalog?->code ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Structured Lab Results --}}
@php
    $rawResults = $invoice->lab_results ?? [];
    $isLegacyFlat = is_array($rawResults) && array_is_list($rawResults);
    if ($isLegacyFlat && count($rawResults) > 0) {
        $groupedResults = ['general' => $rawResults];
    } elseif (!$isLegacyFlat) {
        $groupedResults = (array) $rawResults;
    } else {
        $groupedResults = [];
    }

    $sections = [];
    if ($invoice->items->count() > 0) {
        foreach ($invoice->items as $item) {
            $key = (string) $item->id;
            $saved = $groupedResults[$key] ?? [];
            if (count($saved) > 0) {
                $sections[] = [
                    'label' => $item->description ?? $item->serviceCatalog?->name ?? 'Test',
                    'code'  => $item->serviceCatalog?->code ?? '',
                    'rows'  => $saved,
                ];
            }
        }
        if (isset($groupedResults['general']) && count($groupedResults['general']) > 0) {
            $sections[] = ['label' => 'General Results', 'code' => '', 'rows' => $groupedResults['general']];
        }
    } elseif (isset($groupedResults['general'])) {
        $sections[] = ['label' => 'Results', 'code' => '', 'rows' => $groupedResults['general']];
    }
@endphp

@if(count($sections) > 0)
<div class="section-title">Test Results</div>
@foreach($sections as $section)
    <div style="font-size:11px; font-weight:bold; color:#1e3a8a; margin:8px 0 4px 0;">
        {{ $section['label'] }}@if($section['code']) &nbsp;<span style="font-size:10px; color:#64748b;">({{ $section['code'] }})</span>@endif
    </div>
    <table class="results-table">
        <thead>
            <tr>
                <th style="width:35%;">Parameter</th>
                <th style="width:20%;">Result</th>
                <th style="width:15%;">Unit</th>
                <th style="width:30%;">Reference Range</th>
            </tr>
        </thead>
        <tbody>
            @foreach($section['rows'] as $row)
            @php
                // Simple out-of-range flag: try to detect numeric result outside reference range
                $isOutOfRange = false;
                $ref = $row['reference_range'] ?? '';
                $result = $row['result'] ?? '';
                if ($ref && is_numeric($result) && preg_match('/^([\d.]+)\s*[-–]\s*([\d.]+)$/', trim($ref), $m)) {
                    $isOutOfRange = (float)$result < (float)$m[1] || (float)$result > (float)$m[2];
                }
            @endphp
            <tr>
                <td>{{ $row['test_name'] ?? '' }}</td>
                <td class="{{ $isOutOfRange ? 'flag' : '' }}">
                    {{ $result }}{{ $isOutOfRange ? ' *' : '' }}
                </td>
                <td>{{ $row['unit'] ?? '—' }}</td>
                <td>{{ $ref ?: '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endforeach
@if(collect($sections)->flatMap(fn($s) => $s['rows'])->contains(fn($r) => true))
<p style="font-size:10px; color:#dc2626; margin-top:4px;">* Values marked with asterisk are outside normal reference range.</p>
@endif
@endif

{{-- Lab Report Text --}}
@if($invoice->report_text)
<div class="section-title">Interpretation / Report</div>
<div class="report-text">{{ $invoice->report_text }}</div>
@endif

{{-- Footer / Signatures --}}
<div class="footer">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:50%; vertical-align:bottom;">
                <div class="stamp-line">
                    Performed By: {{ $performer?->name ?? 'Laboratory Technician' }}
                </div>
            </td>
            <td style="width:50%; vertical-align:bottom;">
                <div class="stamp-line">
                    Authorized By / Doctor Stamp
                </div>
            </td>
        </tr>
    </table>
    <p style="font-size:9px; color:#94a3b8; text-align:center; margin-top:10px;">
        Generated on {{ now()->format('d M Y H:i') }} &nbsp;|&nbsp; {{ config('app.name') }} Laboratory Information System
    </p>
</div>

</body>
</html>
