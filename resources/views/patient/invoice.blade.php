@extends('layouts.app')
@section('title', ucfirst($invoice->department) . ' Report — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1">
                <i class="bi {{ $invoice->department === 'radiology' ? 'bi-broadcast' : 'bi-droplet' }} me-2" style="color:var(--accent-info);"></i>
                {{ ucfirst($invoice->department) }} Report
            </h2>
            <p class="page-subtitle mb-0">{{ $invoice->service_name }}</p>
        </div>
        <a href="{{ route('patient.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Profile</a>
    </div>

    {{-- Order Details --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-info-circle me-2" style="color:var(--accent-info);"></i>Details</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Service</span>
                    <span class="info-value">{{ $invoice->service_name }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Date</span>
                    <span class="info-value">{{ $invoice->created_at?->format('M d, Y') }}</span>
                </div>
                @if($invoice->prescribingDoctor)
                <div class="info-grid-item">
                    <span class="info-label">Ordered by</span>
                    <span class="info-value">Dr. {{ $invoice->prescribingDoctor->name }}</span>
                </div>
                @endif
                <div class="info-grid-item">
                    <span class="info-label">Status</span>
                    @php
                        $wc = $invoice->isPaid() && $invoice->isWorkCompleted();
                        $sStyle = $wc
                            ? 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);'
                            : 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);';
                    @endphp
                    <span class="badge-glass" style="{{ $sStyle }}">{{ $wc ? 'Completed' : 'In Progress' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Report --}}
    @if($invoice->report_text)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-file-earmark-medical me-2" style="color:var(--accent-success);"></i>Report</div>
        <div class="card-body">
            <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                {!! nl2br(e($invoice->report_text)) !!}
            </div>
        </div>
    </div>
    @endif

    {{-- Lab Results --}}
    @if($invoice->department === 'lab' && $invoice->lab_results)
    @php
        $rawResults = $invoice->lab_results;
        $isLegacyFlat = is_array($rawResults) && array_is_list($rawResults);
        $groupedResults = $isLegacyFlat && count($rawResults) > 0
            ? ['general' => $rawResults]
            : (array) $rawResults;
        $itemMap = $invoice->items->keyBy('id');
    @endphp
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-table me-2" style="color:var(--accent-warning);"></i>Test Results</div>
        <div class="card-body">
            @foreach($groupedResults as $key => $results)
                @php
                    $sectionLabel = $key === 'general'
                        ? ($invoice->service_name ?? 'Results')
                        : ($itemMap[$key]->description ?? $itemMap[$key]->serviceCatalog?->name ?? 'Test');
                @endphp
                <h6 class="fw-semibold mb-2"><i class="bi bi-clipboard2-pulse me-1" style="color:var(--accent-info);"></i>{{ $sectionLabel }}</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Parameter</th><th>Result</th><th>Unit</th><th>Reference</th></tr>
                        </thead>
                        <tbody>
                            @foreach((array) $results as $r)
                            <tr>
                                <td class="fw-medium">{{ $r['test_name'] ?? '' }}</td>
                                <td class="fw-semibold" style="color:var(--accent-primary);">{{ $r['result'] ?? '' }}</td>
                                <td style="color:var(--text-muted);">{{ $r['unit'] ?? '—' }}</td>
                                <td style="color:var(--text-muted);">{{ $r['reference_range'] ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Radiology Images --}}
    @if($invoice->department === 'radiology' && $invoice->radiology_images && count($invoice->radiology_images) > 0)
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-images me-2" style="color:var(--accent-secondary);"></i>Images</div>
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
                                <img src="{{ Storage::url($imagePath) }}" alt="Image {{ $idx + 1 }}" class="img-fluid w-100" style="min-height:160px; object-fit:cover;">
                            </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- AI Analyses --}}
    @if($analyses->count() > 0)
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-robot me-2" style="color:var(--accent-secondary);"></i>AI Analysis</div>
        <div class="card-body">
            @foreach($analyses as $analysis)
            <div class="mb-3 p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                <small style="color:var(--text-muted);">{{ $analysis->created_at->format('M d, Y H:i') }}</small>
                <div class="mt-2" style="color:var(--text-secondary); white-space:pre-line; font-size:0.9rem;">
                    {!! nl2br(e($analysis->ai_response)) !!}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <a href="{{ route('patient.dashboard') }}" class="btn btn-outline-secondary mb-4"><i class="bi bi-arrow-left me-1"></i>Back to Profile</a>
</div>
@endsection
