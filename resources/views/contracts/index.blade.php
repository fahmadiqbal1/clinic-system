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
                    Staff Contracts
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

{{-- Stats Summary --}}
@if(!$staff && $contracts->count())
@php
    $allContracts = \App\Models\StaffContract::selectRaw("
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN resignation_notice_submitted_at IS NOT NULL THEN 1 ELSE 0 END) as resignation_count,
        SUM(CASE WHEN early_exit_flag = 1 THEN 1 ELSE 0 END) as early_exit_count
    ")->first();
@endphp
<div class="fade-in delay-2 mb-4">
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="glass-card text-center py-3">
                <div class="glass-stat-value" style="color:var(--accent-primary);">{{ $allContracts->total }}</div>
                <div class="glass-stat-label">Total Contracts</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-card text-center py-3">
                <div class="glass-stat-value" style="color:var(--accent-success);">{{ $allContracts->active_count }}</div>
                <div class="glass-stat-label">Active</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-card text-center py-3">
                <div class="glass-stat-value" style="color:var(--accent-warning);">{{ $allContracts->draft_count }}</div>
                <div class="glass-stat-label">Pending Signature</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-card text-center py-3">
                <div class="glass-stat-value" style="color:var(--accent-danger);">{{ $allContracts->resignation_count + $allContracts->early_exit_count }}</div>
                <div class="glass-stat-label">Exits / Resignations</div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Filter bar --}}
@if(!$staff)
<div class="fade-in delay-2 mb-4">
    <div class="glass-card py-3">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="small text-white-50 me-1"><i class="bi bi-funnel me-1"></i>Filter:</span>
            <a href="{{ route('contracts.index') }}"
               class="btn btn-sm {{ !request('status') ? 'btn-primary' : 'btn-outline-light' }}">All</a>
            <a href="{{ route('contracts.index', ['status' => 'active']) }}"
               class="btn btn-sm {{ request('status') === 'active' ? 'btn-success' : 'btn-outline-light' }}">
               <i class="bi bi-check-circle me-1"></i>Active</a>
            <a href="{{ route('contracts.index', ['status' => 'draft']) }}"
               class="btn btn-sm {{ request('status') === 'draft' ? 'btn-warning' : 'btn-outline-light' }}">
               <i class="bi bi-pencil-square me-1"></i>Pending Signature</a>
            <a href="{{ route('contracts.index', ['status' => 'superseded']) }}"
               class="btn btn-sm {{ request('status') === 'superseded' ? 'btn-info' : 'btn-outline-light' }}">
               <i class="bi bi-archive me-1"></i>Superseded</a>
        </div>
    </div>
</div>
@endif

@if ($contracts->isEmpty())
    <div class="fade-in delay-3">
        <div class="empty-state text-center py-5">
            <div class="stat-icon stat-icon-info mx-auto mb-3" style="width:4rem;height:4rem;">
                <i class="bi bi-folder2-open" style="font-size:1.75rem;"></i>
            </div>
            <h4 class="text-white fw-bold mb-2">No Contracts Found</h4>
            <p class="text-white-50 mb-0">
                @if($staff)
                    No contracts have been created for {{ $staff->name }} yet.
                @else
                    No staff contracts match the current filter.
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
    {{-- Contracts Table --}}
    <div class="fade-in delay-3">
        <div class="glass-card p-0" style="overflow:hidden;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Staff Member</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Effective</th>
                            <th>Term</th>
                            <th>Signed</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contracts as $contract)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="stat-icon stat-icon-primary" style="width:36px;height:36px;font-size:0.85rem;">
                                        {{ strtoupper(substr($contract->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-white">{{ $contract->user?->name ?? 'Unknown' }}</div>
                                        <small class="text-white-50">{{ $contract->user?->roles?->pluck('name')->join(', ') ?? '' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="code-tag">v{{ $contract->version }}</span></td>
                            <td>
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
                            </td>
                            <td class="small">{{ $contract->effective_from?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <span class="small">{{ $contract->minimum_term_months }}mo</span>
                                @if($contract->isActive() && $contract->isWithinMinimumTerm())
                                    <i class="bi bi-hourglass-split text-warning ms-1" title="Within minimum term"></i>
                                @endif
                            </td>
                            <td class="small">
                                @if($contract->isSigned())
                                    <span class="text-success"><i class="bi bi-check2 me-1"></i>{{ $contract->signed_at->format('M d, Y') }}</span>
                                @else
                                    <span class="text-white-50">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('contracts.view', $contract) }}" class="btn btn-sm btn-outline-light" title="View Document">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($contract->status === 'active' && auth()->user()->hasRole('Owner') && !$contract->early_exit_flag)
                                        <form action="{{ route('contracts.mark-early-exit', $contract) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Mark Early Exit"
                                                onclick="return confirm('Mark this contract for early exit?')">
                                                <i class="bi bi-flag"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    @if($contracts->hasPages())
    <div class="fade-in delay-4 mt-3 d-flex justify-content-center">
        {{ $contracts->withQueryString()->links() }}
    </div>
    @endif
@endif
@endsection