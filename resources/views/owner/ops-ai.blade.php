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
            <form id="opsAiForm" class="row g-3">
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

    <div id="opsAiResult" class="card fade-in d-none">
        <div class="card-body">
            <div id="opsAiUrgency" class="mb-2"></div>
            <pre id="opsAiRationale" style="white-space:pre-wrap;"></pre>
            <div id="opsAiCritical"></div>
            <div id="opsAiActions"></div>
            <div id="opsAiIssues" class="text-warning small mt-3"></div>
        </div>
    </div>
</div>

<script>
document.getElementById('opsAiForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const payload = Object.fromEntries(fd.entries());
    delete payload._token;
    const box = document.getElementById('opsAiResult');
    box.classList.remove('d-none');
    document.getElementById('opsAiRationale').textContent = 'Analysing…';

    const r = await fetch('{{ route('owner.ops-ai.analyse') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload),
    });
    const data = await r.json();
    if (!r.ok) {
        document.getElementById('opsAiRationale').textContent = data.error || 'Request failed.';
        return;
    }
    const urgencyClass = { Critical: 'danger', Warning: 'warning', Info: 'secondary' }[data.urgency] || 'secondary';
    document.getElementById('opsAiUrgency').innerHTML =
        `<span class="badge bg-${urgencyClass}">Urgency: ${data.urgency}</span> ` +
        `<span class="badge bg-secondary">Confidence: ${(data.confidence * 100).toFixed(0)}%</span>`;
    document.getElementById('opsAiRationale').textContent = data.rationale || '';
    const crit = (data.critical_items || []).map(c => `<li>${c}</li>`).join('');
    document.getElementById('opsAiCritical').innerHTML =
        crit ? `<h6 class="mt-3 text-danger">Critical items</h6><ul>${crit}</ul>` : '';
    const acts = (data.action_items || []).map(a => `<li>${a}</li>`).join('');
    document.getElementById('opsAiActions').innerHTML =
        acts ? `<h6 class="mt-3">Action items</h6><ol>${acts}</ol>` : '';
    const issues = (data.verification_issues || []).map(i => `<li>${i}</li>`).join('');
    document.getElementById('opsAiIssues').innerHTML =
        issues ? `<strong>Quality flags:</strong><ul>${issues}</ul>` : '';
});
</script>
@endsection
