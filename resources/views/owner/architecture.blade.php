@extends('layouts.app')
@section('title', 'Architecture Graph')

@push('styles')
<style>
#cy {
    width: 100%;
    height: 560px;
    background: rgba(15, 23, 42, 0.6);
    border-radius: var(--card-radius, 12px);
}
.legend-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 4px;
}
</style>
@endpush

@section('content')
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="page-header"><i class="bi bi-diagram-3 me-2"></i>Architecture Graph</h1>
            <p class="page-subtitle">Codebase dependency graph powered by GitNexus</p>
        </div>
        @if($enabled)
        <div class="d-flex gap-2">
            <a href="{{ route('owner.architecture') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </a>
        </div>
        @endif
    </div>

    @if(! $enabled)
    {{-- Feature flag off --}}
    <div class="glass-card p-5 text-center fade-in">
        <i class="bi bi-diagram-3" style="font-size:2.5rem; color:var(--text-tertiary);"></i>
        <h3 class="h6 fw-medium mt-3">Architecture View is disabled</h3>
        <p class="small text-muted mb-3">Enable the <code>ai.gitnexus.enabled</code> feature flag in Platform Settings to activate this page.</p>
        <a href="{{ route('owner.platform-settings.index') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-toggles me-1"></i>Platform Settings
        </a>
    </div>
    @elseif(! $meta)
    {{-- Not yet scanned --}}
    <div class="glass-card p-5 text-center fade-in">
        <i class="bi bi-cpu" style="font-size:2.5rem; color:var(--text-tertiary);"></i>
        <h3 class="h6 fw-medium mt-3">No graph data found</h3>
        <p class="small text-muted mb-3">Run the scan command to generate the graph:</p>
        <code class="d-block p-2 mb-0" style="background:rgba(0,0,0,0.3); border-radius:6px; font-size:0.82rem;">
            php artisan gitnexus:scan
        </code>
    </div>
    @else
    {{-- Stats bar --}}
    <div class="row g-3 mb-4">
        @php
            $stats = [
                ['label' => 'Total Nodes',  'value' => number_format($meta['stats']['nodes']      ?? 0), 'icon' => 'bi-node-plus',         'color' => 'primary'],
                ['label' => 'Edges',        'value' => number_format($meta['stats']['edges']      ?? 0), 'icon' => 'bi-share',             'color' => 'success'],
                ['label' => 'Communities',  'value' => number_format($meta['stats']['communities']?? 0), 'icon' => 'bi-collection',        'color' => 'warning'],
                ['label' => 'Flows',        'value' => number_format($meta['stats']['processes']  ?? 0), 'icon' => 'bi-arrow-left-right',  'color' => 'danger'],
            ];
        @endphp
        @foreach($stats as $s)
        <div class="col-6 col-md-3 fade-in">
            <div class="glass-card p-3 hover-lift">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-{{ $s['color'] }}">
                        <i class="bi {{ $s['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="text-muted small">{{ $s['label'] }}</div>
                        <div class="stat-value">{{ $s['value'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Last indexed --}}
    <div class="glass-card p-3 mb-4 d-flex align-items-center justify-content-between fade-in delay-1">
        <div class="small text-muted">
            <i class="bi bi-clock me-1"></i>
            Last indexed:
            <span class="fw-semibold" style="color:var(--text-primary);">
                {{ isset($meta['indexedAt']) ? \Carbon\Carbon::parse($meta['indexedAt'])->diffForHumans() : 'unknown' }}
            </span>
            &nbsp;·&nbsp; Commit:
            <code style="font-size:0.78rem;">{{ substr($meta['lastCommit'] ?? '—', 0, 8) }}</code>
        </div>
        <div class="small">
            <span class="badge-glass">{{ $meta['stats']['files'] ?? 0 }} files indexed</span>
        </div>
    </div>

    {{-- Graph + controls --}}
    <div class="glass-card p-3 mb-4 fade-in delay-2">
        {{-- Filter toolbar --}}
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <span class="small text-muted me-1">Show:</span>
            @foreach([
                ['model',      '#6366f1', 'Models'],
                ['service',    '#10b981', 'Services'],
                ['controller', '#f59e0b', 'Controllers'],
                ['job',        '#ef4444', 'Jobs'],
                ['policy',     '#8b5cf6', 'Policies'],
                ['command',    '#06b6d4', 'Commands'],
                ['support',    '#64748b', 'Support'],
                ['enum',       '#84cc16', 'Enums'],
                ['middleware', '#f97316', 'Middleware'],
                ['provider',   '#ec4899', 'Providers'],
            ] as [$type, $color, $label])
            <button class="btn btn-sm badge-glass filter-btn active"
                    data-type="{{ $type }}"
                    style="font-size:0.75rem; padding:3px 8px;">
                <span class="legend-dot" style="background:{{ $color }};"></span>{{ $label }}
            </button>
            @endforeach
            <button id="btn-fit" class="btn btn-sm btn-outline-secondary ms-auto" style="font-size:0.75rem;">
                <i class="bi bi-fullscreen me-1"></i>Fit
            </button>
        </div>

        <div id="cy"></div>

        <div class="mt-2 small text-muted">
            Scroll to zoom · Drag nodes · Click a node to highlight its connections
        </div>
    </div>

    {{-- Impact analysis --}}
    <div class="glass-card p-4 fade-in delay-3">
        <h5 class="fw-semibold mb-3"><i class="bi bi-bullseye me-2"></i>Impact Analysis</h5>
        <p class="small text-muted mb-3">
            Enter a class name or file path to see its blast radius.
            Run via terminal: <code style="font-size:0.8rem;">php artisan gitnexus:impact Invoice</code>
        </p>
        <div class="row g-3">
            @php
                $keySymbols = [
                    'Invoice', 'Patient', 'Prescription', 'MedGemmaService',
                    'AuditLog', 'CaseTokenService', 'AiSidecarClient',
                ];
            @endphp
            @foreach($keySymbols as $sym)
            <div class="col-auto">
                <code class="badge-glass" style="font-size:0.78rem; cursor:default;">{{ $sym }}</code>
            </div>
            @endforeach
        </div>
    </div>

    @endif
</div>
@endsection

@if($enabled && $graph)
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.30.4/cytoscape.min.js"
        integrity="sha512-6tBbfPFl7F9LVoVPlaCJfCpH9Ky8qsMNb8E6GY2E3dqD9e6hcpq97G5WcUHHi7KF8SZ5VDVPYRCy0iFzNFNLg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function () {
    const TYPE_COLORS = {
        model:      '#6366f1',
        service:    '#10b981',
        controller: '#f59e0b',
        job:        '#ef4444',
        policy:     '#8b5cf6',
        command:    '#06b6d4',
        support:    '#64748b',
        enum:       '#84cc16',
        middleware: '#f97316',
        provider:   '#ec4899',
        listener:   '#a78bfa',
        event:      '#fb923c',
        other:      '#94a3b8',
    };

    const elements = @json($graph);

    const cy = cytoscape({
        container: document.getElementById('cy'),
        elements:  elements,
        style: [
            {
                selector: 'node',
                style: {
                    'label':            'data(label)',
                    'background-color': (ele) => TYPE_COLORS[ele.data('type')] || '#94a3b8',
                    'color':            '#f8fafc',
                    'font-size':        '10px',
                    'text-valign':      'center',
                    'text-halign':      'center',
                    'width':            28,
                    'height':           28,
                    'text-wrap':        'wrap',
                    'text-max-width':   60,
                    'border-width':     1,
                    'border-color':     'rgba(255,255,255,0.15)',
                },
            },
            {
                selector: 'edge',
                style: {
                    'width':             1,
                    'line-color':        'rgba(148,163,184,0.3)',
                    'target-arrow-color':'rgba(148,163,184,0.4)',
                    'target-arrow-shape':'triangle',
                    'curve-style':       'bezier',
                    'arrow-scale':       0.7,
                },
            },
            {
                selector: 'node.highlighted',
                style: {
                    'border-width': 3,
                    'border-color': '#f8fafc',
                    'z-index':      10,
                },
            },
            {
                selector: 'node.faded, edge.faded',
                style: { opacity: 0.15 },
            },
        ],
        layout: {
            name:              'cose',
            animate:           false,
            nodeRepulsion:     5000,
            idealEdgeLength:   80,
            gravity:           0.8,
            numIter:           800,
        },
    });

    // Highlight on click
    cy.on('tap', 'node', function (evt) {
        const node = evt.target;
        cy.elements().removeClass('highlighted faded');
        const connected = node.closedNeighborhood();
        cy.elements().not(connected).addClass('faded');
        connected.addClass('highlighted');
    });
    cy.on('tap', function (evt) {
        if (evt.target === cy) {
            cy.elements().removeClass('highlighted faded');
        }
    });

    // Fit button
    document.getElementById('btn-fit').addEventListener('click', () => cy.fit(30));

    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            this.classList.toggle('active');
            const type    = this.dataset.type;
            const visible = this.classList.contains('active');
            cy.nodes(`[type = "${type}"]`).style('display', visible ? 'element' : 'none');
        });
    });
})();
</script>
@endpush
@endif
