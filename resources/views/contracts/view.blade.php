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
            <p class="page-subtitle mb-0">Full document view</p>
        </div>
        <div class="d-flex gap-2">
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
        <div class="info-grid">
            <div class="info-grid-item">
                <span class="info-label"><i class="bi bi-person-badge me-1"></i> Staff Member</span>
                <span class="info-value">{{ $contract->user?->name ?? 'Unknown Staff' }}</span>
            </div>
            <div class="info-grid-item">
                <span class="info-label"><i class="bi bi-activity me-1"></i> Status</span>
                <span class="info-value">
                    <span class="badge-glass
                        @if ($contract->status === 'active') glow-success
                        @elseif ($contract->status === 'draft') glow-warning
                        @else glow-info @endif
                    ">
                        <i class="bi
                            @if ($contract->status === 'active') bi-check-circle-fill
                            @elseif ($contract->status === 'draft') bi-pencil-square
                            @else bi-archive @endif
                            me-1"></i>
                        {{ ucfirst($contract->status) }}
                    </span>
                </span>
            </div>
            <div class="info-grid-item">
                <span class="info-label"><i class="bi bi-calendar-check me-1"></i> Effective From</span>
                <span class="info-value">{{ $contract->effective_from?->format('M d, Y') ?? 'N/A' }}</span>
            </div>
            <div class="info-grid-item">
                <span class="info-label"><i class="bi bi-clock-history me-1"></i> Minimum Term</span>
                <span class="info-value">{{ $contract->minimum_term_months }} months</span>
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
                Document Version {{ $contract->version }}
                <span class="mx-2">&middot;</span>
                Created {{ $contract->created_at?->format('M d, Y H:i A') ?? 'N/A' }}
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
                            onclick="return confirm('Are you sure you want to submit a resignation notice? This action cannot be undone.')">
                            <i class="bi bi-box-arrow-right me-1"></i> Submit Resignation
                        </button>
                    </form>
                @endif

                @if ($contract->resignation_notice_submitted_at)
                    <span class="badge-glass glow-warning align-self-center">
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

                <button onclick="window.print()" class="btn btn-primary fw-semibold">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    @media print {
        .page-header, .sidebar, nav, .fade-in.delay-2 .glass-card .info-grid,
        .fade-in.delay-4 .glass-card .d-flex button,
        .fade-in.delay-4 .glass-card .d-flex form { display: none !important; }
        .glass-card { background: white !important; color: black !important; box-shadow: none !important; border: 1px solid #ddd !important; }
        .contract-content { color: black !important; }
        .text-white, .text-white-50 { color: black !important; }
    }
</style>
@endpush
@endsection