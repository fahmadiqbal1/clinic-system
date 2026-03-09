@extends('layouts.app')
@section('title', 'Profile — ' . config('app.name'))

@section('content')
<div class="container mt-4" style="max-width:800px;">
    {{-- Profile Header --}}
    <div class="page-header mb-4">
        <div class="d-flex align-items-center gap-3 mb-2">
            <div class="stat-icon stat-icon-primary" style="width:56px;height:56px;font-size:1.4rem;">
                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
            </div>
            <div>
                <h2 class="mb-0">{{ auth()->user()->name }}</h2>
                <p class="page-subtitle mb-0">
                    <span class="badge bg-primary me-1">{{ ucfirst(auth()->user()->getRoleNames()->first() ?? 'User') }}</span>
                    {{ auth()->user()->email }}
                </p>
            </div>
        </div>
    </div>

    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-person me-2" style="color:var(--accent-primary);"></i>Profile Information</div>
        <div class="card-body">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-shield-lock me-2" style="color:var(--accent-warning);"></i>Update Password</div>
        <div class="card-body">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    @if(auth()->user()->hasRole('Owner'))
    @php
        $medgemma = \App\Models\PlatformSetting::medgemma();
        $fbr = \App\Models\PlatformSetting::fbr();
    @endphp
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-robot me-2" style="color:var(--accent-primary);"></i>MedGemma AI — API Configuration</span>
            <span id="medgemma-status-badge">
                @if($medgemma->isReady())
                    <span class="badge {{ $medgemma->statusBadgeClass() }}"><i class="bi {{ $medgemma->statusIcon() }} me-1" style="font-size:.5rem;vertical-align:middle;"></i>{{ $medgemma->statusLabel() }}</span>
                @else
                    <span class="badge bg-danger"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>Not Configured</span>
                @endif
            </span>
        </div>
        <div class="card-body">
            <p class="small mb-3" style="color:var(--text-muted);">
                Configure <strong>MedGemma</strong> to enable AI-assisted medical analysis.
                Choose <strong>Ollama</strong> (local, free — default) or <strong>Hugging Face</strong> (cloud, requires API key).
            </p>

            @if(session('success'))
                <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
            @endif

            {{-- Live connection status panel --}}
            <div id="medgemma-test-result" class="alert py-2 mb-3 d-none"></div>

            <form method="post" action="{{ route('owner.platform-settings.update') }}">
                @csrf
                @method('PATCH')

                {{-- Provider selector --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Provider</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="provider" id="provider_huggingface" value="huggingface"
                                   {{ old('provider', $medgemma->provider ?? config('medgemma.provider', 'ollama')) === 'huggingface' ? 'checked' : '' }}>
                            <label class="form-check-label" for="provider_huggingface">
                                <i class="bi bi-cloud me-1"></i>Hugging Face <span class="text-muted small">(cloud API)</span>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="provider" id="provider_ollama" value="ollama"
                                   {{ old('provider', $medgemma->provider ?? config('medgemma.provider', 'ollama')) === 'ollama' ? 'checked' : '' }}>
                            <label class="form-check-label" for="provider_ollama">
                                <i class="bi bi-pc-display me-1"></i>Ollama <span class="text-muted small">(local, free)</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- HuggingFace API Key (hidden when Ollama is selected) --}}
                <div class="mb-3" id="hf-api-key-group">
                    <label for="medgemma_api_key" class="form-label fw-semibold">Hugging Face API Key</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password"
                               id="medgemma_api_key"
                               name="api_key"
                               class="form-control @error('api_key') is-invalid @enderror"
                               placeholder="{{ $medgemma->hasApiKey() ? '••••••••••••••••••••  (key saved — enter new value to change)' : 'hf_...' }}"
                               autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" id="toggle-api-key" title="Show / hide key">
                            <i class="bi bi-eye" id="toggle-api-key-icon"></i>
                        </button>
                    </div>
                    @error('api_key')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @if($medgemma->hasApiKey())
                        <div class="form-text text-success">
                            <i class="bi bi-check-circle me-1"></i>An API key is currently saved. Leave blank to keep the existing key.
                        </div>
                    @else
                        <div class="form-text">
                            Get a key at <a href="https://huggingface.co/settings/tokens" target="_blank" rel="noopener">huggingface.co/settings/tokens</a>.
                            Requires HF Pro subscription for <code>google/medgemma-4b-it</code>.
                        </div>
                    @endif
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="medgemma_model" class="form-label fw-semibold">Model</label>
                        <input type="text"
                               id="medgemma_model"
                               name="model"
                               class="form-control @error('model') is-invalid @enderror"
                               value="{{ old('model', $medgemma->model ?? config('medgemma.model', 'medgemma')) }}"
                               placeholder="{{ config('medgemma.model', 'medgemma') }}">
                        @error('model')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="model-help-text">
                            Ollama: <code>medgemma</code> · HF: <code>google/medgemma-4b-it</code>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="medgemma_api_url" class="form-label fw-semibold">
                            API Base URL
                            <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem;vertical-align:middle;"
                                  title="Must be reachable from the web server (VPS), not your browser.">server-side</span>
                        </label>
                        <input type="text"
                               id="medgemma_api_url"
                               name="api_url"
                               class="form-control @error('api_url') is-invalid @enderror"
                               value="{{ old('api_url', $medgemma->api_url ?? config('medgemma.api_url', 'http://localhost:11434')) }}"
                               placeholder="{{ config('medgemma.api_url', 'http://localhost:11434') }}">
                        @error('api_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="url-help-text">
                            Ollama on VPS: <code>http://localhost:11434</code><br>
                            Tunnel/ngrok: <code>https://xxxx.ngrok-free.app</code><br>
                            HF: <code>https://router.huggingface.co/hf-inference/models/</code>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Save Settings
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="test-medgemma-btn"
                            {{ !$medgemma->isReady() ? 'disabled' : '' }}>
                        <i class="bi bi-plug me-1"></i>Test Connection
                    </button>
                    <span id="test-spinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status"></span>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- FBR IRIS Digital Invoicing --}}
    @if(auth()->user()->hasRole('Owner'))
    <div class="card mb-4 fade-in delay-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-receipt-cutoff me-2" style="color:var(--accent-success);"></i>FBR IRIS — Digital Invoicing (Pakistan)</span>
            <span id="fbr-status-badge">
                @if($fbr->isFbrReady())
                    <span class="badge {{ $fbr->statusBadgeClass() }}">
                        <i class="bi {{ $fbr->statusIcon() }} me-1" style="font-size:.5rem;vertical-align:middle;"></i>{{ $fbr->statusLabel() }}
                    </span>
                @else
                    <span class="badge bg-secondary">
                        <i class="bi bi-dash-circle me-1" style="font-size:.5rem;vertical-align:middle;"></i>Not Configured
                    </span>
                @endif
            </span>
        </div>
        <div class="card-body">
            <p class="small mb-3" style="color:var(--text-muted);">
                Configure <strong>FBR IRIS</strong> integration to enable mandatory digital invoicing under Pakistan's POS law.
                Every paid invoice will be auto-submitted to FBR in real-time with an <strong>IRN</strong> and scannable <strong>QR code</strong>.
            </p>

            @if(session('success') && str_contains(session('success'), 'FBR'))
                <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
            @endif

            <div id="fbr-test-result" class="alert py-2 mb-3 d-none"></div>

            <form method="post" action="{{ route('owner.fbr-settings.update') }}">
                @csrf
                @method('PATCH')

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="fbr_strn" class="form-label fw-semibold">STRN <span class="text-danger">*</span></label>
                        <input type="text" id="fbr_strn" name="fbr_strn"
                               class="form-control @error('fbr_strn') is-invalid @enderror"
                               value="{{ old('fbr_strn', $fbr->getMeta('strn')) }}"
                               placeholder="e.g. 1234567890123">
                        @error('fbr_strn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Sales Tax Registration Number assigned by FBR.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="fbr_ntn" class="form-label fw-semibold">NTN</label>
                        <input type="text" id="fbr_ntn" name="fbr_ntn"
                               class="form-control @error('fbr_ntn') is-invalid @enderror"
                               value="{{ old('fbr_ntn', $fbr->getMeta('ntn')) }}"
                               placeholder="e.g. 1234567-8">
                        @error('fbr_ntn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">National Tax Number.</div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="fbr_posid" class="form-label fw-semibold">POSID <span class="text-danger">*</span></label>
                        <input type="text" id="fbr_posid" name="fbr_posid"
                               class="form-control @error('fbr_posid') is-invalid @enderror"
                               value="{{ old('fbr_posid', $fbr->getMeta('posid')) }}"
                               placeholder="e.g. 1234567890">
                        @error('fbr_posid')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Point-of-Sale ID assigned by FBR to your terminal.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="fbr_tax_rate" class="form-label fw-semibold">GST Rate (%)</label>
                        <input type="number" id="fbr_tax_rate" name="fbr_tax_rate"
                               step="0.01" min="0" max="100"
                               class="form-control @error('fbr_tax_rate') is-invalid @enderror"
                               value="{{ old('fbr_tax_rate', $fbr->getMeta('tax_rate', 0)) }}"
                               placeholder="0 (exempt) or 17">
                        @error('fbr_tax_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">GST percentage to apply to invoices. Set 0 if your services are tax-exempt.</div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="fbr_business_name" class="form-label fw-semibold">Business Name</label>
                        <input type="text" id="fbr_business_name" name="fbr_business_name"
                               class="form-control"
                               value="{{ old('fbr_business_name', $fbr->getMeta('business_name')) }}"
                               placeholder="{{ config('app.name') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="fbr_city" class="form-label fw-semibold">City</label>
                        <input type="text" id="fbr_city" name="fbr_city"
                               class="form-control"
                               value="{{ old('fbr_city', $fbr->getMeta('city')) }}"
                               placeholder="e.g. Karachi">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="fbr_business_address" class="form-label fw-semibold">Business Address</label>
                    <input type="text" id="fbr_business_address" name="fbr_business_address"
                           class="form-control"
                           value="{{ old('fbr_business_address', $fbr->getMeta('business_address')) }}"
                           placeholder="Full registered address">
                </div>

                <div class="mb-3">
                    <label for="fbr_bearer_token" class="form-label fw-semibold">FBR Bearer Token <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password" id="fbr_bearer_token" name="fbr_bearer_token"
                               class="form-control @error('fbr_bearer_token') is-invalid @enderror"
                               placeholder="{{ $fbr->hasApiKey() ? '••••••••••••••••••••  (token saved — enter new value to change)' : 'Bearer token from FBR IRIS portal' }}"
                               autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" id="toggle-fbr-token" title="Show / hide token">
                            <i class="bi bi-eye" id="toggle-fbr-token-icon"></i>
                        </button>
                    </div>
                    @error('fbr_bearer_token')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @if($fbr->hasApiKey())
                        <div class="form-text text-success">
                            <i class="bi bi-check-circle me-1"></i>A token is saved. Leave blank to keep it.
                        </div>
                    @else
                        <div class="form-text">
                            Obtain your token from the <a href="https://iris.fbr.gov.pk" target="_blank" rel="noopener">FBR IRIS portal</a>.
                        </div>
                    @endif
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="fbr_api_url" class="form-label fw-semibold">API Endpoint</label>
                        <input type="url" id="fbr_api_url" name="fbr_api_url"
                               class="form-control @error('fbr_api_url') is-invalid @enderror"
                               value="{{ old('fbr_api_url', $fbr->api_url) }}"
                               placeholder="https://gst.fbr.gov.pk/invoices/v1">
                        @error('fbr_api_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="fbr_is_sandbox" name="fbr_is_sandbox"
                                   value="1" {{ $fbr->getMeta('is_sandbox', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="fbr_is_sandbox">
                                Use Sandbox / Test Environment
                                <span class="text-muted small">(disable for live FBR submissions)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-floppy me-1"></i>Save FBR Settings
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="test-fbr-btn"
                            {{ !$fbr->isFbrReady() ? 'disabled' : '' }}>
                        <i class="bi bi-plug me-1"></i>Test FBR Connection
                    </button>
                    <span id="fbr-spinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status"></span>
                    @if($fbr->last_tested_at)
                        <small class="text-muted ms-2">Last tested: {{ $fbr->last_tested_at->diffForHumans() }}</small>
                    @endif
                </div>
            </form>

            @if($fbr->last_error)
                <div class="alert alert-warning mt-3 py-2">
                    <i class="bi bi-exclamation-triangle me-2"></i><strong>Last error:</strong> {{ $fbr->last_error }}
                </div>
            @endif
        </div>
    </div>
    @endif

    <div class="card mb-4 fade-in delay-{{ auth()->user()->hasRole('Owner') ? '5' : '3' }}">
        <div class="card-header"><i class="bi bi-trash3 me-2" style="color:var(--accent-danger);"></i>Delete Account</div>
        <div class="card-body">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Provider toggle — show/hide API key field and update defaults
    const hfRadio = document.getElementById('provider_huggingface');
    const ollamaRadio = document.getElementById('provider_ollama');
    const apiKeyGroup = document.getElementById('hf-api-key-group');
    const modelInput = document.getElementById('medgemma_model');
    const urlInput = document.getElementById('medgemma_api_url');

    function updateProviderUI() {
        if (!hfRadio) return;
        const isOllama = ollamaRadio && ollamaRadio.checked;

        if (apiKeyGroup) {
            apiKeyGroup.style.display = isOllama ? 'none' : '';
        }

        // Update placeholders based on provider
        if (modelInput) {
            modelInput.placeholder = isOllama ? 'medgemma' : 'google/medgemma-4b-it';
        }
        if (urlInput) {
            urlInput.placeholder = isOllama ? 'http://localhost:11434 (if Ollama runs on VPS)' : 'https://router.huggingface.co/hf-inference/models/';
        }
    }

    if (hfRadio) hfRadio.addEventListener('change', updateProviderUI);
    if (ollamaRadio) ollamaRadio.addEventListener('change', updateProviderUI);
    updateProviderUI();

    // Toggle API key visibility
    const keyInput = document.getElementById('medgemma_api_key');
    const toggleBtn = document.getElementById('toggle-api-key');
    const toggleIcon = document.getElementById('toggle-api-key-icon');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            if (keyInput.type === 'password') {
                keyInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                keyInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        });
    }

    // Test connection
    const testBtn = document.getElementById('test-medgemma-btn');
    const spinner = document.getElementById('test-spinner');
    const resultDiv = document.getElementById('medgemma-test-result');
    const statusBadge = document.getElementById('medgemma-status-badge');

    if (testBtn) {
        testBtn.addEventListener('click', function () {
            testBtn.disabled = true;
            spinner.classList.remove('d-none');
            resultDiv.classList.add('d-none');

            fetch('{{ route("owner.platform-settings.test") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
            .then(r => r.json())
            .then(data => {
                const connected = data.status === 'connected';
                resultDiv.className = 'alert py-2 mb-3 ' + (connected ? 'alert-success' : 'alert-warning');
                resultDiv.innerHTML = '<i class="bi bi-' + (connected ? 'check-circle' : 'exclamation-triangle') + ' me-2"></i>' + (connected ? 'Connected to MedGemma API successfully.' : (data.error || 'Connection test failed.'));
                resultDiv.classList.remove('d-none');

                if (statusBadge) {
                    statusBadge.innerHTML = connected
                        ? '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>Connected</span>'
                        : '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1" style="font-size:.5rem;vertical-align:middle;"></i>Connection Failed</span>';
                }
            })
            .catch(() => {
                resultDiv.className = 'alert alert-danger py-2 mb-3';
                resultDiv.innerHTML = '<i class="bi bi-x-circle me-2"></i>Request failed. Check your network connection.';
                resultDiv.classList.remove('d-none');
            })
            .finally(() => {
                testBtn.disabled = false;
                spinner.classList.add('d-none');
            });
        });
    }
    // FBR token visibility toggle
    const fbrTokenInput = document.getElementById('fbr_bearer_token');
    const fbrToggleBtn  = document.getElementById('toggle-fbr-token');
    const fbrToggleIcon = document.getElementById('toggle-fbr-token-icon');
    if (fbrToggleBtn) {
        fbrToggleBtn.addEventListener('click', function () {
            if (fbrTokenInput.type === 'password') {
                fbrTokenInput.type = 'text';
                fbrToggleIcon.className = 'bi bi-eye-slash';
            } else {
                fbrTokenInput.type = 'password';
                fbrToggleIcon.className = 'bi bi-eye';
            }
        });
    }

    // FBR sandbox toggle — auto-update API endpoint
    const fbrSandboxCheck = document.getElementById('fbr_is_sandbox');
    const fbrApiUrlInput  = document.getElementById('fbr_api_url');
    if (fbrSandboxCheck && fbrApiUrlInput) {
        fbrSandboxCheck.addEventListener('change', function () {
            const sandboxUrl = 'https://sdnfbr.fbr.gov.pk/invoices/v1';
            const liveUrl    = 'https://gst.fbr.gov.pk/invoices/v1';
            if (fbrApiUrlInput.value === sandboxUrl || fbrApiUrlInput.value === liveUrl || fbrApiUrlInput.value === '') {
                fbrApiUrlInput.value = this.checked ? sandboxUrl : liveUrl;
            }
        });
    }

    // FBR test connection
    const fbrTestBtn    = document.getElementById('test-fbr-btn');
    const fbrSpinner    = document.getElementById('fbr-spinner');
    const fbrResultDiv  = document.getElementById('fbr-test-result');
    const fbrStatusBadge = document.getElementById('fbr-status-badge');

    if (fbrTestBtn) {
        fbrTestBtn.addEventListener('click', function () {
            fbrTestBtn.disabled = true;
            fbrSpinner.classList.remove('d-none');
            fbrResultDiv.classList.add('d-none');

            fetch('{{ route("owner.fbr-settings.test") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
            .then(r => r.json())
            .then(data => {
                const connected = data.status === 'connected';
                fbrResultDiv.className = 'alert py-2 mb-3 ' + (connected ? 'alert-success' : 'alert-warning');
                fbrResultDiv.innerHTML = '<i class="bi bi-' + (connected ? 'check-circle' : 'exclamation-triangle') + ' me-2"></i>'
                    + (connected ? 'FBR IRIS connection successful.' : (data.error || 'Connection test failed.'));
                fbrResultDiv.classList.remove('d-none');

                if (fbrStatusBadge) {
                    fbrStatusBadge.innerHTML = connected
                        ? '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>Connected</span>'
                        : '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1" style="font-size:.5rem;vertical-align:middle;"></i>Connection Failed</span>';
                }
            })
            .catch(() => {
                fbrResultDiv.className = 'alert alert-danger py-2 mb-3';
                fbrResultDiv.innerHTML = '<i class="bi bi-x-circle me-2"></i>Request failed. Check your network connection.';
                fbrResultDiv.classList.remove('d-none');
            })
            .finally(() => {
                fbrTestBtn.disabled = false;
                fbrSpinner.classList.add('d-none');
            });
        });
    }
})();
</script>
@endpush
