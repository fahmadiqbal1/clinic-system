@extends('layouts.app')

@section('title', ($staff ? $staff->name . "'s Contracts" : 'Staff Contracts') . ' — ' . config('app.name'))

@section('content')
<div class="fade-in delay-1">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="h3 fw-bold text-white mb-1">
                <i class="bi bi-file-earmark-text me-2"></i>
                @if($staff)
                    {{ $staff->name }}'s Contract History
                @else
                    All Staff Contracts
                @endif
            </h1>
            <p class="page-subtitle mb-0">Manage and review employment contracts</p>
        </div>
        @if (auth()->user()->hasRole('Owner'))
            <a href="{{ route('contracts.create') }}" class="btn btn-light fw-semibold">
                <i class="bi bi-plus-circle me-1"></i> New Contract
            </a>
        @endif
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

@if ($contracts->isEmpty())
    <div class="fade-in delay-2">
        <div class="empty-state text-center py-5">
            <div class="stat-icon stat-icon-info mx-auto mb-3" style="width:4rem;height:4rem;">
                <i class="bi bi-folder2-open" style="font-size:1.75rem;"></i>
            </div>
            <h4 class="text-white fw-bold mb-2">No Contracts Found</h4>
            <p class="text-white-50 mb-0">
                @if($staff)
                    No contracts have been created for {{ $staff->name }} yet.
                @else
                    No Staff Contracts exist in the system.
                @endif
            </p>
            @if (auth()->user()->hasRole('Owner'))
                <a href="{{ route('contracts.create') }}" class="btn btn-primary mt-3">
                    <i class="bi bi-plus-circle me-1"></i> Create First Contract
                </a>
            @endif
        </div>
    </div>
@else
    @foreach ($contracts as $index => $contract)
        <div class="fade-in delay-{{ min($index + 2, 6) }}">
            <div class="glass-card mb-3 hover-lift">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h3 class="h5 fw-bold text-white mb-1">
                            @if(!$staff)
                                <i class="bi bi-person-badge me-1"></i>
                                {{ $contract->user?->name ?? 'Unknown' }} &mdash;
                            @endif
                            <i class="bi bi-hash"></i>Version {{ $contract->version }}
                        </h3>
                        <p class="small text-white-50 mb-0">
                            <i class="bi bi-calendar-event me-1"></i>
                            Effective {{ $contract->effective_from?->format('M d, Y') ?? 'N/A' }}
                        </p>
                    </div>
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
                </div>

                <div class="info-grid mb-3">
                    <div class="info-grid-item">
                        <span class="info-label"><i class="bi bi-clock-history me-1"></i> Minimum Term</span>
                        <span class="info-value">{{ $contract->minimum_term_months }} months</span>
                    </div>
                    @if ($contract->isSigned())
                        <div class="info-grid-item">
                            <span class="info-label"><i class="bi bi-pen me-1"></i> Signed</span>
                            <span class="info-value">{{ $contract->signed_at?->format('M d, Y') ?? 'N/A' }}</span>
                        </div>
                    @endif
                    @if ($contract->resignation_notice_submitted_at)
                        <div class="info-grid-item">
                            <span class="info-label"><i class="bi bi-box-arrow-right me-1"></i> Resignation Notice</span>
                            <span class="info-value">{{ $contract->resignation_notice_submitted_at?->format('M d, Y') ?? 'N/A' }}</span>
                        </div>
                    @endif
                    @if ($contract->early_exit_flag)
                        <div class="info-grid-item">
                            <span class="info-label"><i class="bi bi-exclamation-triangle me-1"></i> Status</span>
                            <span class="info-value glow-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Early Exit</span>
                        </div>
                    @endif
                </div>

                <div class="glass-divider mb-3"></div>

                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-white-50">
                        <i class="bi bi-person me-1"></i>
                        Created by {{ $contract->creator?->name ?? 'Unknown' }} on {{ $contract->created_at?->format('M d, Y') ?? 'N/A' }}
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <a href="{{ route('contracts.view', $contract) }}" class="btn btn-sm btn-outline-light fw-semibold">
                            <i class="bi bi-eye me-1"></i> View
                        </a>
                        @if ($contract->status === 'active' && auth()->user()->hasRole('Owner'))
                            <form action="{{ route('contracts.mark-early-exit', $contract) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger fw-semibold" onclick="return confirm('Mark this contract for early exit?')">
                                    <i class="bi bi-flag me-1"></i> Mark Early Exit
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="fade-in delay-4 mt-4">
        {{ $contracts->links() }}
    </div>
@endif
@endsection