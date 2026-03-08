@extends('layouts.app')
@section('title', 'Platform Settings — ' . config('app.name'))

@section('content')
<div class="container py-4">

    {{-- Page Header --}}
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-cpu me-2"></i>Platform Settings</h1>
            <p class="text-muted mb-0">Manage AI platform connections and API credentials</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- MedGemma / Hugging Face Card --}}
    <div class="glass-panel p-4 mb-4">

        {{-- Card Header with live status badge --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2 bg-primary bg-opacity-10">
                    <i class="bi bi-robot fs-4 text-primary"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-semibold">MedGemma AI (via Ollama)</h5>
                    <small class="text-muted">Medical AI analysis powered by Google's MedGemma model running locally</small>
                </div>
            </div>
            {{-- Live status badge — updated by JS after connection test --}}
            <span id="status-badge"
                  class="badge {{ $medgemma->statusBadgeClass() }} d-flex align-items-center gap-1 px-3 py-2 fs-6"
                  style="min-width: 160px; justify-content: center;">
                <i id="status-icon" class="bi {{ $medgemma->statusIcon() }}"></i>
                <span id="status-label">{{ $medgemma->statusLabel() }}</span>
            </span>
        </div>

        {{-- Last tested info --}}
        @if($medgemma->last_tested_at)
            <p class="text-muted small mb-3" id="last-tested-text">
                <i class="bi bi-clock me-1"></i>Last tested: {{ $medgemma->last_tested_at->diffForHumans() }}
            </p>
        @else
            <p class="text-muted small mb-3" id="last-tested-text">
                <i class="bi bi-clock me-1"></i>Never tested
            </p>
        @endif

        {{-- Error message (if last test failed) --}}
        <div id="error-alert" class="{{ $medgemma->status === 'failed' ? '' : 'd-none' }} alert alert-danger alert-dismissible mb-3" role="alert">
            <strong><i class="bi bi-exclamation-triangle me-1"></i>Connection error:</strong>
            <span id="error-message">{{ $medgemma->last_error }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        {{-- Settings form --}}
        <form method="POST" action="{{ route('owner.platform-settings.update') }}" id="platform-form">
            @csrf
            @method('PATCH')

            {{-- Provider selector --}}
            <div class="mb-3">
                <label class="form-label fw-medium">Provider</label>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="provider" id="ps_provider_hf" value="huggingface"
                               {{ old('provider', $medgemma->provider ?? config('medgemma.provider', 'ollama')) === 'huggingface' ? 'checked' : '' }}>
                        <label class="form-check-label" for="ps_provider_hf">
                            <i class="bi bi-cloud me-1"></i>Hugging Face <span class="text-muted small">(cloud API)</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="provider" id="ps_provider_ollama" value="ollama"
                               {{ old('provider', $medgemma->provider ?? config('medgemma.provider', 'ollama')) === 'ollama' ? 'checked' : '' }}>
                        <label class="form-check-label" for="ps_provider_ollama">
                            <i class="bi bi-pc-display me-1"></i>Ollama <span class="text-muted small">(local, free)</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                {{-- API Key (HuggingFace only) --}}
                <div class="col-12" id="ps-api-key-group">
                    <label for="api_key" class="form-label fw-medium">
                        Hugging Face API Key
                        <a href="https://huggingface.co/settings/tokens" target="_blank" rel="noopener" class="ms-1 text-muted small">
                            <i class="bi bi-box-arrow-up-right"></i> Get a key
                        </a>
                    </label>
                    <div class="input-group">
                        <input type="password"
                               id="api_key"
                               name="api_key"
                               class="form-control @error('api_key') is-invalid @enderror"
                               placeholder="{{ $medgemma->hasApiKey() ? '••••••••••••••••••••  (key saved — enter new value to change)' : 'hf_...' }}"
                               autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" id="toggle-key" title="Show/hide key">
                            <i class="bi bi-eye" id="toggle-icon"></i>
                        </button>
                        @error('api_key')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @if($medgemma->hasApiKey())
                        <div class="form-text text-success">
                            <i class="bi bi-check-circle me-1"></i>An API key is currently saved. Leave blank to keep the existing key.
                        </div>
                    @else
                        <div class="form-text text-warning">
                            <i class="bi bi-exclamation-circle me-1"></i>No API key saved yet. AI analysis features will not work until a key is provided.
                        </div>
                    @endif
                </div>

                {{-- Model --}}
                <div class="col-md-6">
                    <label for="model" class="form-label fw-medium">Model</label>
                    <input type="text"
                           id="model"
                           name="model"
                           class="form-control @error('model') is-invalid @enderror"
                           value="{{ old('model', $medgemma->model ?? config('medgemma.model', 'medgemma')) }}"
                           required>
                    @error('model')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Ollama: <code>medgemma</code> · HF: <code>google/medgemma-4b-it</code></div>
                </div>

                {{-- API URL --}}
                <div class="col-md-6">
                    <label for="api_url" class="form-label fw-medium">API Base URL</label>
                    <input type="text"
                           id="api_url"
                           name="api_url"
                           class="form-control @error('api_url') is-invalid @enderror"
                           value="{{ old('api_url', $medgemma->api_url ?? config('medgemma.api_url', 'http://localhost:11434')) }}">
                    @error('api_url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Ollama: <code>http://localhost:11434</code> · HF: <code>https://router.huggingface.co/hf-inference/models/</code></div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i>Save Settings
                </button>
                <button type="button" class="btn btn-outline-success" id="test-btn"
                        {{ !$medgemma->isReady() ? 'disabled' : '' }}>
                    <i class="bi bi-lightning me-1" id="test-icon"></i>
                    <span id="test-label">Test Connection</span>
                </button>
            </div>
        </form>

        {{-- Information box about what MedGemma does --}}
        <div class="alert alert-info mt-4 mb-0" role="alert">
            <h6 class="alert-heading"><i class="bi bi-info-circle me-1"></i>About MedGemma AI</h6>
            <p class="mb-1 small">MedGemma analyses patient data — vitals, lab results, radiology reports and images — and provides a clinical second opinion to help doctors make informed decisions.</p>
            <ul class="mb-0 small">
                <li>Doctors can request AI analysis from a patient's consultation page</li>
                <li>Lab technicians can request analysis on completed lab invoices</li>
                <li>Radiologists can request analysis on imaging invoices</li>
            </ul>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    // Toggle API key visibility
    const keyInput   = document.getElementById('api_key');
    const toggleBtn  = document.getElementById('toggle-key');
    const toggleIcon = document.getElementById('toggle-icon');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const show = keyInput.type === 'password';
            keyInput.type        = show ? 'text' : 'password';
            toggleIcon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }

    // Test connection via AJAX
    const testBtn    = document.getElementById('test-btn');
    const testIcon   = document.getElementById('test-icon');
    const testLabel  = document.getElementById('test-label');
    const badge      = document.getElementById('status-badge');
    const statusIcon = document.getElementById('status-icon');
    const statusLbl  = document.getElementById('status-label');
    const errorAlert = document.getElementById('error-alert');
    const errorMsg   = document.getElementById('error-message');
    const lastTested = document.getElementById('last-tested-text');

    if (testBtn) {
        testBtn.addEventListener('click', function () {
            // Show spinner
            testBtn.disabled   = true;
            testIcon.className = 'bi bi-arrow-repeat spin me-1';
            testLabel.textContent = 'Testing…';

            // Update badge to "Connecting…"
            applyStatus('connecting');

            fetch('{{ route('owner.platform-settings.test') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                applyStatus(data.status);

                if (data.status === 'connected') {
                    errorAlert.classList.add('d-none');
                    if (lastTested) {
                        lastTested.innerHTML = '<i class="bi bi-clock me-1"></i>Last tested: just now';
                    }
                } else {
                    errorMsg.textContent = data.error || 'Unknown error.';
                    errorAlert.classList.remove('d-none');
                    if (lastTested) {
                        lastTested.innerHTML = '<i class="bi bi-clock me-1"></i>Last tested: just now';
                    }
                }

                testBtn.disabled      = false;
                testIcon.className    = 'bi bi-lightning me-1';
                testLabel.textContent = 'Test Connection';
            })
            .catch(function (err) {
                applyStatus('failed');
                errorMsg.textContent = err.message || 'Request failed.';
                errorAlert.classList.remove('d-none');
                testBtn.disabled      = false;
                testIcon.className    = 'bi bi-lightning me-1';
                testLabel.textContent = 'Test Connection';
            });
        });
    }

    function applyStatus(status) {
        const map = {
            connected:    { badge: 'bg-success',              icon: 'bi-check-circle-fill', label: 'Connected' },
            connecting:   { badge: 'bg-warning text-dark',    icon: 'bi-arrow-repeat spin', label: 'Connecting…' },
            failed:       { badge: 'bg-danger',               icon: 'bi-x-circle-fill',     label: 'Connection Failed' },
            disconnected: { badge: 'bg-secondary',            icon: 'bi-dash-circle',        label: 'Disconnected' },
        };
        const s = map[status] || map.disconnected;

        // Remove all bg-* classes
        badge.className = badge.className.replace(/\bbg-\S+\b/g, '').replace(/\btext-dark\b/g, '').trim();
        badge.classList.add(...s.badge.split(' '));
        statusIcon.className = 'bi ' + s.icon;
        statusLbl.textContent = s.label;
    }
})();
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
.spin { display: inline-block; animation: spin 1s linear infinite; }
</style>
@endpush
