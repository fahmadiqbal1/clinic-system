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
            <form id="adminAiForm" class="row g-3">
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
                    <button type="submit" class="btn btn-primary w-100">
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
            <div id="adminAiPriority" class="mb-2"></div>
            <pre id="adminAiRationale" style="white-space:pre-wrap;"></pre>
            <div id="adminAiActions"></div>
            <div id="adminAiIssues" class="text-warning small mt-3"></div>
        </div>
    </div>
</div>

<script>
document.getElementById('adminAiForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    const payload = Object.fromEntries(fd.entries());
    delete payload._token;

    const resultBox = document.getElementById('adminAiResult');
    resultBox.classList.remove('d-none');
    document.getElementById('adminAiRationale').textContent = 'Analysing…';

    const r = await fetch('{{ route('owner.admin-ai.analyse') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify(payload),
    });
    const data = await r.json();
    if (!r.ok) {
        document.getElementById('adminAiRationale').textContent = data.error || 'Request failed.';
        return;
    }
    document.getElementById('adminAiPriority').innerHTML =
        `<span class="badge bg-info">Priority: ${data.priority || 'Medium'}</span> ` +
        `<span class="badge bg-secondary">Confidence: ${(data.confidence * 100).toFixed(0)}%</span>`;
    document.getElementById('adminAiRationale').textContent = data.rationale || '';
    const actions = (data.action_items || []).map(a => `<li>${a}</li>`).join('');
    document.getElementById('adminAiActions').innerHTML =
        actions ? `<h6 class="mt-3">Action items</h6><ol>${actions}</ol>` : '';
    const issues = (data.verification_issues || []).map(i => `<li>${i}</li>`).join('');
    document.getElementById('adminAiIssues').innerHTML =
        issues ? `<strong>Quality flags:</strong><ul>${issues}</ul>` : '';
});
</script>
@endsection
