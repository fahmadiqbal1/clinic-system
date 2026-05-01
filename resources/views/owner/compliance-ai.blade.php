@extends('layouts.app')
@section('title', 'Compliance AI — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-shield-lock me-2" style="color:var(--accent-primary);"></i>Compliance AI</h2>
            <p class="page-subtitle mb-0">Audit chain, PHI access, evidence gaps, certification readiness</p>
        </div>
        <a href="{{ route('owner.ai-oversight') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>AI Oversight
        </a>
    </div>

    <div class="card mb-3 fade-in">
        <div class="card-body">
            <form id="complianceAiForm" action="{{ route('owner.compliance-ai.run') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Scope</label>
                    <select name="scope" class="form-select">
                        <option value="full">Full</option>
                        <option value="audit_chain">Audit chain</option>
                        <option value="phi_access">PHI access</option>
                        <option value="evidence_gap">Evidence gaps</option>
                        <option value="flag_snapshot">Flag snapshot</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Period (days)</label>
                    <input type="number" name="period_days" class="form-control" min="1" max="365" value="30">
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <button type="submit" id="complianceAiBtn" class="btn btn-primary w-100">
                        <i class="bi bi-cpu me-1"></i>Run compliance check
                    </button>
                </div>
                <div class="col-12">
                    <label class="form-label">Auditor question (optional)</label>
                    <textarea name="custom_question" class="form-control" rows="2" maxlength="1000"></textarea>
                </div>
            </form>
        </div>
    </div>

    <div id="complianceAiResult" class="card fade-in d-none">
        <div class="card-body">
            <div id="complianceAiMeta" class="mb-3"></div>
            <div id="complianceAiEscalation"></div>
            <div id="complianceAiSections"></div>
            <div id="complianceAiEvidence" class="small text-muted mt-2"></div>
            <div id="complianceAiIssues" class="text-warning small mt-2"></div>
            <details class="mt-3">
                <summary class="text-muted small" style="cursor:pointer;">Full model output</summary>
                <pre id="complianceAiRaw" style="white-space:pre-wrap; font-size:0.78rem;" class="mt-2 p-2 rounded"></pre>
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

    var STATUS_CLASS = { COMPLIANT: 'success', REQUIRES_REVIEW: 'warning', NON_COMPLIANT: 'danger' };

    document.getElementById('complianceAiForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn          = document.getElementById('complianceAiBtn');
        var resultBox    = document.getElementById('complianceAiResult');
        var metaEl       = document.getElementById('complianceAiMeta');
        var escalationEl = document.getElementById('complianceAiEscalation');
        var sectionsEl   = document.getElementById('complianceAiSections');
        var evidenceEl   = document.getElementById('complianceAiEvidence');
        var issuesEl     = document.getElementById('complianceAiIssues');
        var rawEl        = document.getElementById('complianceAiRaw');

        var fd      = new FormData(e.target);
        var payload = Object.fromEntries(fd.entries());
        delete payload._token;
        if (payload.period_days) payload.period_days = parseInt(payload.period_days, 10);
        if (!payload.custom_question) delete payload.custom_question;

        btn.disabled = true;
        resultBox.classList.remove('d-none');
        metaEl.innerHTML      = '';
        escalationEl.innerHTML = '';
        evidenceEl.innerHTML   = '';
        issuesEl.innerHTML     = '';
        rawEl.textContent      = '';
        sectionsEl.innerHTML   = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Running compliance checks…</div>';

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
            var sClass = STATUS_CLASS[data.status] || 'secondary';
            metaEl.innerHTML =
                '<span class="badge bg-' + sClass + ' me-1">Status: ' + escHtml(data.status || '—') + '</span>' +
                '<span class="badge bg-secondary">Confidence: ' + (data.confidence ? (data.confidence * 100).toFixed(0) : '—') + '%</span>';

            escalationEl.innerHTML = data.escalation_pending
                ? '<div class="alert alert-danger py-2 mt-2"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>ESCALATION REQUIRED</strong> — owner must review and respond.</div>'
                : '';

            var sections = parseSections(data.rationale || '');
            if (sections.length) {
                renderSections(sections, sectionsEl);
            } else {
                sectionsEl.innerHTML = '<p class="text-muted small">No structured output returned.</p>';
            }

            var refs = (data.evidence_refs || []).map(function (r) { return '<code>' + escHtml(r) + '</code>'; }).join(' ');
            evidenceEl.innerHTML = refs ? '<strong>Evidence refs:</strong> ' + refs : '';

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
