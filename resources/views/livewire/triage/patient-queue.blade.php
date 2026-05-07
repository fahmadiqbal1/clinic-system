<div wire:poll.10s="refreshQueue">
    {{-- Header row --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-people me-1" style="color:var(--accent-warning);"></i>
            Live Patient Queue
            <span class="badge bg-secondary ms-1">{{ count($patients) }}</span>
        </h6>
        <button
            class="btn btn-outline-secondary btn-sm"
            wire:click="refreshQueue"
            wire:loading.attr="disabled"
            title="Refresh"
        >
            <span wire:loading wire:target="refreshQueue" class="spinner-border spinner-border-sm" role="status"></span>
            <i class="bi bi-arrow-clockwise" wire:loading.remove wire:target="refreshQueue"></i>
        </button>
    </div>

    @if (empty($patients))
        <div class="text-center py-5 text-muted">
            <i class="bi bi-check-circle display-6 d-block mb-2" style="color:var(--accent-success);"></i>
            No patients waiting in triage.
        </div>
    @else
        <div class="row g-3">
            @foreach ($patients as $patient)
                @php
                    $status    = $patient['status'];
                    $isInTriage = $status === 'triage';
                    $badgeClass = $isInTriage ? 'bg-primary' : 'bg-warning text-dark';
                    $badgeLabel = $isInTriage ? 'In Triage' : 'Waiting';
                    $iconClass  = $isInTriage ? 'bi-clipboard2-pulse' : 'bi-hourglass-split';

                    // Time waiting = from registered_at (for waiting) or triage_started_at (for in-triage)
                    $since = $isInTriage
                        ? ($patient['triage_started_at'] ? \Carbon\Carbon::parse($patient['triage_started_at']) : null)
                        : ($patient['registered_at']    ? \Carbon\Carbon::parse($patient['registered_at'])     : null);

                    $waitingText = $since ? $since->diffForHumans(['parts' => 2, 'join' => true]) : '—';
                @endphp
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card border-0 hover-lift fade-in h-100"
                         style="border-left: 3px solid {{ $isInTriage ? 'var(--accent-primary)' : 'var(--accent-warning)' }} !important;">
                        <div class="card-body glass-stat py-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="fw-semibold">
                                    <i class="bi {{ $iconClass }} me-1"></i>
                                    {{ $patient['name'] }}
                                </div>
                                <span class="badge {{ $badgeClass }} ms-2">{{ $badgeLabel }}</span>
                            </div>
                            <div class="small text-muted">
                                <i class="bi bi-clock me-1"></i>Waiting {{ $waitingText }}
                            </div>
                            @if ($isInTriage)
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-clipboard2-pulse me-1" style="color:var(--accent-primary);"></i>
                                    Triage in progress
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Fallback polling notice when Reverb is not configured --}}
    @if (!\App\Models\PlatformSetting::isEnabled('ui.reverb.enabled'))
        <div class="mt-3 text-muted small d-flex align-items-center gap-1">
            <span class="auto-poll-indicator" role="status" aria-label="Auto-refreshing">
                <span class="auto-poll-dot"></span>
            </span>
            Polling every 10 s — enable Reverb for real-time updates.
        </div>
    @endif
</div>
