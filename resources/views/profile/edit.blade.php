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
        try {
            $medgemma = \App\Models\PlatformSetting::medgemma();
            $fbr = \App\Models\PlatformSetting::fbr();
            $platformSettingsError = null;
        } catch (\Exception $e) {
            $medgemma = null;
            $fbr = null;
            $platformSettingsError = 'Platform settings unavailable: run `php artisan migrate` to set up required tables.';
        }
    @endphp
    @if($platformSettingsError)
        <div class="alert alert-warning mb-4"><i class="bi bi-exclamation-triangle me-2"></i>{{ $platformSettingsError }}</div>
    @endif
    @if($medgemma && $fbr)
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
    @endif {{-- end @if($medgemma && $fbr) --}}
    @endif {{-- end @if(hasRole('Owner')) --}}

    {{-- FBR IRIS Digital Invoicing --}}
    @if(auth()->user()->hasRole('Owner') && isset($fbr) && $fbr)
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

            {{-- PRAL DI API Prerequisites notice --}}
            <div class="alert py-3 mb-4" style="background:rgba(13,110,253,0.06); border:1px solid rgba(13,110,253,0.25);">
                <p class="fw-semibold mb-2 small" style="color:#0d6efd;"><i class="bi bi-info-circle me-2"></i>Before you configure — PRAL DI API setup steps (one-time)</p>
                <ol class="small mb-0 ps-3" style="color:var(--text-primary); line-height:1.8;">
                    <li>Log in to the <a href="https://iris.fbr.gov.pk" target="_blank" rel="noopener">FBR IRIS portal</a> with your NTN credentials.</li>
                    <li>Navigate to <strong>Digital Invoicing → API Integration → PRAL</strong>.</li>
                    <li>Submit your server's public IP for whitelisting — approval takes up to 2 hours.</li>
                    <li>After approval, copy your <strong>Sandbox API Token</strong> from the portal and paste it below.</li>
                    <li>Enable Sandbox mode below, save settings, then click <em>Test FBR Connection</em> to validate scenario <code>SN019</code>.</li>
                    <li>Once all sandbox scenarios pass, the portal auto-generates your <strong>Production API Token</strong> — paste it below and disable Sandbox mode.</li>
                </ol>
            </div>

            <p class="small mb-3" style="color:var(--text-muted);">
                Configure <strong>PRAL Digital Invoicing API v1.12</strong> to enable mandatory e-invoicing under Pakistan's Income Tax law.
                Every paid invoice is auto-submitted in real-time; FBR issues a unique <strong>IRN</strong> and the system attaches a scannable <strong>QR code</strong> to the printed invoice.
            </p>

            @if(session('success') && str_contains(session('success'), 'FBR'))
                <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
            @endif

            <div id="fbr-test-result" class="alert py-2 mb-3 d-none"></div>

            <form method="post" action="{{ route('owner.fbr-settings.update') }}">
                @csrf
                @method('PATCH')

                {{-- Row 1: NTN + STRN --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="fbr_ntn" class="form-label fw-semibold">NTN <span class="text-danger">*</span></label>
                        <input type="text" id="fbr_ntn" name="fbr_ntn"
                               class="form-control @error('fbr_ntn') is-invalid @enderror"
                               value="{{ old('fbr_ntn', $fbr->getMeta('ntn')) }}"
                               placeholder="e.g. 1234567-8">
                        @error('fbr_ntn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">National Tax Number — used as <code>sellerNTNCNIC</code> in API payload.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="fbr_strn" class="form-label fw-semibold">STRN</label>
                        <input type="text" id="fbr_strn" name="fbr_strn"
                               class="form-control @error('fbr_strn') is-invalid @enderror"
                               value="{{ old('fbr_strn', $fbr->getMeta('strn')) }}"
                               placeholder="e.g. 1234567890123">
                        @error('fbr_strn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Sales Tax Registration Number (optional if not GST-registered).</div>
                    </div>
                </div>

                {{-- Row 2: Business Name + Province --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="fbr_business_name" class="form-label fw-semibold">Business Name <span class="text-danger">*</span></label>
                        <input type="text" id="fbr_business_name" name="fbr_business_name"
                               class="form-control @error('fbr_business_name') is-invalid @enderror"
                               value="{{ old('fbr_business_name', $fbr->getMeta('business_name')) }}"
                               placeholder="{{ config('app.name') }}">
                        @error('fbr_business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Registered business name as shown in FBR records.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="fbr_seller_province" class="form-label fw-semibold">Province / Territory <span class="text-danger">*</span></label>
                        <select id="fbr_seller_province" name="fbr_seller_province"
                                class="form-select @error('fbr_seller_province') is-invalid @enderror">
                            <option value="">— Select province —</option>
                            @foreach(['Punjab','Sindh','KPK','Balochistan','ICT','AJK','GB'] as $prov)
                                <option value="{{ $prov }}" {{ old('fbr_seller_province', $fbr->getMeta('seller_province')) === $prov ? 'selected' : '' }}>{{ $prov }}</option>
                            @endforeach
                        </select>
                        @error('fbr_seller_province')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Seller's province used in every invoice payload.</div>
                    </div>
                </div>

                {{-- Business Address --}}
                <div class="mb-3">
                    <label for="fbr_business_address" class="form-label fw-semibold">Business Address</label>
                    <input type="text" id="fbr_business_address" name="fbr_business_address"
                           class="form-control"
                           value="{{ old('fbr_business_address', $fbr->getMeta('business_address')) }}"
                           placeholder="Full registered address">
                </div>

                {{-- Row 3: Sale Type + UOM + Tax Rate --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="fbr_sale_type" class="form-label fw-semibold">Sale Type</label>
                        <select id="fbr_sale_type" name="fbr_sale_type" class="form-select @error('fbr_sale_type') is-invalid @enderror">
                            @foreach(['Services','Goods','Both'] as $st)
                                <option value="{{ $st }}" {{ old('fbr_sale_type', $fbr->getMeta('sale_type', 'Services')) === $st ? 'selected' : '' }}>{{ $st }}</option>
                            @endforeach
                        </select>
                        @error('fbr_sale_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Healthcare = <strong>Services</strong>.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="fbr_uom" class="form-label fw-semibold">Unit of Measure</label>
                        <input type="text" id="fbr_uom" name="fbr_uom"
                               class="form-control"
                               value="{{ old('fbr_uom', $fbr->getMeta('uom', 'Unit')) }}"
                               placeholder="Unit">
                        <div class="form-text">Default UOM per line item (e.g. <code>Unit</code>, <code>Nos</code>).</div>
                    </div>
                    <div class="col-md-4">
                        <label for="fbr_tax_rate" class="form-label fw-semibold">GST / ST Rate (%)</label>
                        <input type="number" id="fbr_tax_rate" name="fbr_tax_rate"
                               step="0.01" min="0" max="100"
                               class="form-control @error('fbr_tax_rate') is-invalid @enderror"
                               value="{{ old('fbr_tax_rate', $fbr->getMeta('tax_rate', 0)) }}"
                               placeholder="0 (exempt) or 17">
                        @error('fbr_tax_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Set <code>0</code> if services are tax-exempt.</div>
                    </div>
                </div>

                <hr class="my-4">

                {{-- Environment toggle --}}
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="fbr_is_sandbox" name="fbr_is_sandbox"
                               value="1" {{ $fbr->getMeta('is_sandbox', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="fbr_is_sandbox">
                            <strong>Sandbox / Test Mode</strong>
                            <span class="text-muted small d-block">
                                When enabled, API calls go to <code>validateinvoicedata_sb</code> / <code>postinvoicedata_sb</code> (PRAL sandbox).
                                Disable only after all sandbox scenarios pass and you have a Production token.
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Row 4: Sandbox token + Production token --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="fbr_sandbox_api_key" class="form-label fw-semibold">
                            Sandbox API Token
                            <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem;">TEST ENV</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" id="fbr_sandbox_api_key" name="fbr_sandbox_api_key"
                                   class="form-control @error('fbr_sandbox_api_key') is-invalid @enderror"
                                   placeholder="{{ $fbr->getMeta('sandbox_api_key') ? '••••••••••••  (saved — enter new value to replace)' : 'Token from FBR IRIS → API Integration → PRAL (sandbox)' }}"
                                   autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary fbr-token-toggle"
                                    data-target="fbr_sandbox_api_key" title="Show / hide">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        @error('fbr_sandbox_api_key')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if($fbr->getMeta('sandbox_api_key'))
                            <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Token saved. Leave blank to keep.</div>
                        @else
                            <div class="form-text">Get this from PRAL after your server IP is whitelisted.</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label for="fbr_production_api_key" class="form-label fw-semibold">
                            Production API Token
                            <span class="badge bg-success ms-1" style="font-size:.6rem;">LIVE ENV</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" id="fbr_production_api_key" name="fbr_production_api_key"
                                   class="form-control @error('fbr_production_api_key') is-invalid @enderror"
                                   placeholder="{{ $fbr->getMeta('production_api_key') ? '••••••••••••  (saved — enter new value to replace)' : 'Auto-generated by PRAL after sandbox scenarios pass' }}"
                                   autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary fbr-token-toggle"
                                    data-target="fbr_production_api_key" title="Show / hide">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        @error('fbr_production_api_key')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if($fbr->getMeta('production_api_key'))
                            <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Token saved. Leave blank to keep.</div>
                        @else
                            <div class="form-text">Available in PRAL portal once sandbox validation is complete.</div>
                        @endif
                    </div>
                </div>

                {{-- Signing Secret --}}
                <div class="mb-4">
                    <label for="fbr_signing_secret" class="form-label fw-semibold">
                        Digital Signing Secret
                        <span class="badge bg-info text-dark ms-1" style="font-size:.6rem;">HMAC-SHA256</span>
                    </label>
                    <input type="password" id="fbr_signing_secret" name="fbr_signing_secret"
                           class="form-control"
                           placeholder="{{ $fbr->getMeta('signing_secret') ? '••••••••  (secret saved)' : 'Optional — leave blank to derive from NTN' }}"
                           autocomplete="off">
                    <div class="form-text">
                        Used to generate the HMAC-SHA256 <strong>digital signature</strong> on every FBR payload.
                        If left blank, the NTN is used as the signing key. Keep this value secure.
                    </div>
                </div>

                {{-- FBR DI API Compliance Checklist --}}
                <div class="alert py-3 mb-4" style="background:rgba(var(--accent-success-rgb, 25,135,84),0.07); border:1px solid rgba(25,135,84,0.2);">
                    <p class="fw-semibold mb-2" style="color:var(--accent-success);"><i class="bi bi-shield-check me-2"></i>PRAL DI API v1.12 — Compliance Checklist</p>
                    <ul class="small mb-0 ps-3" style="color:var(--text-primary);">
                        <li>✅ Every paid invoice is <strong>auto-submitted in real-time</strong> via <code>postinvoicedata</code>.</li>
                        <li>✅ FBR issues the <strong>IRN</strong> (<code>invoiceNumber</code>) — no sequential numbers generated locally.</li>
                        <li>✅ A scannable <strong>QR code</strong> (pipe-delimited: NTN|IRN|Amount|Tax|DateTime|Name) is printed on every invoice.</li>
                        <li>✅ Payloads include <code>hsCode</code>, <code>saleType</code>, <code>uoM</code>, <code>valueSalesExcludingST</code> per DI API schema.</li>
                        <li>✅ Payloads carry an <strong>HMAC-SHA256 digital signature</strong> for tamper detection.</li>
                        <li>✅ Full FBR API response is <strong>archived on the invoice record</strong> (5-year retention).</li>
                        <li>⚠️ Invoices submitted <strong>more than 24 hours</strong> after payment will flag an overdue warning.</li>
                        <li>ℹ️ Healthcare scenario: <strong>SN019</strong> (Services rendered or provided).</li>
                    </ul>
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
    // FBR token visibility toggles (sandbox + production)
    document.querySelectorAll('.fbr-token-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.dataset.target;
            const input = document.getElementById(targetId);
            const icon  = btn.querySelector('i');
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    });

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
