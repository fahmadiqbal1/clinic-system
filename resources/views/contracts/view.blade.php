@extends('layouts.app')

@section('title', 'Contract v' . $contract->version . ' — ' . config('app.name'))

@section('content')
<div class="fade-in delay-1">
    <div class="page-header">
        <div>
            <h1 class="h3 fw-bold text-white mb-1">
                <i class="bi bi-file-earmark-richtext me-2"></i>
                Contract &mdash; Version {{ $contract->version }}
            </h1>
            <p class="page-subtitle mb-0">{{ $contract->user?->name ?? 'Unknown Staff' }} — Full document view</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('contracts.pdf', $contract) }}" class="btn btn-primary fw-semibold">
                <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
            </a>
            <button onclick="window.print()" class="btn btn-outline-light fw-semibold">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            <a href="javascript:history.back()" class="btn btn-outline-light fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

{{-- Metadata Bar --}}
<div class="fade-in delay-2 mb-4">
    <div class="glass-card">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="stat-icon stat-icon-primary" style="width:36px;height:36px;font-size:.85rem;">
                        {{ strtoupper(substr($contract->user?->name ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <small class="text-white-50 d-block">Staff Member</small>
                        <span class="fw-semibold text-white">{{ $contract->user?->name ?? 'Unknown' }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <small class="text-white-50 d-block">Status</small>
                @if($contract->early_exit_flag)
                    <span class="badge bg-danger"><i class="bi bi-flag-fill me-1"></i>Early Exit</span>
                @elseif($contract->resignation_notice_submitted_at)
                    <span class="badge bg-warning text-dark"><i class="bi bi-box-arrow-right me-1"></i>Resigned</span>
                @elseif($contract->status === 'active')
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                @elseif($contract->status === 'draft')
                    <span class="badge bg-warning text-dark"><i class="bi bi-pen me-1"></i>Awaiting Signature</span>
                @else
                    <span class="badge bg-secondary"><i class="bi bi-archive me-1"></i>Superseded</span>
                @endif
            </div>
            <div class="col-md-2">
                <small class="text-white-50 d-block">Effective From</small>
                <span class="text-white">{{ $contract->effective_from?->format('M d, Y') ?? '—' }}</span>
            </div>
            <div class="col-md-2">
                <small class="text-white-50 d-block">Minimum Term</small>
                <span class="text-white">{{ $contract->minimum_term_months }} months</span>
            </div>
            <div class="col-md-3">
                <small class="text-white-50 d-block">Signed</small>
                @if($contract->isSigned())
                    <span class="text-success"><i class="bi bi-check2 me-1"></i>{{ $contract->signed_at->format('M d, Y') }}</span>
                @else
                    <span class="text-white-50">Not yet signed</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Contract Content --}}
<div class="fade-in delay-3 mb-4">
    <div class="glass-card" id="contract-document">
        <div class="contract-content text-white">
            {!! strip_tags($contract->contract_html_snapshot, '<p><br><h1><h2><h3><h4><h5><h6><strong><em><b><i><u><ul><ol><li><table><thead><tbody><tr><th><td><div><span><hr><blockquote><pre><code><a><img><sub><sup><small>') !!}
        </div>
    </div>
</div>

{{-- Action Footer --}}
<div class="fade-in delay-4">
    <div class="glass-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="small text-white-50">
                <i class="bi bi-file-earmark me-1"></i>
                Version {{ $contract->version }}
                <span class="mx-2">&middot;</span>
                Created {{ $contract->created_at?->format('M d, Y H:i A') ?? 'N/A' }}
                <span class="mx-2">&middot;</span>
                By {{ $contract->creator?->name ?? 'Unknown' }}
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if ($contract->status === 'draft' && !auth()->user()->hasRole('Owner') && $contract->user_id === auth()->id())
                    <form action="{{ route('contracts.sign', $contract) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success fw-semibold">
                            <i class="bi bi-check-circle me-1"></i> Sign & Accept
                        </button>
                    </form>
                @endif

                @if ($contract->status === 'active' && !auth()->user()->hasRole('Owner') && $contract->user_id === auth()->id() && !$contract->resignation_notice_submitted_at)
                    <form action="{{ route('contracts.resign', $contract) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger fw-semibold"
                            onclick="return confirm('Are you sure you want to submit a resignation notice?')">
                            <i class="bi bi-box-arrow-right me-1"></i> Submit Resignation
                        </button>
                    </form>
                @endif

                @if ($contract->resignation_notice_submitted_at)
                    <span class="badge bg-warning text-dark align-self-center">
                        <i class="bi bi-envelope-exclamation me-1"></i>
                        Resignation submitted {{ $contract->resignation_notice_submitted_at->format('M d, Y') }}
                    </span>
                @endif

                @if ($contract->status === 'active' && auth()->user()->hasRole('Owner') && !$contract->early_exit_flag)
                    <form action="{{ route('contracts.mark-early-exit', $contract) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning fw-semibold"
                            onclick="return confirm('Mark this contract for early exit?')">
                            <i class="bi bi-flag me-1"></i> Mark Early Exit
                        </button>
                    </form>
                @endif

                <a href="{{ route('contracts.pdf', $contract) }}" class="btn btn-primary fw-semibold">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                </a>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
@media print {
    @page { size: A4 portrait; margin: 8mm 10mm; }
    body, html { background:#fff!important; color:#000!important; font-size:10px!important; line-height:1.3!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .navbar,.skip-link,.glass-toast-container,.no-print,.quick-actions,#glassConfirmModal,.introjs-overlay,.breadcrumb,.sidebar,footer,.dropdown-menu,.offcanvas,.modal,[data-no-disable],script,noscript { display:none!important; }
    .btn, form, .alert, details { display:none!important; }
    /* Hide page header and action footer */
    .page-header,.page-subtitle,.fade-in.delay-4 { display:none!important; }
    main { padding:0!important; margin:0!important; min-height:auto!important; }
    .container,.container-fluid,.container-lg { max-width:100%!important; padding:0!important; margin:0!important; }
    .glass-card { background:#fff!important; color:#000!important; border:none!important; box-shadow:none!important; backdrop-filter:none!important; -webkit-backdrop-filter:none!important; padding:0!important; margin:0 0 3px!important; border-radius:0!important; }
    /* Metadata bar compaction */
    .fade-in.delay-2 .glass-card { padding:4px 8px!important; margin-bottom:4px!important; }
    .fade-in.delay-2 .row { margin:0!important; }
    .fade-in.delay-2 .col-md-2,.fade-in.delay-2 .col-md-3 { padding:2px 6px!important; }
    .fade-in.delay-2 small { font-size:8px!important; color:#555!important; }
    .row { margin-left:0!important; margin-right:0!important; } [class*="col-md-"] { padding:1px 4px!important; }
    /* Typography */
    .text-white,.text-white-50,.text-muted,[style*="color:var(--"] { color:#000!important; text-shadow:none!important; }
    .fw-semibold,.fw-bold { color:#000!important; }
    .small,small { font-size:8px!important; }
    .badge,[class*="badge-glass"] { border:1px solid #888!important; color:#000!important; background:#eee!important; font-size:8px!important; padding:0 3px!important; border-radius:2px!important; }
    .stat-icon { width:24px!important; height:24px!important; font-size:.7rem!important; background:#eee!important; color:#000!important; }
    .fade-in { opacity:1!important; animation:none!important; }
    /* Contract body — the main content */
    .contract-content { color:#000!important; font-size:10px!important; line-height:1.3!important; }
    .contract-content h1 { font-size:13px!important; margin:6px 0 3px!important; color:#000!important; }
    .contract-content h2 { font-size:11px!important; margin:4px 0 2px!important; color:#000!important; }
    .contract-content h3 { font-size:10px!important; margin:3px 0 2px!important; color:#000!important; }
    .contract-content p { margin:0 0 3px!important; font-size:10px!important; }
    .contract-content li { font-size:10px!important; margin-bottom:1px!important; }
    .contract-content ul,.contract-content ol { margin:0 0 4px!important; padding-left:14px!important; }
    .contract-content table { font-size:9px!important; }
    .contract-content th,.contract-content td { padding:2px 4px!important; border-color:#bbb!important; }
    #contract-document { break-inside:auto; }
}
</style>
@endpush
@endsection