@extends('layouts.app')
@section('title', 'AI Analyses — ' . $patient->full_name . ' — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-robot me-2" style="color:var(--accent-secondary);"></i>MedGemma AI Analyses</h2>
            <p class="page-subtitle mb-0">{{ $patient->full_name }} — Patient #{{ $patient->id }}</p>
        </div>
        <a href="{{ route('doctor.consultation.show', $patient) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Consultation</a>
    </div>

    @if($analyses->count() > 0)
        @foreach($analyses as $analysis)
        <div class="card mb-4 fade-in delay-1">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-stars me-1" style="color:var(--accent-secondary);"></i>
                    {{ ucfirst($analysis->context_type) }} Analysis
                </span>
                <small style="color:var(--text-muted);">{{ $analysis->created_at->format('M d, Y H:i') }}</small>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small style="color:var(--text-muted);">Requested by: {{ $analysis->requester?->name ?? 'System' }}</small>
                    @php
                        $statusStyle = match($analysis->status) {
                            'completed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            'failed' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                            default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $statusStyle }}">{{ ucfirst($analysis->status) }}</span>
                </div>
                <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border); white-space:pre-line; font-size:0.9rem; color:var(--text-secondary);">
                    {!! nl2br(e($analysis->ai_response ?? 'Awaiting response...')) !!}
                </div>
            </div>
        </div>
        @endforeach
    @else
        <div class="card fade-in delay-1">
            <div class="card-body text-center py-5">
                <i class="bi bi-robot" style="font-size:3rem; color:var(--text-muted);"></i>
                <p class="mt-3" style="color:var(--text-muted);">No AI analyses have been performed for this patient yet.</p>
            </div>
        </div>
    @endif
</div>
@endsection
