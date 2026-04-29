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
            <form id="complianceAiForm" class="row g-3">
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
                    <button type="submit" class="btn btn-primary w-100">
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
            <div id="complianceAiStatus" class="mb-2"></div>
            <pre id="complianceAiRationale" style="white-space:pre-wrap;"></pre>
            <div id="complianceAiEvidence" class="small text-muted"></div>
            <div id="complianceAiIssues" class="text-warning small mt-3"></div>
        </div>
    </div>
</div>

<script>
document.getElementById('complianceAiForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const payload = Object.fromEntries(fd.entries());
    delete payload._token;
    const box = document.getElementById('complianceAiResult');
    box.classList.remove('d-none');
    document.getElementById('complianceAiRationale').textContent = 'Running compliance checks…';

    const r = await fetch('{{ route('owner.compliance-ai.run') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload),
    });
    const data = await r.json();
    if (!r.ok) {
        document.getElementById('complianceAiRationale').textContent = data.error || 'Request failed.';
        return;
    }
    const statusClass = {
        COMPLIANT: 'success', REQUIRES_REVIEW: 'warning', NON_COMPLIANT: 'danger',
    }[data.status] || 'secondary';
    let banner = '';
    if (data.escalation_pending) {
        banner = '<div class="alert alert-danger mt-2">⚠️ ESCALATION REQUIRED — owner must review and respond.</div>';
    }
    document.getElementById('complianceAiStatus').innerHTML =
        `<span class="badge bg-${statusClass}">Status: ${data.status}</span> ` +
        `<span class="badge bg-secondary">Confidence: ${(data.confidence * 100).toFixed(0)}%</span>` +
        banner;
    document.getElementById('complianceAiRationale').textContent = data.rationale || '';
    const refs = (data.evidence_refs || []).map(r => `<code>${r}</code>`).join(' ');
    document.getElementById('complianceAiEvidence').innerHTML =
        refs ? `<strong>Evidence refs:</strong> ${refs}` : '';
    const issues = (data.verification_issues || []).map(i => `<li>${i}</li>`).join('');
    document.getElementById('complianceAiIssues').innerHTML =
        issues ? `<strong>Quality flags:</strong><ul>${issues}</ul>` : '';
});
</script>
@endsection
