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

    {{-- Connection Status Bar --}}
    <div class="glass-panel p-3 mb-4" id="connectionStatusPanel">
        <div class="d-flex align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-robot text-primary fs-5"></i>
                <span class="fw-semibold">Clinical AI Status</span>
            </div>
            <span id="status-badge"
                  class="badge {{ $medgemma->statusBadgeClass() }} d-flex align-items-center gap-1 px-3 py-2">
                <i id="status-icon" class="bi {{ $medgemma->statusIcon() }}"></i>
                <span id="status-label">{{ $medgemma->statusLabel() }}</span>
            </span>
            <span class="text-muted small" id="last-tested-text">
                @if($medgemma->last_tested_at)
                    <i class="bi bi-clock me-1"></i>Last tested {{ $medgemma->last_tested_at->diffForHumans() }}
                    @if($medgemma->status === 'connected' && $medgemma->last_tested_at->lt(now()->subHour()))
                        <span class="badge bg-warning text-dark ms-1" title="Status may be stale — click Test Connection to verify">Unverified</span>
                    @endif
                @else
                    <i class="bi bi-clock me-1"></i>Never tested
                @endif
            </span>
            <div class="ms-auto d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" id="test-provider-btn">
                    <i class="bi bi-lightning me-1" id="test-provider-icon"></i>
                    <span id="test-provider-label">Test Connection</span>
                </button>
            </div>
        </div>
        <div id="error-alert" class="{{ $medgemma->status === 'failed' ? '' : 'd-none' }} alert alert-danger alert-dismissible mt-2 mb-0 py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <span id="error-message">{{ $medgemma->last_error }}</span>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
    </div>

    {{-- AI Model Provider --}}
    @php
        $mc = \App\Models\PlatformSetting::where('provider', 'model_config')
            ->pluck('meta', 'platform_name')
            ->map(fn($m) => is_array($m) ? ($m['value'] ?? '') : $m);
        $currentProvider = $mc['ai.model.provider'] ?? 'ollama';
    @endphp
    <div class="glass-panel p-4 mb-4" id="modelProviderPanel">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-3 p-2 bg-info bg-opacity-10">
                <i class="bi bi-cloud-arrow-up fs-4 text-info"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-semibold">AI Model Provider</h5>
                <small class="text-muted">Pick any provider, enter the model name and API key — active immediately, no restart needed</small>
            </div>
            <div class="ms-auto">
                <span class="badge bg-info px-3 py-2" id="activeProviderBadge">
                    {{ ['ollama'=>'Offline (Ollama)','openai'=>'OpenAI','anthropic'=>'Anthropic','huggingface'=>'Hugging Face','groq'=>'Groq (Free)'][$currentProvider] ?? $currentProvider }}
                </span>
            </div>
        </div>

        {{-- Provider tabs --}}
        <ul class="nav nav-pills gap-1 mb-3" id="providerTabs">
            @foreach([
                'ollama'       => ['icon'=>'bi-pc-display',        'label'=>'Ollama (Local)',      'col'=>'secondary'],
                'openai'       => ['icon'=>'bi-stars',             'label'=>'OpenAI',              'col'=>'primary'],
                'anthropic'    => ['icon'=>'bi-lightning-charge',  'label'=>'Anthropic',           'col'=>'success'],
                'huggingface'  => ['icon'=>'bi-boxes',             'label'=>'Hugging Face',        'col'=>'warning'],
                'groq'         => ['icon'=>'bi-speedometer2',       'label'=>'Groq (Free)',         'col'=>'info'],
            ] as $pVal => $pCfg)
            <li class="nav-item">
                <button class="nav-link provider-tab {{ $currentProvider === $pVal ? 'active' : '' }}"
                        data-provider="{{ $pVal }}" type="button">
                    <i class="bi {{ $pCfg['icon'] }} me-1"></i>{{ $pCfg['label'] }}
                </button>
            </li>
            @endforeach
        </ul>

        {{-- ── Ollama ── --}}
        <div class="provider-pane" id="pane-ollama" style="{{ $currentProvider !== 'ollama' ? 'display:none' : '' }}">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Ollama Base URL</label>
                    <input type="text" id="ollama_url" class="form-control form-control-sm"
                           placeholder="http://127.0.0.1:8081"
                           value="{{ $mc['ai.model.ollama.url'] ?? 'http://127.0.0.1:8081' }}">
                    <div class="form-text">URL of your running Ollama instance</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Model Name</label>
                    <input type="text" id="ollama_model" class="form-control form-control-sm"
                           placeholder="e.g. llama3.2:3b, mistral, phi3"
                           value="{{ $mc['ai.model.ollama.model'] ?? '' }}">
                    <div class="form-text">Exact name as shown in <code>ollama list</code></div>
                </div>
                <div class="col-12">
                    <div class="alert alert-secondary py-2 mb-0 small">
                        <i class="bi bi-info-circle me-1"></i>
                        No API key needed. Ollama must be running on this machine.
                        Pull a model with: <code>ollama pull llama3.2:3b</code>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── OpenAI ── --}}
        <div class="provider-pane" id="pane-openai" style="{{ $currentProvider !== 'openai' ? 'display:none' : '' }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Model Name <span class="text-danger">*</span></label>
                    <input type="text" id="openai_model" class="form-control form-control-sm"
                           placeholder="e.g. gpt-4o, gpt-4o-mini, gpt-3.5-turbo"
                           value="{{ $mc['ai.model.openai.model'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">API Key <span class="text-danger">*</span></label>
                    <input type="password" id="openai_key" class="form-control form-control-sm"
                           placeholder="sk-...  (leave blank to keep saved key)">
                    @if(!empty($mc['ai.model.openai.key']))
                        <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Key saved</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Base URL <span class="text-muted">(optional)</span></label>
                    <input type="text" id="openai_base_url" class="form-control form-control-sm"
                           placeholder="https://api.openai.com/v1"
                           value="{{ $mc['ai.model.openai.base_url'] ?? '' }}">
                    <div class="form-text">Override for Azure OpenAI or compatible endpoints</div>
                </div>
            </div>
        </div>

        {{-- ── Anthropic ── --}}
        <div class="provider-pane" id="pane-anthropic" style="{{ $currentProvider !== 'anthropic' ? 'display:none' : '' }}">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Model Name <span class="text-danger">*</span></label>
                    <input type="text" id="anthropic_model" class="form-control form-control-sm"
                           placeholder="e.g. claude-haiku-4-5-20251001, claude-sonnet-4-6"
                           value="{{ $mc['ai.model.anthropic.model'] ?? '' }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">API Key <span class="text-danger">*</span></label>
                    <input type="password" id="anthropic_key" class="form-control form-control-sm"
                           placeholder="sk-ant-...  (leave blank to keep saved key)">
                    @if(!empty($mc['ai.model.anthropic.key']))
                        <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Key saved</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Hugging Face ── --}}
        <div class="provider-pane" id="pane-huggingface" style="{{ $currentProvider !== 'huggingface' ? 'display:none' : '' }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Model ID <span class="text-danger">*</span></label>
                    <input type="text" id="hf_model" class="form-control form-control-sm"
                           placeholder="e.g. HuggingFaceH4/zephyr-7b-beta"
                           value="{{ $mc['ai.model.hf.model'] ?? '' }}">
                    <div class="form-text">Full <code>org/model-name</code> from Hugging Face</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">API Key <span class="text-danger">*</span></label>
                    <input type="password" id="hf_key" class="form-control form-control-sm"
                           placeholder="hf_...  (leave blank to keep saved key)">
                    @if(!empty($mc['ai.model.hf.key']))
                        <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Key saved</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Inference URL <span class="text-muted">(optional)</span></label>
                    <input type="text" id="hf_base_url" class="form-control form-control-sm"
                           placeholder="https://api-inference.huggingface.co/v1"
                           value="{{ $mc['ai.model.hf.base_url'] ?? '' }}">
                    <div class="form-text">Change only if using a private HF Endpoint</div>
                </div>
            </div>
        </div>

        {{-- ── Groq ── --}}
        <div class="provider-pane" id="pane-groq" style="{{ $currentProvider !== 'groq' ? 'display:none' : '' }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Model ID <span class="text-danger">*</span></label>
                    <input type="text" id="groq_model" class="form-control form-control-sm"
                           placeholder="e.g. llama-3.1-8b-instant"
                           value="{{ $mc['ai.model.groq.model'] ?? '' }}">
                    <div class="form-text">Free models: <code>llama-3.1-8b-instant</code>, <code>mixtral-8x7b-32768</code>, <code>llama-3.3-70b-versatile</code></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">API Key <span class="text-danger">*</span></label>
                    <input type="password" id="groq_key" class="form-control form-control-sm"
                           placeholder="gsk_...  (leave blank to keep saved key)">
                    @if(!empty($mc['ai.model.groq.key']))
                        <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Key saved</div>
                    @endif
                </div>
            </div>
            <div class="alert alert-info py-2 mb-0 small mt-3">
                <i class="bi bi-info-circle me-1"></i>
                Get a free API key at <strong>console.groq.com</strong>. No model gating — all listed models are immediately accessible on the free tier.
            </div>
        </div>

        {{-- Save row --}}
        <div class="d-flex align-items-center gap-3 mt-4 pt-3 border-top">
            <button class="btn btn-primary" id="saveModelConfig">
                <i class="bi bi-check-circle me-1"></i>Save & Apply
            </button>
            <span class="small text-muted" id="modelConfigStatus"></span>
        </div>
        <p class="text-muted small mt-2 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            After saving, click <strong>Test Connection</strong> at the top to verify the provider is reachable.
        </p>
    </div>

    {{-- Feature Flags --}}
    <div class="glass-panel p-4 mb-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-3 p-2 bg-warning bg-opacity-10">
                <i class="bi bi-toggles fs-4 text-warning"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-semibold">Feature Flags</h5>
                <small class="text-muted">Toggle AI and admin features on or off — changes take effect immediately</small>
            </div>
        </div>

        @php
            $flagGroups = [
                'AI Personas' => [
                    'ai.admin.enabled'      => ['label' => 'Administrative AI',  'desc' => 'Owner — /owner/admin-ai',      'icon' => 'bi-person-gear'],
                    'ai.ops.enabled'        => ['label' => 'Operations AI',      'desc' => 'Owner — /owner/ops-ai',        'icon' => 'bi-boxes'],
                    'ai.compliance.enabled' => ['label' => 'Compliance AI',      'desc' => 'Owner — /owner/compliance-ai', 'icon' => 'bi-shield-check'],
                ],
                'AI Infrastructure' => [
                    'ai.sidecar.enabled'    => ['label' => 'AI Sidecar',         'desc' => 'Routes consultations through Python sidecar', 'icon' => 'bi-cpu'],
                    'ai.ragflow.enabled'    => ['label' => 'RAGFlow',            'desc' => 'Knowledge retrieval for AI queries',          'icon' => 'bi-database'],
                    'ai.gitnexus.enabled'   => ['label' => 'Architecture Graph', 'desc' => 'Owner — /owner/architecture',                 'icon' => 'bi-diagram-3'],
                ],
                'AI Chat (per role)' => [
                    'ai.chat.enabled.owner'      => ['label' => 'Chat — Owner',      'desc' => 'Knowledge Assistant in Owner views',      'icon' => 'bi-chat-dots'],
                    'ai.chat.enabled.doctor'     => ['label' => 'Chat — Doctor',     'desc' => 'Knowledge Assistant in consultation view', 'icon' => 'bi-chat-dots'],
                    'ai.chat.enabled.pharmacy'   => ['label' => 'Chat — Pharmacy',   'desc' => 'Knowledge Assistant in pharmacy view',    'icon' => 'bi-chat-dots'],
                    'ai.chat.enabled.laboratory' => ['label' => 'Chat — Laboratory', 'desc' => 'Knowledge Assistant in lab view',         'icon' => 'bi-chat-dots'],
                    'ai.chat.enabled.radiology'  => ['label' => 'Chat — Radiology',  'desc' => 'Knowledge Assistant in radiology view',   'icon' => 'bi-chat-dots'],
                ],
                'Admin' => [
                    'admin.nocobase.enabled' => ['label' => 'NocoBase Admin', 'desc' => 'Owner — /owner/nocobase property & equipment', 'icon' => 'bi-building'],
                ],
            ];
            $allFlags = \App\Models\PlatformSetting::where('provider','feature_flag')
                ->pluck('meta', 'platform_name')
                ->map(fn($m) => (bool)($m['value'] ?? false));
        @endphp

        @foreach($flagGroups as $groupName => $flags)
        <div class="mb-4">
            <h6 class="text-muted text-uppercase fw-semibold mb-3" style="font-size:.75rem;letter-spacing:.08em;">{{ $groupName }}</h6>
            <div class="row g-2">
                @foreach($flags as $flagKey => $meta)
                @php $isOn = $allFlags[$flagKey] ?? false; @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border bg-white" style="min-height:64px;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi {{ $meta['icon'] }} text-muted"></i>
                            <div>
                                <div class="fw-medium" style="font-size:.9rem;">{{ $meta['label'] }}</div>
                                <div class="text-muted" style="font-size:.75rem;">{{ $meta['desc'] }}</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 ms-3 flex-shrink-0">
                            <span id="badge-{{ str_replace('.', '-', $flagKey) }}"
                                  class="badge {{ $isOn ? 'bg-success' : 'bg-secondary' }}"
                                  style="min-width:2.5rem;text-align:center;">
                                {{ $isOn ? 'ON' : 'OFF' }}
                            </span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input flag-toggle"
                                       type="checkbox"
                                       role="switch"
                                       style="width:2.5rem;height:1.25rem;cursor:pointer;"
                                       data-flag="{{ $flagKey }}"
                                       {{ $isOn ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

    </div>

</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.flag-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        const flag = this.dataset.flag;
        const enabled = this.checked;
        const badge = document.getElementById('badge-' + flag.replace(/\./g, '-'));
        const label = document.getElementById('label-' + flag.replace(/\./g, '-'));

        fetch('{{ route("owner.platform-settings.flag") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ flag: flag, enabled: enabled }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                badge.className = 'badge ' + (enabled ? 'bg-success' : 'bg-secondary');
                badge.textContent = enabled ? 'ON' : 'OFF';
                if (label) label.textContent = enabled ? 'Enabled' : 'Disabled';
            } else {
                this.checked = !enabled;
            }
        })
        .catch(() => { this.checked = !enabled; });
    });
});

// ── AI Model Provider switcher ────────────────────────────────────────────────
(function () {
    const LABELS = { ollama: 'Offline (Ollama)', openai: 'OpenAI', anthropic: 'Anthropic', huggingface: 'Hugging Face', groq: 'Groq (Free)' };
    let selectedProvider = document.querySelector('.provider-tab.active')?.dataset.provider ?? 'ollama';

    document.querySelectorAll('.provider-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            selectedProvider = this.dataset.provider;
            document.querySelectorAll('.provider-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.provider-pane').forEach(p => p.style.display = 'none');
            const pane = document.getElementById('pane-' + selectedProvider);
            if (pane) pane.style.display = '';
        });
    });

    document.getElementById('saveModelConfig')?.addEventListener('click', function () {
        const status = document.getElementById('modelConfigStatus');
        status.textContent = 'Saving…';
        this.disabled = true;

        const v = id => document.getElementById(id)?.value?.trim() ?? '';
        const payload = {
            provider:        selectedProvider,
            ollama_url:      v('ollama_url'),
            ollama_model:    v('ollama_model'),
            openai_model:    v('openai_model'),
            openai_key:      v('openai_key'),
            openai_base_url: v('openai_base_url'),
            anthropic_model: v('anthropic_model'),
            anthropic_key:   v('anthropic_key'),
            hf_model:        v('hf_model'),
            hf_key:          v('hf_key'),
            hf_base_url:     v('hf_base_url'),
            groq_model:      v('groq_model'),
            groq_key:        v('groq_key'),
        };

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const btn  = this;

        fetch('{{ route("owner.platform-settings.model-config") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.ok) {
                document.getElementById('activeProviderBadge').textContent = LABELS[data.provider] ?? data.provider;
                ['openai_key','anthropic_key','hf_key','groq_key'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                if (data.sidecar_synced) {
                    status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Saved &amp; synced to AI engine</span>';
                } else {
                    const tip = data.sidecar_error ? ` (${data.sidecar_error})` : '';
                    status.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>Saved to DB — AI engine offline, changes apply on restart' + tip + '</span>';
                }
                setTimeout(() => { status.textContent = ''; }, 6000);
            } else {
                status.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + (data.error ?? 'Error') + '</span>';
            }
        })
        .catch(() => {
            btn.disabled = false;
            status.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>Network error — config saved to DB, AI engine not reachable.</span>';
        });
    });
})();

// ── Test Connection (status bar) ──────────────────────────────────────────────
(function () {
    const btn    = document.getElementById('test-provider-btn');
    const icon   = document.getElementById('test-provider-icon');
    const label  = document.getElementById('test-provider-label');
    const badge  = document.getElementById('status-badge');
    const sIcon  = document.getElementById('status-icon');
    const sLabel = document.getElementById('status-label');
    const errDiv = document.getElementById('error-alert');
    const errMsg = document.getElementById('error-message');
    const tsText = document.getElementById('last-tested-text');
    const csrf   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    if (!btn) return;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        icon.className = 'bi bi-arrow-repeat spin me-1';
        label.textContent = 'Testing…';
        badge.className = 'badge bg-secondary d-flex align-items-center gap-1 px-3 py-2';
        sIcon.className = 'bi bi-hourglass-split';
        sLabel.textContent = 'Connecting…';

        fetch('{{ route("owner.platform-settings.test-provider") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            icon.className = 'bi bi-lightning me-1';
            label.textContent = 'Test Connection';

            if (data.status === 'connected') {
                badge.className = 'badge bg-success d-flex align-items-center gap-1 px-3 py-2';
                sIcon.className = 'bi bi-check-circle-fill';
                sLabel.textContent = 'Connected';
                tsText.innerHTML = '<i class="bi bi-clock me-1"></i>Last tested ' + (data.last_tested_at ?? 'just now');
                if (errDiv) errDiv.classList.add('d-none');
            } else {
                badge.className = 'badge bg-danger d-flex align-items-center gap-1 px-3 py-2';
                sIcon.className = 'bi bi-x-circle-fill';
                sLabel.textContent = 'Failed';
                if (errDiv && errMsg) {
                    errMsg.textContent = data.error ?? 'Connection failed';
                    errDiv.classList.remove('d-none');
                }
            }
        })
        .catch(() => {
            btn.disabled = false;
            icon.className = 'bi bi-lightning me-1';
            label.textContent = 'Test Connection';
            badge.className = 'badge bg-warning d-flex align-items-center gap-1 px-3 py-2';
            sLabel.textContent = 'Network Error';
        });
    });
})();
</script>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.spin { display: inline-block; animation: spin .8s linear infinite; }
</style>
@endpush
