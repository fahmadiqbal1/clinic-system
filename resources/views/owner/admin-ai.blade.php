@extends('layouts.app')
@section('title', 'Administrative AI — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-graph-up-arrow me-2" style="color:var(--accent-primary);"></i>Administrative AI</h2>
            <p class="page-subtitle mb-0">Revenue, discount, FBR, and payout findings — owner-facing</p>
        </div>
        <a href="{{ route('owner.ai-oversight') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>AI Oversight
        </a>
    </div>

    <div class="card mb-3 fade-in">
        <div class="card-body">
            <form id="adminAiForm" action="{{ route('owner.admin-ai.analyse') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Query type</label>
                    <select name="query_type" class="form-select">
                        <option value="general">General</option>
                        <option value="revenue_anomaly">Revenue anomaly</option>
                        <option value="discount_risk">Discount risk</option>
                        <option value="fbr_status">FBR status</option>
                        <option value="payout_audit">Payout audit</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Period (days)</label>
                    <input type="number" name="period_days" class="form-control" min="1" max="365" value="7">
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <button type="submit" id="adminAiBtn" class="btn btn-primary w-100">
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

    <div id="adminAiResult" class="card fade-in d-none">
        <div class="card-body">
            <div id="adminAiMeta" class="mb-3"></div>
            <div id="adminAiSections"></div>
            <div id="adminAiActions" class="mt-2"></div>
            <div id="adminAiIssues" class="text-warning small mt-2"></div>
            <details class="mt-3">
                <summary class="text-muted small" style="cursor:pointer;">Full model output</summary>
                <pre id="adminAiRaw" style="white-space:pre-wrap; font-size:0.78rem;" class="mt-2 p-2 rounded" style="background:rgba(0,0,0,0.2);"></pre>
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

    var PRIORITY_CLASS = { Critical: 'danger', High: 'warning', Medium: 'info', Low: 'secondary' };

    document.getElementById('adminAiForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn         = document.getElementById('adminAiBtn');
        var resultBox   = document.getElementById('adminAiResult');
        var metaEl      = document.getElementById('adminAiMeta');
        var sectionsEl  = document.getElementById('adminAiSections');
        var actionsEl   = document.getElementById('adminAiActions');
        var issuesEl    = document.getElementById('adminAiIssues');
        var rawEl       = document.getElementById('adminAiRaw');

        var fd      = new FormData(e.target);
        var payload = Object.fromEntries(fd.entries());
        delete payload._token;
        if (payload.period_days) payload.period_days = parseInt(payload.period_days, 10);
        if (!payload.custom_question) delete payload.custom_question;

        btn.disabled = true;
        resultBox.classList.remove('d-none');
        metaEl.innerHTML  = '';
        actionsEl.innerHTML = '';
        issuesEl.innerHTML  = '';
        rawEl.textContent   = '';
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
            var pClass = PRIORITY_CLASS[data.priority] || 'secondary';
            metaEl.innerHTML =
                '<span class="badge bg-' + pClass + ' me-1">Priority: ' + escHtml(data.priority || 'Medium') + '</span>' +
                '<span class="badge bg-secondary">Confidence: ' + (data.confidence ? (data.confidence * 100).toFixed(0) : '—') + '%</span>';

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
