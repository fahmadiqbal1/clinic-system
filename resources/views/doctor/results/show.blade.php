@extends('layouts.app')
@section('title', 'Result — ' . $invoice->service_name . ' — ' . config('app.name'))

@section('content')
<div class="fade-in">

    {{-- Header --}}
    <div class="page-header mb-3">
        <div>
            <h1 class="page-title">
                @if($invoice->department === 'lab')
                    <i class="bi bi-droplet me-2" style="color:var(--accent-info);"></i>Lab Result
                @else
                    <i class="bi bi-broadcast me-2" style="color:var(--accent-warning);"></i>Radiology Result
                @endif
            </h1>
            <p class="page-subtitle mb-0">{{ $invoice->service_name }} — {{ $invoice->patient?->full_name }}</p>
        </div>
        <a href="{{ route('doctor.results.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Results</a>
    </div>

    {{-- Patient summary bar --}}
    <div class="glass-card mb-4 fade-in delay-1 py-3">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="small fw-semibold" style="color:var(--text-muted);">Patient</div>
                <div class="fw-medium">{{ $invoice->patient?->full_name ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small fw-semibold" style="color:var(--text-muted);">Patient ID</div>
                <div class="fw-medium">{{ $invoice->patient?->id ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small fw-semibold" style="color:var(--text-muted);">Ordered</div>
                <div class="fw-medium">{{ $invoice->created_at->format('M d, Y') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small fw-semibold" style="color:var(--text-muted);">Completed</div>
                <div class="fw-medium">{{ $invoice->updated_at->format('M d, Y') }}</div>
            </div>
        </div>
        @if($invoice->performer)
        <div class="mt-2 text-center small" style="color:var(--text-muted);">
            <i class="bi bi-person-check me-1"></i>Performed by <strong>{{ $invoice->performer->name }}</strong>
        </div>
        @endif
    </div>

    {{-- Lab structured results --}}
    @if($invoice->department === 'lab' && $invoice->lab_results)
        @php
            $rawResults = $invoice->lab_results;
            $isFlat     = is_array($rawResults) && array_is_list($rawResults);
            $grouped    = $isFlat && count($rawResults) > 0
                ? ['general' => $rawResults]
                : (array) $rawResults;
            $labItemMap = $invoice->items->keyBy('id');
        @endphp

        @foreach($grouped as $sectionKey => $rows)
            @php
                $sectionLabel = $sectionKey === 'general'
                    ? ($invoice->service_name ?? 'Results')
                    : ($labItemMap[$sectionKey]->description
                        ?? $labItemMap[$sectionKey]->serviceCatalog?->name
                        ?? 'Test');
            @endphp
            <div class="glass-card mb-4 fade-in delay-2">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-clipboard2-pulse me-2" style="color:var(--accent-info);"></i>
                    <h6 class="fw-semibold mb-0">{{ $sectionLabel }}</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Result</th>
                                <th>Unit</th>
                                <th>Reference Range</th>
                                <th>Flag</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach((array) $rows as $row)
                            @php
                                $val   = trim($row['result'] ?? '');
                                $range = trim($row['reference_range'] ?? '');
                                $flag  = '';
                                $flagClass = '';
                                // Simple numeric out-of-range detection
                                if (is_numeric($val) && preg_match('/^([\d.]+)\s*[-–]\s*([\d.]+)$/', $range, $m)) {
                                    $n = (float) $val;
                                    if ($n < (float) $m[1]) { $flag = 'Low ↓'; $flagClass = 'color:var(--accent-info);'; }
                                    elseif ($n > (float) $m[2]) { $flag = 'High ↑'; $flagClass = 'color:var(--accent-danger);'; }
                                } elseif (is_numeric($val) && preg_match('/^<\s*([\d.]+)$/', $range, $m) && (float)$val >= (float)$m[1]) {
                                    $flag = 'High ↑'; $flagClass = 'color:var(--accent-danger);';
                                } elseif (is_numeric($val) && preg_match('/^>\s*([\d.]+)$/', $range, $m) && (float)$val <= (float)$m[1]) {
                                    $flag = 'Low ↓'; $flagClass = 'color:var(--accent-info);';
                                }
                            @endphp
                            <tr @if($flag) style="background:rgba(var(--accent-{{ $flag === 'High ↑' ? 'danger' : 'info' }}-rgb),0.06);" @endif>
                                <td class="fw-medium">{{ $row['test_name'] ?? '' }}</td>
                                <td class="fw-semibold" style="{{ $flag ? $flagClass : 'color:var(--accent-primary);' }}">{{ $val ?: '—' }}</td>
                                <td style="color:var(--text-muted);">{{ $row['unit'] ?? '—' }}</td>
                                <td style="color:var(--text-muted);">{{ $range ?: '—' }}</td>
                                <td>
                                    @if($flag)
                                        <span class="fw-semibold small" style="{{ $flagClass }}">{{ $flag }}</span>
                                    @else
                                        <span class="small text-success">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif

    {{-- Radiologist / lab technician report text --}}
    @if($invoice->report_text)
    <div class="glass-card mb-4 fade-in delay-2">
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-file-text me-2" style="color:var(--accent-{{ $invoice->department === 'radiology' ? 'warning' : 'info' }});"></i>
            <h6 class="fw-semibold mb-0">{{ $invoice->department === 'radiology' ? 'Radiologist Report' : 'Lab Technician Report' }}</h6>
        </div>
        <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border); white-space:pre-wrap; font-size:0.93rem;">{{ $invoice->report_text }}</div>
    </div>
    @endif

    {{-- Radiology images --}}
    @if($invoice->department === 'radiology' && !empty($invoice->radiology_images))
    <div class="glass-card mb-4 fade-in delay-3">
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-images me-2" style="color:var(--accent-warning);"></i>
            <h6 class="fw-semibold mb-0">Images & Files ({{ count($invoice->radiology_images) }})</h6>
        </div>
        <div class="row g-3">
            @foreach($invoice->radiology_images as $idx => $imagePath)
            <div class="col-6 col-md-4 col-lg-3">
                <div class="rounded overflow-hidden" style="border:1px solid var(--glass-border);">
                    @if(str_ends_with(strtolower($imagePath), '.pdf'))
                        <a href="{{ Storage::url($imagePath) }}" target="_blank"
                           class="d-flex flex-column align-items-center justify-content-center p-4 text-decoration-none"
                           style="min-height:150px; background:var(--glass-bg);">
                            <i class="bi bi-file-earmark-pdf" style="font-size:2.5rem; color:var(--accent-danger);"></i>
                            <span class="small mt-1" style="color:var(--text-muted);">PDF Report</span>
                        </a>
                    @else
                        <a href="{{ Storage::url($imagePath) }}" target="_blank" data-lightbox="radiology-{{ $invoice->id }}" data-title="Image {{ $idx + 1 }}">
                            <img src="{{ Storage::url($imagePath) }}"
                                 alt="Image {{ $idx + 1 }}"
                                 class="img-fluid w-100"
                                 style="min-height:150px; object-fit:cover; cursor:zoom-in;">
                        </a>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Nothing to show --}}
    @if(!$invoice->report_text && !$invoice->lab_results && (empty($invoice->radiology_images)))
    <div class="empty-state fade-in delay-2">
        <i class="bi bi-hourglass-split" style="font-size:2rem;opacity:0.3;"></i>
        <h6 class="mt-3 mb-1">Results not yet available</h6>
        <p class="small mb-0" style="color:var(--text-muted);">The lab or radiology team has not yet submitted their report.</p>
    </div>
    @endif

</div>
@endsection
