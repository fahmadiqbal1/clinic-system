@extends('layouts.app')
@section('title', 'AI & Infrastructure — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-cpu me-2" style="color:var(--accent-primary);"></i>AI & Infrastructure</h2>
            <p class="page-subtitle mb-0">Sidecar status, RAGFlow health, and pending AI action requests</p>
        </div>
        <a href="{{ route('owner.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    {{-- Feature-flag banner --}}
    @if(!$sidecarEnabled && !$ragflowEnabled)
    <div class="alert alert-warning fade-in mb-4">
        <i class="bi bi-toggle-off me-2"></i>
        Both <code>ai.sidecar.enabled</code> and <code>ai.ragflow.enabled</code> are <strong>OFF</strong>.
        Enable them in <a href="{{ route('owner.platform-settings.index') }}">Platform Settings</a>.
    </div>
    @endif

    {{-- Status cards --}}
    <div class="row g-3 mb-4">
        {{-- Sidecar status --}}
        <div class="col-md-4 fade-in delay-1">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    @php
                        $sStatus = $sidecarStatus['status'] ?? 'unknown';
                        $sColor  = match($sStatus) {
                            'ok'           => 'var(--accent-success)',
                            'circuit_open' => 'var(--accent-warning)',
                            default        => 'var(--accent-danger)',
                        };
                        $sIcon   = match($sStatus) {
                            'ok'           => 'bi-check-circle-fill',
                            'circuit_open' => 'bi-shield-exclamation',
                            default        => 'bi-x-circle-fill',
                        };
                    @endphp
                    <div class="stat-icon" style="background:rgba(129,140,248,0.15);">
                        <i class="bi bi-hdd-network" style="color:var(--accent-primary);"></i>
                    </div>
                    <div>
                        <div class="text-muted small">AI Sidecar</div>
                        <div class="fw-semibold" style="color:{{ $sColor }};">
                            <i class="bi {{ $sIcon }} me-1"></i>
                            {{ match($sStatus) {
                                'ok'           => 'Online',
                                'circuit_open' => 'Circuit Open',
                                'unreachable'  => 'Unreachable',
                                default        => ucfirst($sStatus),
                            } }}
                        </div>
                        @if($sidecarEnabled)
                            <div class="text-muted" style="font-size:0.72rem;">Flag ON</div>
                        @else
                            <div class="text-muted" style="font-size:0.72rem;">Flag OFF</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- RAGFlow status --}}
        <div class="col-md-4 fade-in delay-2">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(52,211,153,0.15);">
                        <i class="bi bi-journals" style="color:var(--accent-success);"></i>
                    </div>
                    <div>
                        <div class="text-muted small">RAGFlow</div>
                        <div class="fw-semibold" style="color:{{ $ragflowEnabled ? 'var(--accent-success)' : 'var(--text-muted)' }};">
                            @if($ragflowEnabled)
                                <i class="bi bi-toggle-on me-1"></i>Enabled
                            @else
                                <i class="bi bi-toggle-off me-1"></i>Disabled
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:0.72rem;">
                            Run <code>php artisan ragflow:sync</code> to populate corpus
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending AI action requests --}}
        <div class="col-md-4 fade-in delay-3">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(251,191,36,0.15);">
                        <i class="bi bi-hourglass-split" style="color:var(--accent-warning);"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending AI Requests</div>
                        <div class="stat-value {{ $pendingAiRequests > 0 ? 'glow-warning' : '' }}">
                            {{ $pendingAiRequests }}
                        </div>
                        <div class="text-muted" style="font-size:0.72rem;">Awaiting Owner approval</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Top cited documents (7d) --}}
    @if($topCitedDocs->isNotEmpty())
    <div class="glass-card p-4 mb-4 fade-in delay-3">
        <h5 class="mb-3">
            <i class="bi bi-bookmark-star me-2" style="color:var(--accent-primary);"></i>
            Top Cited Documents <span class="text-muted small fw-normal">(last 7 days)</span>
        </h5>
        <div class="d-flex flex-wrap gap-2">
            @foreach($topCitedDocs as $doc => $count)
                <span class="badge-glass px-3 py-1" style="font-size:0.8rem;">
                    <i class="bi bi-file-earmark-text me-1" style="color:var(--accent-info);"></i>
                    {{ $doc }}
                    <span class="badge bg-secondary ms-1">{{ $count }}×</span>
                </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Recent AI invocations --}}
    <div class="glass-card p-4 mb-4 fade-in delay-4">
        <h5 class="mb-3">
            <i class="bi bi-clock-history me-2" style="color:var(--accent-secondary);"></i>
            Recent AI Invocations
        </h5>
        @if($recentInvocations->isEmpty())
            <p class="text-muted mb-0">No AI invocations recorded yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Model</th>
                            <th>Outcome</th>
                            <th>Latency</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentInvocations as $inv)
                        <tr>
                            <td><code style="font-size:0.78rem;">{{ $inv->endpoint }}</code></td>
                            <td style="color:var(--text-muted); font-size:0.82rem;">{{ $inv->model_id ?? '—' }}</td>
                            <td>
                                @if($inv->outcome === 'ok')
                                    <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);font-size:0.75rem;">ok</span>
                                @else
                                    <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);font-size:0.75rem;">{{ $inv->outcome }}</span>
                                @endif
                            </td>
                            <td style="color:var(--text-muted); font-size:0.82rem;">{{ $inv->latency_ms }}ms</td>
                            <td style="color:var(--text-muted); font-size:0.78rem;">{{ $inv->created_at->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Owner AI Knowledge Assistant --}}
    @if($ragflowEnabled)
    <x-ai-assistant-panel
        collection="general"
        panelTitle="Owner Knowledge Assistant"
        placeholder="Ask about clinical protocols, services, or infrastructure…" />
    @endif

</div>
@endsection
