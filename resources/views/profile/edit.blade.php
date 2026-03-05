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
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-robot me-2" style="color:var(--accent-primary);"></i>MedGemma AI — API Configuration</span>
            <span id="medgemma-status-badge">
                @php
                    $storedKey = \App\Models\Setting::get('medgemma.api_key');
                    $effectiveKey = $storedKey ?: config('medgemma.api_key');
                @endphp
                @if($effectiveKey)
                    <span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>API Key Configured</span>
                @else
                    <span class="badge bg-danger"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>Not Configured</span>
                @endif
            </span>
        </div>
        <div class="card-body">
            <p class="small mb-3" style="color:var(--text-muted);">
                MedGemma is powered by the <strong>Hugging Face Inference API</strong>.
                Enter your API key below to enable AI-assisted medical analysis across the clinic.
                Get a free key at <a href="https://huggingface.co/settings/tokens" target="_blank" rel="noopener">huggingface.co/settings/tokens</a>.
            </p>

            @if(session('status') === 'medgemma-saved')
                <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i>MedGemma settings saved successfully.</div>
            @endif

            {{-- Live connection status panel --}}
            <div id="medgemma-test-result" class="alert py-2 mb-3 d-none"></div>

            <form method="post" action="{{ route('owner.medgemma-config.update') }}">
                @csrf

                <div class="mb-3">
                    <label for="medgemma_api_key" class="form-label fw-semibold">Hugging Face API Key</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password"
                               id="medgemma_api_key"
                               name="medgemma_api_key"
                               class="form-control @error('medgemma_api_key') is-invalid @enderror"
                               value="{{ old('medgemma_api_key', \App\Models\Setting::get('medgemma.api_key', '')) }}"
                               placeholder="hf_••••••••••••••••••••••••••••••••"
                               autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" id="toggle-api-key" title="Show / hide key">
                            <i class="bi bi-eye" id="toggle-api-key-icon"></i>
                        </button>
                    </div>
                    @error('medgemma_api_key')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Paste your <code>hf_…</code> token from Hugging Face.</div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="medgemma_model" class="form-label fw-semibold">Model</label>
                        <input type="text"
                               id="medgemma_model"
                               name="medgemma_model"
                               class="form-control @error('medgemma_model') is-invalid @enderror"
                               value="{{ old('medgemma_model', \App\Models\Setting::get('medgemma.model', config('medgemma.model'))) }}"
                               placeholder="{{ config('medgemma.model') }}">
                        @error('medgemma_model')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Default: <code>{{ config('medgemma.model') }}</code></div>
                    </div>
                    <div class="col-md-6">
                        <label for="medgemma_api_url" class="form-label fw-semibold">API Base URL</label>
                        <input type="text"
                               id="medgemma_api_url"
                               name="medgemma_api_url"
                               class="form-control @error('medgemma_api_url') is-invalid @enderror"
                               value="{{ old('medgemma_api_url', \App\Models\Setting::get('medgemma.api_url', config('medgemma.api_url'))) }}"
                               placeholder="{{ config('medgemma.api_url') }}">
                        @error('medgemma_api_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Leave unchanged unless you use a custom proxy.</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Save Settings
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="test-medgemma-btn">
                        <i class="bi bi-plug me-1"></i>Test Connection
                    </button>
                    <span id="test-spinner" class="spinner-border spinner-border-sm text-secondary d-none" role="status"></span>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="card mb-4 fade-in delay-4">
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

            fetch('{{ route('owner.medgemma-config.test') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    api_key: keyInput ? keyInput.value : '',
                    model: document.getElementById('medgemma_model') ? document.getElementById('medgemma_model').value : '',
                    api_url: document.getElementById('medgemma_api_url') ? document.getElementById('medgemma_api_url').value : '',
                }),
            })
            .then(r => r.json())
            .then(data => {
                resultDiv.className = 'alert py-2 mb-3 ' + (data.connected ? 'alert-success' : 'alert-warning');
                resultDiv.innerHTML = '<i class="bi bi-' + (data.connected ? 'check-circle' : 'exclamation-triangle') + ' me-2"></i>' + data.message;
                resultDiv.classList.remove('d-none');

                if (statusBadge) {
                    statusBadge.innerHTML = data.connected
                        ? '<span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>Connected</span>'
                        : '<span class="badge bg-warning text-dark"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>Not Connected</span>';
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
