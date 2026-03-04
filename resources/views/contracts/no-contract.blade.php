@extends('layouts.app')

@section('title', 'No Active Contract — ' . config('app.name'))

@section('content')
<div class="fade-in delay-1">
    <div class="page-header">
        <div>
            <h1 class="h3 fw-bold text-white mb-1">
                <i class="bi bi-file-earmark-text me-2"></i> Staff Contract
            </h1>
            <p class="page-subtitle mb-0">Employment contract status</p>
        </div>
        <a href="{{ route('contracts.index') }}" class="btn btn-outline-light fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Contract History
        </a>
    </div>
</div>

<div class="fade-in delay-2">
    <div class="empty-state text-center py-5">
        <div class="stat-icon stat-icon-warning mx-auto mb-4" style="width:5rem;height:5rem;">
            <i class="bi bi-file-earmark-x" style="font-size:2.25rem;"></i>
        </div>

        <h2 class="h4 fw-bold text-white mb-2">No Active Contract</h2>

        <p class="text-white-50 mb-4" style="max-width:28rem;margin:0 auto;">
            There is currently no active employment contract for
            <strong class="text-white">{{ $staff?->name ?? 'this staff member' }}</strong>.
        </p>

        @if (auth()->user()->hasRole('Owner'))
            <p class="text-white-50 mb-4">As an owner, you can create a new contract for this staff member.</p>
            <a href="{{ route('contracts.create', ['user_id' => $staff->id]) }}" class="btn btn-primary fw-semibold px-4">
                <i class="bi bi-plus-circle me-1"></i> Create New Contract
            </a>
        @elseif (!auth()->user()->hasRole('Owner') && auth()->id() === $staff->id)
            <div class="glass-card accent-left-info d-inline-block text-start mt-2" style="max-width:26rem;">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-hourglass-split text-info mt-1"></i>
                    <span class="text-white-50">
                        Once the Owner creates a contract, you will receive it here for signature.
                    </span>
                </div>
            </div>
        @endif
    </div>
</div>

@if (auth()->user()->hasRole('Owner') || (!auth()->user()->hasRole('Owner') && auth()->id() === $staff->id))
    <div class="fade-in delay-3 mt-4">
        <div class="glass-card">
            <h3 class="h5 fw-bold text-white mb-3">
                <i class="bi bi-clock-history me-2"></i> Contract History
            </h3>
            <a href="{{ route('contracts.index') }}" class="btn btn-outline-light btn-sm fw-semibold">
                <i class="bi bi-list-ul me-1"></i> View all contract versions
            </a>
        </div>
    </div>
@endif
@endsection