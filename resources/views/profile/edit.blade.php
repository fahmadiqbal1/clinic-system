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
                Configure <strong>MedGemma</strong> (<code>google/medgemma-4b-it</code>) to enable AI-assisted medical analysis.
                Choose <strong>Hugging Face</strong> (cloud, requires API key) or <strong>Ollama</strong> (local, free).
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
                                   {{ old('provider', $medgemma->provider ?? 'huggingface') === 'huggingface' ? 'checked' : '' }}>
                            <label class="form-check-label" for="provider_huggingface">
                                <i class="bi bi-cloud me-1"></i>Hugging Face <span class="text-muted small">(cloud API)</span>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="provider" id="provider_ollama" value="ollama"
                                   {{ old('provider', $medgemma->provider ?? 'huggingface') === 'ollama' ? 'checked' : '' }}>
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
                               value="{{ old('model', $medgemma->model ?? config('medgemma.model', 'google/medgemma-4b-it')) }}"
                               placeholder="{{ config('medgemma.model', 'google/medgemma-4b-it') }}">
                        @error('model')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="model-help-text">
                            HF: <code>google/medgemma-4b-it</code> · Ollama: <code>alibayram/medgemma:4b</code>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="medgemma_api_url" class="form-label fw-semibold">API Base URL</label>
                        <input type="text"
                               id="medgemma_api_url"
                               name="api_url"
                               class="form-control @error('api_url') is-invalid @enderror"
                               value="{{ old('api_url', $medgemma->api_url ?? config('medgemma.api_url', 'https://router.huggingface.co/hf-inference/models/')) }}"
                               placeholder="{{ config('medgemma.api_url', 'https://router.huggingface.co/hf-inference/models/') }}">
                        @error('api_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="url-help-text">
                            HF default: <code>https://router.huggingface.co/hf-inference/models/</code> · Ollama: <code>http://localhost:11434</code>
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

    <div class="card mb-4 fade-in delay-{{ auth()->user()->hasRole('Owner') ? '4' : '3' }}">
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
            modelInput.placeholder = isOllama ? 'alibayram/medgemma:4b' : 'google/medgemma-4b-it';
        }
        if (urlInput) {
            urlInput.placeholder = isOllama ? 'http://localhost:11434' : 'https://router.huggingface.co/hf-inference/models/';
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
})();
</script>
@endpush
