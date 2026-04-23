@extends('layouts.app')

@section('title', 'Contract Details — ' . config('app.name'))

@section('content')
<div class="fade-in delay-1">
    <div class="page-header">
        <div>
            <h1 class="h3 fw-bold text-white mb-1">
                <i class="bi bi-file-earmark-medical me-2"></i>
                @if (isset($contract) && $contract)
                    Active Contract
                @else
                    Staff Contracts
                @endif
            </h1>
            <p class="page-subtitle mb-0">
                @if (isset($contract) && $contract)
                    Version {{ $contract->version }} &mdash; {{ $contract->user?->name ?? 'Unknown Staff' }}
                @else
                    Contract details for {{ $staff?->name ?? 'this staff member' }}
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            @if(isset($contract) && $contract)
                <a href="{{ route('contracts.view', $contract) }}" class="btn btn-outline-light fw-semibold">
                    <i class="bi bi-eye me-1"></i> Full Document
                </a>
            @endif
            <a href="{{ route('contracts.index') }}" class="btn btn-outline-light fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> All Contracts
            </a>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="fade-in delay-2">
        <div class="alert-banner-success d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill"></i>
            {{ session('success') }}
        </div>
    </div>
@endif

@if (session('error'))
    <div class="fade-in delay-2">
        <div class="alert-banner-danger d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill"></i>
            {{ session('error') }}
        </div>
    </div>
@endif

@if (!isset($contract) || !$contract)
    <div class="fade-in delay-2">
        <div class="alert-banner-warning d-flex align-items-start gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>No Active Contract</strong><br>
                There is no active contract for {{ $staff?->name ?? 'this staff member' }} at this time.
                @if (auth()->user()->hasRole('Owner'))
                    <a href="{{ route('contracts.create') }}" class="text-white fw-semibold text-decoration-underline ms-1">Create a new contract</a>.
                @endif
            </div>
        </div>
    </div>
@else
    {{-- Status Pipeline --}}
    <div class="fade-in delay-2 mb-4">
        <div class="glass-card">
            <div class="status-pipeline">
                {{-- Step 1: Created --}}
                <div class="pipeline-step active">
                    <div class="pipeline-step-dot"></div>
                    <div>
                        <strong class="text-white">Created</strong>
                        <small class="d-block text-white-50">{{ $contract->created_at?->format('M d, Y') ?? 'N/A' }}</small>
                    </div>
                </div>
                <div class="pipeline-connector active"></div>

                {{-- Step 2: Signed --}}
                <div class="pipeline-step {{ $contract->isSigned() ? 'active' : '' }}">
                    <div class="pipeline-step-dot"></div>
                    <div>
                        <strong class="text-white">Signed</strong>
                        @if ($contract->isSigned())
                            <small class="d-block text-white-50">{{ $contract->signed_at?->format('M d, Y') ?? 'Pending' }}</small>
                        @else
                            <small class="d-block text-white-50">Awaiting signature</small>
                        @endif
                    </div>
                </div>
                <div class="pipeline-connector {{ $contract->status === 'active' ? 'active' : '' }}"></div>

                {{-- Step 3: Active --}}
                <div class="pipeline-step {{ $contract->status === 'active' ? 'active' : '' }}">
                    <div class="pipeline-step-dot"></div>
                    <div>
                        <strong class="text-white">Active</strong>
                        <small class="d-block text-white-50">
                            @if ($contract->status === 'active')
                                In effect
                            @else
                                {{ ucfirst($contract->status) }}
                            @endif
                        </small>
                    </div>
                </div>

                @if ($contract->resignation_notice_submitted_at || $contract->early_exit_flag)
                    <div class="pipeline-connector active"></div>
                    <div class="pipeline-step active">
                        <div class="pipeline-step-dot"></div>
                        <div>
                            <strong class="text-white">
                                @if ($contract->early_exit_flag)
                                    Early Exit
                                @else
                                    Resignation
                                @endif
                            </strong>
                            <small class="d-block text-white-50">
                                @if ($contract->resignation_notice_submitted_at)
                                    {{ $contract->resignation_notice_submitted_at->format('M d, Y') }}
                                @else
                                    Flagged
                                @endif
                            </small>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Contract Content --}}
        <div class="col-lg-8">
            <div class="fade-in delay-3">
                <div class="glass-card">
                    {{-- Staff & Version Header --}}
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon stat-icon-primary" style="width:48px;height:48px;font-size:1.1rem;">
                                {{ strtoupper(substr($contract->user?->name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <h2 class="h4 fw-bold text-white mb-0">{{ $contract->user?->name ?? 'Unknown Staff' }}</h2>
                                <small class="text-white-50">{{ $contract->user?->roles?->pluck('name')->join(', ') ?? '' }}</small>
                            </div>
                        </div>
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

                    <div class="glass-divider mb-3"></div>

                    {{-- Contract Metadata Grid --}}
                    <div class="info-grid mb-4">
                        <div class="info-grid-item">
                            <span class="info-label"><i class="bi bi-hash me-1"></i> Version</span>
                            <span class="info-value">v{{ $contract->version }}</span>
                        </div>
                        <div class="info-grid-item">
                            <span class="info-label"><i class="bi bi-calendar-check me-1"></i> Effective From</span>
                            <span class="info-value">{{ $contract->effective_from->format('M d, Y') }}</span>
                        </div>
                        <div class="info-grid-item">
                            <span class="info-label"><i class="bi bi-clock-history me-1"></i> Minimum Term</span>
                            <span class="info-value">
                                {{ $contract->minimum_term_months }} months
                                @if($contract->isActive() && $contract->isWithinMinimumTerm())
                                    <i class="bi bi-hourglass-split text-warning ms-1" title="Within minimum term"></i>
                                @endif
                            </span>
                        </div>
                        @if($contract->minimum_term_end)
                            <div class="info-grid-item">
                                <span class="info-label"><i class="bi bi-calendar-x me-1"></i> Term Ends</span>
                                <span class="info-value">{{ $contract->minimum_term_end->format('M d, Y') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="glass-divider mb-4"></div>

                    {{-- Contract HTML Content --}}
                    <div class="contract-content text-white">
                        {!! strip_tags($contract->contract_html_snapshot, '<p><br><h1><h2><h3><h4><h5><h6><strong><em><b><i><u><ul><ol><li><table><thead><tbody><tr><th><td><div><span><hr><blockquote><pre><code><a><img><sub><sup><small>') !!}
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Quick Actions Card --}}
            <div class="fade-in delay-3">
                <div class="glass-card mb-4">
                    <h3 class="h6 fw-bold text-white mb-3"><i class="bi bi-lightning me-1"></i> Quick Actions</h3>
                    <div class="d-grid gap-2">
                        <a href="{{ route('contracts.pdf', $contract) }}" class="btn btn-primary fw-semibold">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                        </a>
                        <a href="{{ route('contracts.view', $contract) }}" class="btn btn-outline-light fw-semibold">
                            <i class="bi bi-eye me-1"></i> Full Document View
                        </a>
                    </div>
                </div>
            </div>

            {{-- Sign Contract (Staff + Draft) --}}
            @if ($contract->status === 'draft' && !auth()->user()->hasRole('Owner') && $contract->user_id === auth()->id())
                <div class="fade-in delay-3">
                    <div class="glass-card accent-left-primary mb-4">
                        <h3 class="h6 fw-bold text-white mb-2">
                            <i class="bi bi-pen me-2"></i> Sign Contract
                        </h3>
                        <p class="small text-white-50 mb-3">
                            By signing, you agree to the terms of this contract effective {{ $contract->effective_from->format('M d, Y') }}.
                        </p>
                        <form action="{{ route('contracts.sign', $contract) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                <i class="bi bi-check-circle me-1"></i> Sign Contract
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Submit Resignation (Staff + Active) --}}
            @if ($contract->status === 'active' && !auth()->user()->hasRole('Owner') && $contract->user_id === auth()->id())
                <div class="fade-in delay-4">
                    <div class="glass-card accent-left-danger mb-4">
                        <h3 class="h6 fw-bold text-white mb-2">
                            <i class="bi bi-box-arrow-right me-2"></i> Submit Resignation
                        </h3>
                        <p class="small text-white-50 mb-3">
                            Submit notice to terminate this contract. The minimum term is {{ $contract->minimum_term_months }} months.
                        </p>
                        <form action="{{ route('contracts.resign', $contract) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-danger w-100 fw-semibold"
                                onclick="return confirm('Are you sure you want to submit a resignation notice?')">
                                <i class="bi bi-exclamation-triangle me-1"></i> Submit Resignation
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Contract Information --}}
            <div class="fade-in delay-5">
                <div class="glass-card">
                    <h3 class="h6 fw-bold text-white mb-3">
                        <i class="bi bi-info-circle me-2"></i> Contract Details
                    </h3>

                    @if ($contract->isSigned())
                        <div class="data-row">
                            <span class="data-label"><i class="bi bi-pen me-1"></i> Signed</span>
                            <span class="data-value">{{ $contract->signed_at->format('M d, Y H:i A') }}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label"><i class="bi bi-geo-alt me-1"></i> Signed IP</span>
                            <span class="data-value font-monospace small">{{ $contract->signed_ip }}</span>
                        </div>
                    @endif

                    @if ($contract->isWithinMinimumTerm())
                        <div class="glass-divider my-2"></div>
                        <div class="data-row">
                            <span class="data-label"><i class="bi bi-calendar-x me-1"></i> Min. Term Ends</span>
                            <span class="data-value">{{ $contract->minimum_term_end->format('M d, Y') }}</span>
                        </div>
                    @endif

                    @if ($contract->resignation_notice_submitted_at)
                        <div class="glass-divider my-2"></div>
                        <div class="data-row">
                            <span class="data-label"><i class="bi bi-box-arrow-right me-1"></i> Resignation Notice</span>
                            <span class="data-value">{{ $contract->resignation_notice_submitted_at->format('M d, Y H:i A') }}</span>
                        </div>
                    @endif

                    @if ($contract->early_exit_flag)
                        <div class="glass-divider my-2"></div>
                        <div class="alert-banner-danger d-flex align-items-center gap-2 mt-2 mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Early Exit Flag</strong>
                        </div>
                    @endif

                    <div class="glass-divider my-2"></div>
                    <div class="data-row">
                        <span class="data-label"><i class="bi bi-person me-1"></i> Created By</span>
                        <span class="data-value">{{ $contract->creator?->name ?? 'Unknown' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection