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
                    <h5 class="mb-0 fw-semibold">MedGemma AI</h5>
                    <small class="text-muted">Medical AI analysis powered by Google's MedGemma model via Ollama or Hugging Face</small>
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
                    <label for="api_url" class="form-label fw-medium">
                        API Base URL
                        <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;vertical-align:middle;"
                              title="This URL is called by the web server (VPS), not your browser.">server-side</span>
                    </label>
                    <input type="text"
                           id="api_url"
                           name="api_url"
                           class="form-control @error('api_url') is-invalid @enderror"
                           value="{{ old('api_url', $medgemma->api_url ?? config('medgemma.api_url', 'http://localhost:11434')) }}">
                    @error('api_url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Ollama: <code>http://localhost:11434</code> (only if Ollama runs on this VPS)
                        &nbsp;·&nbsp; HF: <code>https://router.huggingface.co/hf-inference/models/</code>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i>Save Settings
                </button>
                <button type="button" class="btn btn-outline-success" id="test-btn"
                        {{ !$medgemma->isReady() ? 'disabled' : '' }}>
                    <i class="bi bi-lightning me-1" id="test-icon"></i>
                    <span id="test-label">Test Connection (Server)</span>
                </button>
                <button type="button" class="btn btn-outline-info" id="browser-test-btn"
                        {{ !$medgemma->isReady() ? 'disabled' : '' }}>
                    <i class="bi bi-browser me-1" id="browser-test-icon"></i>
                    <span id="browser-test-label">Test from Browser</span>
                </button>
            </div>

            {{-- Browser-side test result (shown below buttons) --}}
            <div id="browser-test-result" class="alert mt-3 d-none" role="alert"></div>
        </form>

        {{-- Deployment setup guide --}}
        <div class="accordion mt-4" id="ollamaSetupAccordion">
            <div class="accordion-item border-0 bg-transparent">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed px-0 bg-transparent fw-semibold text-primary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#ollamaSetupBody" aria-expanded="false">
                        <i class="bi bi-info-circle me-2"></i>How to connect Ollama to this application
                    </button>
                </h2>
                <div id="ollamaSetupBody" class="accordion-collapse collapse" data-bs-parent="#ollamaSetupAccordion">
                    <div class="accordion-body px-0 pt-2">
                        <div class="alert alert-warning mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Important:</strong> The <em>API Base URL</em> is called by <strong>this web server (VPS)</strong>,
                            not by your browser. Setting it to <code>localhost</code> only works if Ollama is installed and running
                            on the same VPS as this application.
                        </div>

                        <h6 class="fw-semibold mb-2"><i class="bi bi-1-circle me-1 text-primary"></i>Option A — Install Ollama directly on this VPS <span class="badge bg-success ms-1">Recommended</span></h6>
                        <p class="small mb-1">SSH into the VPS and run:</p>
                        <pre class="bg-dark text-light rounded p-2 small mb-3">curl -fsSL https://ollama.com/install.sh | sh
ollama serve &amp;
ollama pull medgemma</pre>
                        <p class="small text-muted mb-3">Then set the API Base URL to <code>http://localhost:11434</code> and save.</p>

                        <h6 class="fw-semibold mb-2"><i class="bi bi-2-circle me-1 text-primary"></i>Option B — Expose your local Ollama via a public tunnel</h6>
                        <p class="small mb-1">If Ollama is running on your local computer, use <strong>ngrok</strong> or <strong>Cloudflare Tunnel</strong> to expose it:</p>
                        <pre class="bg-dark text-light rounded p-2 small mb-1"># ngrok
ngrok http 11434</pre>
                        <pre class="bg-dark text-light rounded p-2 small mb-3"># Cloudflare Tunnel
cloudflared tunnel --url http://localhost:11434</pre>
                        <p class="small text-muted mb-3">Copy the generated HTTPS URL (e.g. <code>https://xxxx.ngrok-free.app</code>) and paste it into the <em>API Base URL</em> field above, then save and test.</p>

                        <h6 class="fw-semibold mb-2"><i class="bi bi-3-circle me-1 text-primary"></i>Option C — Use Hugging Face (cloud, no installation needed)</h6>
                        <p class="small mb-0">Switch the <em>Provider</em> to <strong>Hugging Face</strong>, enter your <code>hf_...</code> API key, set the model to <code>google/medgemma-4b-it</code>, and save.</p>
                    </div>
                </div>
            </div>
        </div>

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
                testLabel.textContent = 'Test Connection (Server)';
            })
            .catch(function (err) {
                applyStatus('failed');
                errorMsg.textContent = err.message || 'Request failed.';
                errorAlert.classList.remove('d-none');
                testBtn.disabled      = false;
                testIcon.className    = 'bi bi-lightning me-1';
                testLabel.textContent = 'Test Connection (Server)';
            });
        });
    }

    // ── Browser-side connection test ──────────────────────────────────────────
    // Sends a tiny request directly from the browser to the Ollama URL.
    // Because Ollama sets Access-Control-Allow-Origin: *, this works even when
    // Ollama is running on localhost on the user's own machine.
    const browserTestBtn    = document.getElementById('browser-test-btn');
    const browserTestIcon   = document.getElementById('browser-test-icon');
    const browserTestLabel  = document.getElementById('browser-test-label');
    const browserTestResult = document.getElementById('browser-test-result');

    if (browserTestBtn) {
        browserTestBtn.addEventListener('click', function () {
            const apiUrlInput = document.getElementById('api_url');
            const modelInput  = document.getElementById('model');
            const rawUrl  = (apiUrlInput  ? apiUrlInput.value  : '').trim() || 'http://localhost:11434';
            const model   = (modelInput   ? modelInput.value   : '').trim() || 'medgemma';
            const baseUrl = rawUrl.replace(/\/+$/, '');
            const endpoint = baseUrl + '/v1/chat/completions';

            const BROWSER_TEST_TIMEOUT_MS = 15000;

            const testPayload = {
                model: model,
                messages: [{ role: 'user', content: 'Hi' }],
                max_tokens: 1,
            };

            browserTestBtn.disabled      = true;
            browserTestIcon.className    = 'bi bi-arrow-repeat spin me-1';
            browserTestLabel.textContent = 'Testing from browser…';
            browserTestResult.className  = 'alert mt-3';
            browserTestResult.classList.remove('d-none');
            browserTestResult.innerHTML  = '<i class="bi bi-arrow-repeat spin me-1"></i>Sending request from your browser to <code>' + endpoint + '</code>…';

            const controller = new AbortController();
            const timeoutId  = setTimeout(function () { controller.abort(); }, BROWSER_TEST_TIMEOUT_MS);

            fetch(endpoint, {
                method: 'POST',
                signal: controller.signal,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(testPayload),
            })
            .then(function (res) {
                clearTimeout(timeoutId);
                if (res.ok) {
                    browserTestResult.className = 'alert alert-success mt-3';
                    browserTestResult.innerHTML = '<i class="bi bi-check-circle me-1"></i>'
                        + '<strong>Browser can reach Ollama at <code>' + endpoint + '</code>.</strong> '
                        + 'Ollama is accessible from your browser. If the server-side test still fails, '
                        + 'Ollama is not installed on the VPS — see the setup guide above.';
                } else {
                    return res.text().then(function (body) {
                        browserTestResult.className = 'alert alert-warning mt-3';
                        browserTestResult.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>'
                            + '<strong>Ollama replied with HTTP ' + res.status + '.</strong> '
                            + (body ? '<br><small class="text-muted">' + body.substring(0, 200) + '</small>' : '');
                    });
                }
            })
            .catch(function (err) {
                clearTimeout(timeoutId);
                const isAbort = err.name === 'AbortError';
                browserTestResult.className = 'alert alert-danger mt-3';
                if (isAbort) {
                    browserTestResult.innerHTML = '<i class="bi bi-x-circle me-1"></i>'
                        + '<strong>Request timed out.</strong> Your browser could not reach <code>' + endpoint + '</code> within 15 seconds.';
                } else {
                    browserTestResult.innerHTML = '<i class="bi bi-x-circle me-1"></i>'
                        + '<strong>Your browser cannot reach Ollama at <code>' + endpoint + '</code>.</strong><br>'
                        + '<small class="text-muted">Error: ' + (err.message || 'Network error') + '</small><br>'
                        + '<small>If you are using a tunnel (ngrok / Cloudflare), ensure it is still running and the URL is correct.</small>';
                }
            })
            .finally(function () {
                browserTestBtn.disabled      = false;
                browserTestIcon.className    = 'bi bi-browser me-1';
                browserTestLabel.textContent = 'Test from Browser';
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
