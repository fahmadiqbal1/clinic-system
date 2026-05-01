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
            <span id="graphSampleBadge" class="badge bg-secondary ms-1" style="font-size:0.7rem;"></span>
        </div>

        <div style="position:relative; height:560px;">
            <div id="cy" style="width:100%;height:100%;"></div>
            <div id="cy-loading" class="d-flex align-items-center justify-content-center"
                 style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.85);border-radius:var(--card-radius,12px);color:#94a3b8;z-index:10;">
                <span class="spinner-border spinner-border-sm me-2"></span> Loading graph…
            </div>
        </div>

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
<script src="{{ asset('js/cytoscape.min.js') }}"></script>
<script>
(function () {
    function showErr(msg) {
        var el = document.getElementById('cy-loading');
        if (el) el.innerHTML = '<span class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>' + msg + '</span>';
    }
    if (typeof cytoscape === 'undefined') { showErr('Graph library not loaded.'); return; }

    var TYPE_COLORS = {
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

    try {
    var raw      = @json($graph);
    } catch(e) { showErr('Graph data parse error: ' + e.message); return; }
    var allNodes = (raw && raw.nodes) ? raw.nodes : (Array.isArray(raw) ? raw.filter(function(e){return !e.data.source;}) : []);
    var allEdges = (raw && raw.edges) ? raw.edges : (Array.isArray(raw) ? raw.filter(function(e){return  e.data.source;}) : []);

    // Sample top MAX_NODES nodes by degree so the browser doesn't freeze
    var MAX_NODES = 400;
    var degree    = {};
    allEdges.forEach(function (e) {
        degree[e.data.source] = (degree[e.data.source] || 0) + 1;
        degree[e.data.target] = (degree[e.data.target] || 0) + 1;
    });
    var sorted    = allNodes.slice().sort(function (a, b) { return (degree[b.data.id] || 0) - (degree[a.data.id] || 0); });
    var topIds    = new Set(sorted.slice(0, MAX_NODES).map(function (n) { return n.data.id; }));
    var nodes     = allNodes.filter(function (n) { return topIds.has(n.data.id); });
    var edges     = allEdges.filter(function (e) { return topIds.has(e.data.source) && topIds.has(e.data.target); });

    if (!allNodes.length) { showErr('No graph nodes found. Run: php artisan gitnexus:scan'); return; }

    document.getElementById('graphSampleBadge').textContent =
        allNodes.length > MAX_NODES ? 'Top ' + MAX_NODES + ' of ' + allNodes.length + ' nodes' : allNodes.length + ' nodes';

    function hideLoader() {
        var loader = document.getElementById('cy-loading');
        if (loader) loader.style.display = 'none';
    }
    // Fallback: hide spinner after 3s regardless of layout state
    var loaderTimeout = setTimeout(hideLoader, 3000);

    try {
    var cy = cytoscape({
        container: document.getElementById('cy'),
        elements:  { nodes: nodes, edges: edges },
        style: [
            {
                selector: 'node',
                style: {
                    'label':            'data(label)',
                    'background-color': function (ele) { return TYPE_COLORS[ele.data('type')] || '#94a3b8'; },
                    'color':            '#f8fafc',
                    'font-size':        '9px',
                    'text-valign':      'center',
                    'text-halign':      'center',
                    'width':            22,
                    'height':           22,
                    'text-wrap':        'wrap',
                    'text-max-width':   50,
                    'border-width':     1,
                    'border-color':     'rgba(255,255,255,0.15)',
                },
            },
            {
                selector: 'edge',
                style: {
                    'width':              1,
                    'line-color':         'rgba(148,163,184,0.25)',
                    'target-arrow-color': 'rgba(148,163,184,0.35)',
                    'target-arrow-shape': 'triangle',
                    'curve-style':        'bezier',
                    'arrow-scale':        0.6,
                },
            },
            {
                selector: 'node.highlighted',
                style: { 'border-width': 3, 'border-color': '#f8fafc', 'z-index': 10 },
            },
            {
                selector: 'node.faded, edge.faded',
                style: { opacity: 0.12 },
            },
        ],
        layout: {
            name:         'grid',
            animate:      false,
            padding:      10,
            avoidOverlap: true,
        },
    });

    // Hide spinner once layout completes (works for both sync and async layouts)
    cy.one('layoutstop', function () { clearTimeout(loaderTimeout); hideLoader(); });
    // Also hide immediately in case layoutstop already fired synchronously
    clearTimeout(loaderTimeout); hideLoader();
    cy.fit(30);

    // Highlight on click
    cy.on('tap', 'node', function (evt) {
        var node      = evt.target;
        var connected = node.closedNeighborhood();
        cy.elements().removeClass('highlighted faded');
        cy.elements().not(connected).addClass('faded');
        connected.addClass('highlighted');
    });
    cy.on('tap', function (evt) {
        if (evt.target === cy) {
            cy.elements().removeClass('highlighted faded');
        }
    });

    // Fit button
    document.getElementById('btn-fit').addEventListener('click', function () { cy.fit(30); });

    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            this.classList.toggle('active');
            var type    = this.dataset.type;
            var visible = this.classList.contains('active');
            cy.nodes('[type = "' + type + '"]').style('display', visible ? 'element' : 'none');
        });
    });
    } catch(e) { showErr('Graph render error: ' + e.message); }
})();
</script>
@endpush
@endif
