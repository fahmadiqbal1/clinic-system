{{--
    AI Knowledge Assistant Panel — Phase 3
    Props:
      $collection  (string)  – RAGFlow collection slug e.g. 'service_catalog'
      $panelTitle  (string)  – heading shown above the chat
      $placeholder (string)  – textarea placeholder
--}}
@php
    $collection  = $collection  ?? 'general';
    $panelTitle  = $panelTitle  ?? 'Knowledge Assistant';
    $placeholder = $placeholder ?? 'Ask about protocols, services, or drug information…';
    $panelId     = 'aiPanel_' . Str::random(6);
@endphp

<div class="card mb-4 fade-in" id="{{ $panelId }}" style="border:1px solid rgba(129,140,248,0.3);">
    <div class="card-header d-flex align-items-center gap-2"
         style="background:rgba(129,140,248,0.08); cursor:pointer;"
         data-bs-toggle="collapse"
         data-bs-target="#{{ $panelId }}_body">
        <span style="width:8px; height:8px; border-radius:50%; background:var(--accent-primary); display:inline-block;" id="{{ $panelId }}_dot"></span>
        <i class="bi bi-stars" style="color:var(--accent-primary);"></i>
        <span class="fw-semibold" style="color:var(--text-primary); font-size:0.92rem;">{{ $panelTitle }}</span>
        <span class="badge-glass ms-auto" style="font-size:0.72rem; color:var(--text-muted);">Beta · Powered by RAGFlow</span>
        <i class="bi bi-chevron-down ms-1" style="color:var(--text-muted); font-size:0.8rem;"></i>
    </div>

    <div class="collapse" id="{{ $panelId }}_body">
        <div class="card-body p-3">
            {{-- Chat history --}}
            <div id="{{ $panelId }}_history"
                 style="min-height:60px; max-height:320px; overflow-y:auto; margin-bottom:0.75rem;"></div>

            {{-- Input row --}}
            <div class="d-flex gap-2">
                <textarea id="{{ $panelId }}_input"
                          class="form-control form-control-sm"
                          rows="2"
                          placeholder="{{ $placeholder }}"
                          style="resize:none; font-size:0.875rem;"></textarea>
                <div class="d-flex flex-column gap-1">
                    <button class="btn btn-primary btn-sm" id="{{ $panelId }}_ask">
                        <i class="bi bi-send"></i>
                    </button>
                    <button class="btn btn-outline-warning btn-sm d-none" id="{{ $panelId }}_flag" title="Flag for Owner review">
                        <i class="bi bi-flag"></i>
                    </button>
                </div>
            </div>
            <small class="text-muted mt-1 d-block" style="font-size:0.72rem;">
                AI answers are informational only. Always verify with clinical guidelines. <span id="{{ $panelId }}_flagStatus"></span>
            </small>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var id       = {{ Js::from($panelId) }};
    var coll     = {{ Js::from($collection) }};
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    var history  = document.getElementById(id + '_history');
    var input    = document.getElementById(id + '_input');
    var askBtn   = document.getElementById(id + '_ask');
    var flagBtn  = document.getElementById(id + '_flag');
    var flagStatus = document.getElementById(id + '_flagStatus');
    var lastAnswer  = '';
    var lastQuery   = '';
    var lastCitations = [];

    function bubble(role, html, citations) {
        var isAi = role === 'ai';
        var wrap = document.createElement('div');
        wrap.className = 'd-flex ' + (isAi ? 'justify-content-start' : 'justify-content-end') + ' mb-2';
        var inner = document.createElement('div');
        inner.style = 'max-width:85%; padding:0.5rem 0.75rem; border-radius:0.75rem; font-size:0.85rem; line-height:1.5; ' +
            (isAi
                ? 'background:rgba(129,140,248,0.12); border:1px solid rgba(129,140,248,0.25); color:var(--text-primary);'
                : 'background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.2); color:var(--text-primary);');
        inner.innerHTML = html;
        if (isAi && citations && citations.length) {
            var citDiv = document.createElement('div');
            citDiv.style = 'margin-top:0.4rem; font-size:0.72rem; color:var(--text-muted);';
            citDiv.innerHTML = '<i class="bi bi-journals me-1"></i>' + citations.map(function(c) {
                return '<span class="badge-glass me-1">' + c + '</span>';
            }).join('');
            inner.appendChild(citDiv);
        }
        wrap.appendChild(inner);
        history.appendChild(wrap);
        history.scrollTop = history.scrollHeight;
    }

    function spinner(show) {
        askBtn.disabled = show;
        askBtn.innerHTML = show
            ? '<span class="spinner-border spinner-border-sm"></span>'
            : '<i class="bi bi-send"></i>';
    }

    askBtn.addEventListener('click', function() {
        var q = input.value.trim();
        if (!q) return;
        bubble('user', q.replace(/</g,'&lt;'), []);
        input.value = '';
        flagBtn.classList.add('d-none');
        flagStatus.textContent = '';
        spinner(true);

        fetch('/ai-assistant/query', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({query: q, collection: coll}),
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            spinner(false);
            if (data.error) {
                bubble('ai', '<em style="color:var(--accent-warning);">' + data.error + '</em>', []);
                return;
            }
            var answer = data.answer || 'No answer returned.';
            bubble('ai', answer.replace(/</g,'&lt;').replace(/\n/g,'<br>'), data.citations || []);
            lastQuery     = q;
            lastAnswer    = answer;
            lastCitations = data.citations || [];
            flagBtn.classList.remove('d-none');
        })
        .catch(function() {
            spinner(false);
            bubble('ai', '<em style="color:var(--accent-danger);">Request failed — please try again.</em>', []);
        });
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); askBtn.click(); }
    });

    flagBtn.addEventListener('click', function() {
        flagBtn.disabled = true;
        fetch('/ai-assistant/flag', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({query: lastQuery, answer: lastAnswer, citations: lastCitations}),
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            flagStatus.textContent = data.flagged ? '✓ Flagged for Owner review.' : '';
            flagBtn.classList.add('d-none');
        })
        .catch(function() {
            flagBtn.disabled = false;
        });
    });
})();
</script>
@endpush
