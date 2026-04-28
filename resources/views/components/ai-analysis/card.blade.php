{{-- MedGemma AI Analysis Card --}}
{{-- Usage: @include('components.ai-analysis.card', ['analyses' => $analyses, 'formAction' => route(...), 'contextLabel' => 'Consultation', 'readinessNote' => '...', 'quickChatAction' => route('ai-analysis.quick-chat', $patient)]) --}}

<div class="card mb-4 fade-in delay-3" id="ai-analysis-section">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-robot me-2" style="color:var(--accent-secondary);"></i>MedGemma AI Second Opinion</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Worker status pill (populated by JS) --}}
            <span id="ai-worker-status" class="badge-glass" style="font-size:0.75rem;padding:0.25rem 0.6rem;display:none;">
                <span id="ai-worker-dot" class="me-1" style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#aaa;"></span>
                <span id="ai-worker-label">Checking…</span>
            </span>
            {{-- Manual start button (shown only when worker is down) --}}
            <button id="ai-worker-start-btn" type="button"
                    class="btn btn-warning btn-sm d-none"
                    title="Queue worker is not running — click to start it">
                <i class="bi bi-play-fill me-1"></i>Start Queue Worker
            </button>
            @if(isset($formAction))
            <form action="{{ $formAction }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-info btn-sm" onclick="return confirm('Request MedGemma AI analysis for this {{ $contextLabel ?? 'record' }}?')">
                    <i class="bi bi-stars me-1"></i>Get AI Analysis
                </button>
            </form>
            @endif
        </div>
    </div>
    <div class="card-body">
        {{-- Readiness guidance --}}
        @if(isset($readinessNote) && $readinessNote)
        <div class="alert mb-3 d-flex align-items-start gap-2" style="background:rgba(var(--accent-info-rgb),0.1); border:1px solid rgba(var(--accent-info-rgb),0.3); color:var(--accent-info); border-radius:var(--radius-md); font-size:0.88rem;">
            <i class="bi bi-lightbulb mt-1"></i>
            <div>{!! $readinessNote !!}</div>
        </div>
        @endif

        @if(isset($analyses) && $analyses->count() > 0)
            @foreach($analyses as $analysis)
            <div class="mb-3 p-3 rounded ai-analysis-item" id="analysis-{{ $analysis->id }}"
                 data-created-at="{{ $analysis->created_at->timestamp }}"
                 style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small style="color:var(--text-muted);">
                        <i class="bi bi-clock me-1"></i>{{ $analysis->created_at->format('M d, Y H:i') }}
                        &mdash; requested by {{ $analysis->requester?->name ?? 'System' }}
                    </small>
                    @php
                        $statusStyle = match($analysis->status) {
                            'completed'       => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            'failed'          => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                            'offline_pending' => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                            default           => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                        };
                        $statusLabel = match($analysis->status) {
                            'offline_pending' => 'Queued (AI Offline)',
                            default           => ucfirst(str_replace('_', ' ', $analysis->status)),
                        };
                    @endphp
                    <span class="badge-glass ai-status-badge"
                          data-analysis-id="{{ $analysis->id }}"
                          data-status="{{ $analysis->status }}"
                          style="{{ $statusStyle }}">
                        @if($analysis->status === 'pending')
                            <span class="spinner-border spinner-border-sm me-1" role="status" style="width:0.7rem;height:0.7rem;border-width:0.1em;"></span>
                        @elseif($analysis->status === 'offline_pending')
                            <i class="bi bi-wifi-off me-1"></i>
                        @endif
                        {{ $statusLabel }}
                        @if($analysis->status === 'pending')
                            <span class="ai-elapsed-timer ms-1" style="font-size:0.8em;opacity:0.8;"></span>
                        @endif
                    </span>
                </div>
                {{-- Stuck warning banner (shown by JS after 3 min with no result) --}}
                @if($analysis->status === 'pending')
                <div class="ai-stuck-warning d-none mb-2 px-2 py-1 rounded" style="background:rgba(var(--accent-warning-rgb),0.12);border:1px solid rgba(var(--accent-warning-rgb),0.35);font-size:0.82rem;color:var(--accent-warning);">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Taking longer than expected.</strong>
                    MedGemma is a large model — first run can take 3–5 minutes on CPU.
                    If it never completes, make sure the queue worker is running (<code>start.bat</code>) and Ollama is online.
                </div>
                @endif
                <div class="ai-response-text" style="color:var(--text-secondary); white-space:pre-line; font-size:0.9rem;">
                    @if($analysis->status === 'offline_pending')
                        <span style="color:var(--accent-warning);">
                            <i class="bi bi-hourglass-split me-1"></i>Will retry automatically every 5 minutes when your computer is connected.
                        </span>
                    @elseif($analysis->status === 'pending')
                        <span style="color:var(--text-muted); font-size:0.87rem;">
                            <i class="bi bi-cpu me-1"></i>MedGemma is analysing — this typically takes <strong>1–3 minutes</strong>. The result will appear here automatically.
                        </span>
                    @else
                        {!! nl2br(e($analysis->ai_response ?? 'No response recorded.')) !!}
                    @endif
                </div>
            </div>
            @endforeach
        @else
            <p class="mb-0" style="color:var(--text-muted);">
                <i class="bi bi-info-circle me-1"></i>No AI analysis has been performed yet. Click "Get AI Analysis" to request a second opinion from MedGemma.
            </p>
        @endif

        {{-- Quick Chat (Doctor consultation only) --}}
        @if(isset($quickChatAction))
        <div class="mt-3 border-top pt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleQuickChat">
                <i class="bi bi-chat-dots me-1"></i>Ask MedGemma a Question
            </button>
            <div id="quickChatPanel" class="mt-2" style="display:none;">
                <div class="input-group">
                    <input type="text" id="quickChatInput" class="form-control"
                           placeholder="e.g. Could this be a drug interaction? What are differential diagnoses?"
                           maxlength="1000">
                    <button type="button" class="btn btn-info" id="quickChatSubmit">
                        <i class="bi bi-send"></i>
                    </button>
                </div>
                <div id="quickChatStatus" class="mt-1" style="display:none; font-size:0.82rem; color:var(--accent-info);">
                    <span class="spinner-border spinner-border-sm me-1" style="width:0.75rem;height:0.75rem;border-width:0.1em;"></span>
                    MedGemma is thinking...
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function() {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── Elapsed timer for pending analyses ──
    function formatElapsed(seconds) {
        if (seconds < 60) return seconds + 's';
        const m = Math.floor(seconds / 60), s = seconds % 60;
        return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
    }

    document.querySelectorAll('.ai-analysis-item').forEach(function(item) {
        const badge = item.querySelector('.ai-status-badge[data-status="pending"]');
        if (!badge) return;
        const createdAt  = parseInt(item.dataset.createdAt, 10);
        const timerEl    = item.querySelector('.ai-elapsed-timer');
        const stuckEl    = item.querySelector('.ai-stuck-warning');
        const STUCK_SECS = 180; // show warning after 3 minutes

        setInterval(function() {
            if (badge.dataset.status !== 'pending') return;
            const elapsed = Math.floor(Date.now() / 1000) - createdAt;
            if (timerEl) timerEl.textContent = '(' + formatElapsed(elapsed) + ')';
            if (stuckEl && elapsed >= STUCK_SECS) stuckEl.classList.remove('d-none');
        }, 1000);
    });

    // ── Auto-poll pending and offline_pending analyses ──
    // pending: every 8s; offline_pending: every 30s (waiting for tunnel to come up)
    const pendingBadges = document.querySelectorAll('.ai-status-badge[data-status="pending"], .ai-status-badge[data-status="offline_pending"]');
    if (pendingBadges.length > 0) {
        const intervals = {};
        pendingBadges.forEach(function(badge) {
            const id       = badge.dataset.analysisId;
            const interval = badge.dataset.status === 'offline_pending' ? 30000 : 8000;
            intervals[id]  = setInterval(function() {
                fetch('/ai-analysis/' + id + '/status', {
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                })
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(data) {
                    if (data.status === 'completed' || data.status === 'failed') {
                        clearInterval(intervals[id]);
                        const successStyle = 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);';
                        const dangerStyle  = 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);';
                        badge.style.cssText = data.status === 'completed' ? successStyle : dangerStyle;
                        badge.dataset.status = data.status;
                        badge.innerHTML = data.status === 'completed'
                            ? '<i class="bi bi-check-circle me-1"></i>Completed'
                            : '<i class="bi bi-x-circle me-1"></i>Failed';
                        const item    = document.querySelector('#analysis-' + id);
                        const stuckEl = item ? item.querySelector('.ai-stuck-warning') : null;
                        const respEl  = item ? item.querySelector('.ai-response-text') : null;
                        if (stuckEl) stuckEl.classList.add('d-none');
                        if (respEl) {
                            const text = data.response || (data.status === 'failed' ? 'Analysis failed. Please try again.' : '');
                            respEl.style.whiteSpace = 'pre-line';
                            respEl.textContent = text;
                        }
                    } else if (data.status === 'pending' && badge.dataset.status === 'offline_pending') {
                        // Ollama came back online — switch to fast polling
                        clearInterval(intervals[id]);
                        badge.dataset.status = 'pending';
                        badge.innerHTML = '<span class="spinner-border spinner-border-sm me-1" style="width:0.7rem;height:0.7rem;border-width:0.1em;"></span>Pending <span class="ai-elapsed-timer ms-1" style="font-size:0.8em;opacity:0.8;"></span>';
                        intervals[id] = setInterval(arguments.callee, 8000);
                    }
                })
                .catch(function(err) {
                    console.warn('[AI Analysis] Status poll failed for #' + id + ':', err.message);
                });
            }, interval);
        });
    }

    // ── Quick Chat ──
    const toggleBtn  = document.getElementById('toggleQuickChat');
    const chatPanel  = document.getElementById('quickChatPanel');
    const chatInput  = document.getElementById('quickChatInput');
    const chatSubmit = document.getElementById('quickChatSubmit');
    const chatStatus = document.getElementById('quickChatStatus');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const visible = chatPanel.style.display !== 'none';
            chatPanel.style.display = visible ? 'none' : 'block';
            if (!visible && chatInput) chatInput.focus();
        });
    }

    function submitQuickChat() {
        const question = chatInput?.value?.trim();
        if (!question || chatSubmit.disabled) return;
        chatSubmit.disabled = true;
        if (chatStatus) chatStatus.style.display = 'block';

        fetch('{{ $quickChatAction ?? '' }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ question })
        })
        .then(r => r.json())
        .then(() => window.location.reload())
        .catch(() => {
            if (chatStatus) chatStatus.style.display = 'none';
            chatSubmit.disabled = false;
            alert('Failed to send question. Please try again.');
        });
    }

    if (chatSubmit) chatSubmit.addEventListener('click', submitQuickChat);
    if (chatInput) {
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitQuickChat(); }
        });
    }

    // ── Queue Worker Status ──
    const workerStatusEl = document.getElementById('ai-worker-status');
    const workerDotEl    = document.getElementById('ai-worker-dot');
    const workerLabelEl  = document.getElementById('ai-worker-label');
    const workerStartBtn = document.getElementById('ai-worker-start-btn');

    function applyWorkerStatus(running) {
        if (!workerStatusEl) return;
        workerStatusEl.style.display = '';
        if (running) {
            workerDotEl.style.background  = 'var(--accent-success, #22c55e)';
            workerLabelEl.textContent      = 'Queue Worker Running';
            workerStartBtn.classList.add('d-none');
        } else {
            workerDotEl.style.background  = 'var(--accent-danger, #ef4444)';
            workerLabelEl.textContent      = 'Queue Worker Stopped';
            workerStartBtn.classList.remove('d-none');
        }
    }

    function checkWorkerStatus() {
        fetch('/queue/worker/status', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(data => applyWorkerStatus(data.running))
            .catch(() => { /* silent — don't alarm on network hiccup */ });
    }

    if (workerStatusEl) {
        checkWorkerStatus();
        // Re-check every 30 seconds (worker can be started/stopped independently)
        setInterval(checkWorkerStatus, 30000);
    }

    if (workerStartBtn) {
        workerStartBtn.addEventListener('click', function() {
            workerStartBtn.disabled = true;
            workerStartBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" style="width:0.75rem;height:0.75rem;border-width:0.1em;"></span>Starting…';

            fetch('/queue/worker/start', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
            })
            .then(r => r.json())
            .then(function(data) {
                applyWorkerStatus(data.started);
                workerStartBtn.disabled = false;
                workerStartBtn.innerHTML = '<i class="bi bi-play-fill me-1"></i>Start Queue Worker';
                if (!data.started) alert(data.message);
            })
            .catch(function() {
                workerStartBtn.disabled = false;
                workerStartBtn.innerHTML = '<i class="bi bi-play-fill me-1"></i>Start Queue Worker';
                alert('Could not reach the server. Please run start.bat manually.');
            });
        });
    }
})();
</script>
@endpush
