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

        {{-- ── Interactive Cloudflare Tunnel Setup Wizard ── --}}
        <div class="mt-4" id="tunnelWizardWrap">
            <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#tunnelWizard"
                    aria-expanded="false"
                    id="tunnelWizardToggle">
                <i class="bi bi-magic me-1"></i>
                Step-by-Step: Connect your local Ollama via Cloudflare Tunnel
                <i class="bi bi-chevron-down ms-auto" id="tunnelWizardChevron"></i>
            </button>

            <div class="collapse mt-3" id="tunnelWizard">
                <div class="border rounded-3 p-4" style="background:var(--glass-bg,#f8f9fa);">
                    <p class="mb-3 text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Follow these steps on your <strong>local Windows PC</strong> (where Ollama is installed),
                        then paste the URL into the field above.
                    </p>

                    {{-- Step list --}}
                    <div id="wizardSteps">

                        {{-- Step 1: Download cloudflared --}}
                        <div class="wizard-step d-flex gap-3 mb-4" id="wstep-1">
                            <div class="flex-shrink-0">
                                <div class="wizard-circle" id="wcirc-1">1</div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold mb-1">Download <code>cloudflared</code></div>
                                <p class="small text-muted mb-2">Choose your platform and download the binary.</p>
                                <div class="d-flex flex-wrap gap-2" id="wstep1-btns">
                                    <a id="dl-windows"
                                       href="https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
                                       class="btn btn-sm btn-outline-secondary"
                                       download>
                                        <i class="bi bi-windows me-1"></i>Windows (.exe)
                                    </a>
                                    <a id="dl-linux"
                                       href="https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64"
                                       class="btn btn-sm btn-outline-secondary"
                                       download>
                                        <i class="bi bi-terminal me-1"></i>Linux
                                    </a>
                                    <a id="dl-mac"
                                       href="https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-darwin-amd64.tgz"
                                       class="btn btn-sm btn-outline-secondary"
                                       download>
                                        <i class="bi bi-apple me-1"></i>macOS
                                    </a>
                                </div>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-success" onclick="wizardComplete(1)">
                                        <i class="bi bi-check me-1"></i>Done, I have cloudflared
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Step 2: Run tunnel --}}
                        <div class="wizard-step d-flex gap-3 mb-4 opacity-50" id="wstep-2">
                            <div class="flex-shrink-0">
                                <div class="wizard-circle" id="wcirc-2">2</div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold mb-1">Start the tunnel</div>
                                <p class="small text-muted mb-2">
                                    Open a terminal on your PC and run this command.
                                    <strong>Keep it running</strong> while you use AI features.
                                </p>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <code class="flex-grow-1 p-2 rounded bg-dark text-light d-block small" id="tunnel-cmd">
                                        cloudflared tunnel --url http://localhost:11434
                                    </code>
                                    <button class="btn btn-sm btn-outline-light bg-dark"
                                            onclick="copyToClipboard('cloudflared tunnel --url http://localhost:11434', this)"
                                            title="Copy command">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <p class="small text-muted mb-2">
                                    Wait for a line like:<br>
                                    <code>https://abc123-xyz.trycloudflare.com</code>
                                </p>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-success" onclick="wizardComplete(2)">
                                        <i class="bi bi-check me-1"></i>Tunnel is running, I see the URL
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Step 3: Paste URL --}}
                        <div class="wizard-step d-flex gap-3 mb-4 opacity-50" id="wstep-3">
                            <div class="flex-shrink-0">
                                <div class="wizard-circle" id="wcirc-3">3</div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold mb-1">Paste the tunnel URL into API Base URL</div>
                                <p class="small text-muted mb-2">
                                    Copy the <code>https://xxxx.trycloudflare.com</code> URL from your terminal
                                    and paste it into the <em>API Base URL</em> field above, then save.
                                </p>
                                <button class="btn btn-sm btn-outline-primary" onclick="focusApiUrl()">
                                    <i class="bi bi-arrow-up me-1"></i>Jump to API Base URL field
                                </button>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-success" onclick="wizardComplete(3)">
                                        <i class="bi bi-check me-1"></i>URL pasted and settings saved
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Step 4: Test --}}
                        <div class="wizard-step d-flex gap-3 mb-2 opacity-50" id="wstep-4">
                            <div class="flex-shrink-0">
                                <div class="wizard-circle" id="wcirc-4">4</div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold mb-1">Test the connection</div>
                                <p class="small text-muted mb-2">
                                    Click the button below to verify the server can reach Ollama through the tunnel.
                                </p>
                                <button class="btn btn-sm btn-outline-success" onclick="triggerServerTest()">
                                    <i class="bi bi-lightning me-1"></i>Run connection test now
                                </button>
                            </div>
                        </div>

                    </div>{{-- /wizardSteps --}}

                    <div id="wizardDone" class="d-none alert alert-success mt-3 mb-0">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>All set!</strong> Your local Ollama is now bridged to this server. AI analysis features are ready.
                    </div>
                </div>
            </div>
        </div>{{-- /tunnelWizardWrap --}}

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

    {{-- AI Model Provider --}}
    @php
        $currentProvider = \App\Models\PlatformSetting::where('platform_name', 'ai.model.provider')
            ->where('provider', 'model_config')->value('meta')['value'] ?? 'ollama';
        $currentModelId  = \App\Models\PlatformSetting::where('platform_name', 'ai.model.online_model_id')
            ->where('provider', 'model_config')->value('meta')['value'] ?? 'gpt-4o-mini';
        $providerLabels  = ['ollama' => 'Offline (Ollama)', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic (Claude)'];
    @endphp
    <div class="glass-panel p-4 mb-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-3 p-2 bg-info bg-opacity-10">
                <i class="bi bi-cloud-arrow-up fs-4 text-info"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-semibold">AI Model Provider</h5>
                <small class="text-muted">Switch between local Ollama and online AI services — takes effect immediately without restart</small>
            </div>
        </div>

        <div class="row g-3">
            {{-- Provider selector --}}
            <div class="col-12">
                <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Active Provider</label>
                <div class="d-flex gap-2 flex-wrap" id="providerBtns">
                    @foreach(['ollama' => ['icon'=>'bi-pc-display','label'=>'Offline (Ollama)','col'=>'secondary'],
                               'openai' => ['icon'=>'bi-stars','label'=>'OpenAI','col'=>'primary'],
                               'anthropic' => ['icon'=>'bi-lightning-charge','label'=>'Anthropic (Claude)','col'=>'success']]
                              as $pVal => $pCfg)
                    <button type="button"
                            class="btn btn-sm provider-btn {{ $currentProvider === $pVal ? 'btn-'.$pCfg['col'] : 'btn-outline-'.$pCfg['col'] }}"
                            data-provider="{{ $pVal }}"
                            data-col="{{ $pCfg['col'] }}">
                        <i class="bi {{ $pCfg['icon'] }} me-1"></i>{{ $pCfg['label'] }}
                    </button>
                    @endforeach
                </div>
                <div class="mt-2">
                    <span class="badge bg-secondary px-3" id="activeProviderBadge">
                        Active: {{ $providerLabels[$currentProvider] ?? $currentProvider }}
                    </span>
                </div>
            </div>

            {{-- Online config — hidden when provider = ollama --}}
            <div class="col-12" id="onlineConfig" style="{{ $currentProvider === 'ollama' ? 'display:none' : '' }}">
                <hr class="my-1">
                <div class="row g-2 mt-1">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Model ID</label>
                        <input type="text" id="modelIdInput" class="form-control form-control-sm"
                               placeholder="e.g. gpt-4o-mini, claude-haiku-4-5-20251001"
                               value="{{ $currentModelId }}">
                        <div class="form-text">Leave blank to keep current</div>
                    </div>
                    <div class="col-md-4" id="openaiKeyWrap" style="{{ $currentProvider !== 'openai' ? 'display:none' : '' }}">
                        <label class="form-label small fw-semibold">OpenAI API Key</label>
                        <input type="password" id="openaiKeyInput" class="form-control form-control-sm"
                               placeholder="sk-...  (leave blank to keep current)">
                    </div>
                    <div class="col-md-4" id="anthropicKeyWrap" style="{{ $currentProvider !== 'anthropic' ? 'display:none' : '' }}">
                        <label class="form-label small fw-semibold">Anthropic API Key</label>
                        <input type="password" id="anthropicKeyInput" class="form-control form-control-sm"
                               placeholder="sk-ant-...  (leave blank to keep current)">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-sm btn-primary" id="saveModelConfig">
                            <i class="bi bi-check-circle me-1"></i>Save & Apply
                        </button>
                        <span class="ms-2 small text-muted" id="modelConfigStatus"></span>
                    </div>
                </div>
            </div>

            {{-- Offline note --}}
            <div class="col-12" id="offlineNote" style="{{ $currentProvider !== 'ollama' ? 'display:none' : '' }}">
                <div class="alert alert-secondary mb-0 py-2 small">
                    <i class="bi bi-pc-display me-1"></i>
                    Running locally via Ollama — no data leaves this server.
                    Start the sidecar with <code>OLLAMA_URL=http://127.0.0.1:8081</code> and model <code>llama3.2:3b</code>.
                </div>
            </div>
        </div>
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

/* Tunnel wizard */
.wizard-circle {
    width: 2rem; height: 2rem; border-radius: 50%;
    background: var(--bs-primary, #0d6efd); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .85rem; flex-shrink: 0;
    transition: background .25s;
}
.wizard-circle.done {
    background: var(--bs-success, #198754);
}
.wizard-step { transition: opacity .3s; }
.wizard-step.active { opacity: 1 !important; }
</style>
@endpush

@push('scripts')
<script>
(function() {
    // Auto-detect platform and highlight the relevant download button
    const ua = navigator.userAgent.toLowerCase();
    const platformId = ua.includes('mac') ? 'dl-mac' : (ua.includes('linux') ? 'dl-linux' : 'dl-windows');
    const dlBtn = document.getElementById(platformId);
    if (dlBtn) {
        dlBtn.classList.remove('btn-outline-secondary');
        dlBtn.classList.add('btn-primary');
    }

    // Chevron rotation on accordion toggle
    const wizardEl = document.getElementById('tunnelWizard');
    const chevron  = document.getElementById('tunnelWizardChevron');
    if (wizardEl && chevron) {
        wizardEl.addEventListener('show.bs.collapse',  () => chevron.style.transform = 'rotate(180deg)');
        wizardEl.addEventListener('hide.bs.collapse',  () => chevron.style.transform = 'rotate(0deg)');
        // Auto-expand if provider is ollama (likely needs tunnel)
        const ollamaRadio = document.getElementById('ps_provider_ollama');
        if (ollamaRadio && ollamaRadio.checked) {
            new bootstrap.Collapse(wizardEl, { toggle: false }).show();
        }
    }
})();

// Step progression
let currentStep = 1;

function wizardComplete(step) {
    const circle = document.getElementById('wcirc-' + step);
    if (circle) {
        circle.classList.add('done');
        circle.innerHTML = '<i class="bi bi-check"></i>';
    }
    // Unlock next step
    const next = document.getElementById('wstep-' + (step + 1));
    if (next) {
        next.classList.remove('opacity-50');
        next.classList.add('active');
    } else {
        // All steps done
        document.getElementById('wizardDone')?.classList.remove('d-none');
    }
    currentStep = step + 1;
}

function focusApiUrl() {
    const field = document.getElementById('api_url');
    if (field) {
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        field.focus();
        field.select();
    }
}

function triggerServerTest() {
    const btn = document.getElementById('test-btn');
    if (btn && !btn.disabled) {
        btn.click();
        wizardComplete(4);
    } else {
        alert('Save your settings first, then click Test Connection.');
    }
}

function copyToClipboard(text, btn) {
    navigator.clipboard?.writeText(text).then(() => {
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-clipboard-check';
            setTimeout(() => { icon.className = 'bi bi-clipboard'; }, 2000);
        }
    }).catch(() => {
        // Fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}
</script>

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
    let selectedProvider = document.querySelector('.provider-btn.btn-primary, .provider-btn.btn-success, .provider-btn.btn-secondary')?.dataset.provider ?? 'ollama';

    const colMap = { ollama: 'secondary', openai: 'primary', anthropic: 'success' };

    document.querySelectorAll('.provider-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            selectedProvider = this.dataset.provider;

            // Update button styles
            document.querySelectorAll('.provider-btn').forEach(b => {
                const col = b.dataset.col;
                b.classList.remove('btn-' + col);
                b.classList.add('btn-outline-' + col);
            });
            const col = this.dataset.col;
            this.classList.remove('btn-outline-' + col);
            this.classList.add('btn-' + col);

            // Show/hide config panels
            document.getElementById('onlineConfig').style.display = selectedProvider === 'ollama' ? 'none' : '';
            document.getElementById('offlineNote').style.display  = selectedProvider === 'ollama' ? '' : 'none';
            document.getElementById('openaiKeyWrap').style.display    = selectedProvider === 'openai'    ? '' : 'none';
            document.getElementById('anthropicKeyWrap').style.display = selectedProvider === 'anthropic' ? '' : 'none';

            // If switching TO ollama, auto-save immediately (no key needed)
            if (selectedProvider === 'ollama') {
                saveModelConfig();
            }
        });
    });

    document.getElementById('saveModelConfig')?.addEventListener('click', saveModelConfig);

    function saveModelConfig() {
        const status = document.getElementById('modelConfigStatus');
        if (status) status.textContent = 'Saving…';

        const payload = {
            provider:      selectedProvider,
            model_id:      document.getElementById('modelIdInput')?.value?.trim() || '',
            openai_key:    document.getElementById('openaiKeyInput')?.value?.trim() || '',
            anthropic_key: document.getElementById('anthropicKeyInput')?.value?.trim() || '',
            _token:        document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        };

        fetch('{{ route("owner.platform-settings.model-config") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': payload._token },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const providerLabels = { ollama: 'Offline (Ollama)', openai: 'OpenAI', anthropic: 'Anthropic (Claude)' };
                document.getElementById('activeProviderBadge').textContent = 'Active: ' + (providerLabels[data.provider] ?? data.provider);
                if (status) status.textContent = '✓ Applied';
                setTimeout(() => { if (status) status.textContent = ''; }, 3000);
            } else {
                if (status) status.textContent = '✗ ' + (data.error ?? 'Error');
            }
        })
        .catch(() => { if (status) status.textContent = '✗ Network error'; });
    }
})();
</script>
@endpush
