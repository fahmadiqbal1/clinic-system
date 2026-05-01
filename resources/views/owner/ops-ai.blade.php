@extends('layouts.app')
@section('title', 'Operations AI — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-boxes me-2" style="color:var(--accent-primary);"></i>Operations AI</h2>
            <p class="page-subtitle mb-0">Inventory, procurement, expense, and queue health</p>
        </div>
        <a href="{{ route('owner.ai-oversight') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>AI Oversight
        </a>
    </div>

    <div class="card mb-3 fade-in">
        <div class="card-body">
            <form id="opsAiForm" action="{{ route('owner.ops-ai.analyse') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Domain</label>
                    <select name="domain" class="form-select">
                        <option value="general">General</option>
                        <option value="inventory">Inventory</option>
                        <option value="procurement">Procurement</option>
                        <option value="expense">Expense</option>
                        <option value="queue">Queue health</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Period (days)</label>
                    <input type="number" name="period_days" class="form-control" min="1" max="365" value="30">
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <button type="submit" id="opsAiBtn" class="btn btn-primary w-100">
                        <i class="bi bi-cpu me-1"></i>Run analysis
                    </button>
                </div>
                <div class="col-12">
                    <label class="form-label">Custom question (optional)</label>
                    <textarea name="custom_question" class="form-control" rows="2" maxlength="1000"></textarea>
                </div>
            </form>
        </div>
    </div>

    <div id="opsAiResult" class="card fade-in d-none">
        <div class="card-body">
            <div id="opsAiMeta" class="mb-3"></div>
            <div id="opsAiCritical" class="mb-2"></div>
            <div id="opsAiSections"></div>
            <div id="opsAiActions" class="mt-2"></div>
            <div id="opsAiIssues" class="text-warning small mt-2"></div>
            <details class="mt-3">
                <summary class="text-muted small" style="cursor:pointer;">Full model output</summary>
                <pre id="opsAiRaw" style="white-space:pre-wrap; font-size:0.78rem;" class="mt-2 p-2 rounded"></pre>
            </details>
        </div>
    </div>
</div>

<script>
(function () {
    function parseSections(text) {
        return text.split(/^## /m).filter(Boolean).map(function (p) {
            var nl = p.indexOf('\n');
            return { title: p.slice(0, nl).trim(), body: p.slice(nl + 1).trim() };
        });
    }
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function renderSections(sections, container) {
        container.innerHTML = sections.map(function (s) {
            return '<div class="card mb-2">' +
                '<div class="card-header py-2 fw-semibold small text-uppercase">' + escHtml(s.title) + '</div>' +
                '<div class="card-body py-2" style="white-space:pre-wrap;font-size:0.88rem;">' + escHtml(s.body) + '</div>' +
                '</div>';
        }).join('');
    }

    var URGENCY_CLASS = { Critical: 'danger', Warning: 'warning', Info: 'secondary', Healthy: 'success' };

    document.getElementById('opsAiForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn        = document.getElementById('opsAiBtn');
        var resultBox  = document.getElementById('opsAiResult');
        var metaEl     = document.getElementById('opsAiMeta');
        var criticalEl = document.getElementById('opsAiCritical');
        var sectionsEl = document.getElementById('opsAiSections');
        var actionsEl  = document.getElementById('opsAiActions');
        var issuesEl   = document.getElementById('opsAiIssues');
        var rawEl      = document.getElementById('opsAiRaw');

        var fd      = new FormData(e.target);
        var payload = Object.fromEntries(fd.entries());
        delete payload._token;
        if (payload.period_days) payload.period_days = parseInt(payload.period_days, 10);
        if (!payload.custom_question) delete payload.custom_question;

        btn.disabled = true;
        resultBox.classList.remove('d-none');
        metaEl.innerHTML    = '';
        criticalEl.innerHTML = '';
        actionsEl.innerHTML  = '';
        issuesEl.innerHTML   = '';
        rawEl.textContent    = '';
        sectionsEl.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Running analysis…</div>';

        fetch(e.target.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(payload),
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            btn.disabled = false;
            var data = res.data;
            if (!res.ok) {
                sectionsEl.innerHTML = '<div class="alert alert-danger">' + escHtml(data.error || data.message || 'Request failed.') + '</div>';
                return;
            }
            var uClass = URGENCY_CLASS[data.urgency] || 'secondary';
            metaEl.innerHTML =
                '<span class="badge bg-' + uClass + ' me-1">Urgency: ' + escHtml(data.urgency || '—') + '</span>' +
                '<span class="badge bg-secondary">Confidence: ' + (data.confidence ? (data.confidence * 100).toFixed(0) : '—') + '%</span>';

            var crit = (data.critical_items || []).map(function (c) { return '<li>' + escHtml(c) + '</li>'; }).join('');
            criticalEl.innerHTML = crit
                ? '<div class="alert alert-danger py-2"><strong>Critical items:</strong><ul class="mb-0 mt-1">' + crit + '</ul></div>'
                : '';

            var sections = parseSections(data.rationale || '');
            if (sections.length) {
                renderSections(sections, sectionsEl);
            } else {
                sectionsEl.innerHTML = '<p class="text-muted small">No structured output returned.</p>';
            }

            var acts = (data.action_items || []).map(function (a) { return '<li>' + escHtml(a) + '</li>'; }).join('');
            actionsEl.innerHTML = acts ? '<h6 class="mt-3">Action items</h6><ol>' + acts + '</ol>' : '';

            var iss = (data.verification_issues || []).map(function (i) { return '<li>' + escHtml(i) + '</li>'; }).join('');
            issuesEl.innerHTML = iss ? '<strong>Quality flags:</strong><ul>' + iss + '</ul>' : '';

            rawEl.textContent = data.rationale || '';
        })
        .catch(function (err) {
            btn.disabled = false;
            sectionsEl.innerHTML = '<div class="alert alert-warning">Network error: ' + escHtml(err.message) + '</div>';
        });
    });
}());
</script>
@endsection
