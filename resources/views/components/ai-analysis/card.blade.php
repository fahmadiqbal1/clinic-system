{{-- MedGemma AI Analysis Card --}}
{{-- Usage: @include('components.ai-analysis.card', ['analyses' => $analyses, 'formAction' => route(...), 'contextLabel' => 'Consultation']) --}}

<div class="card mb-4 fade-in delay-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-robot me-2" style="color:var(--accent-secondary);"></i>MedGemma AI Second Opinion</span>
        @if(isset($formAction))
        <form action="{{ $formAction }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-info btn-sm" onclick="return confirm('Request MedGemma AI analysis for this {{ $contextLabel ?? 'record' }}?')">
                <i class="bi bi-stars me-1"></i>Get AI Analysis
            </button>
        </form>
        @endif
    </div>
    <div class="card-body">
        @if(isset($analyses) && $analyses->count() > 0)
            @foreach($analyses as $analysis)
            <div class="mb-3 p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small style="color:var(--text-muted);">
                        <i class="bi bi-clock me-1"></i>{{ $analysis->created_at->format('M d, Y H:i') }}
                        &mdash; requested by {{ $analysis->requester?->name ?? 'System' }}
                    </small>
                    @php
                        $statusStyle = match($analysis->status) {
                            'completed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            'failed' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                            default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $statusStyle }}">{{ ucfirst($analysis->status) }}</span>
                </div>
                <div style="color:var(--text-secondary); white-space:pre-line; font-size:0.9rem;">
                    {!! nl2br(e($analysis->ai_response ?? 'Awaiting response...')) !!}
                </div>
            </div>
            @endforeach
        @else
            <p class="mb-0" style="color:var(--text-muted);">
                <i class="bi bi-info-circle me-1"></i>No AI analysis has been performed yet. Click "Get AI Analysis" to request a second opinion from MedGemma.
            </p>
        @endif
    </div>
</div>
