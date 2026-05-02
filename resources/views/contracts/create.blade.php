@extends('layouts.app')

@section('title', 'Create Contract — ' . config('app.name'))

@section('content')
<div class="fade-in delay-1">
    <div class="page-header">
        <div>
            <h1 class="h3 fw-bold text-white mb-1">
                <i class="bi bi-file-earmark-plus me-2"></i> Create Staff Contract
            </h1>
            <p class="page-subtitle mb-0">Draft a new employment contract for a staff member</p>
        </div>
        <a href="{{ route('contracts.index') }}" class="btn btn-outline-light fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Contracts
        </a>
    </div>
</div>

@if ($errors->any())
    <div class="fade-in delay-2">
        <div class="alert-banner-danger d-flex align-items-start gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Please correct the following errors:</strong>
                <ul class="mb-0 mt-1 list-unstyled">
                    @foreach ($errors->all() as $error)
                        <li><i class="bi bi-dot"></i> {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    {{-- Contract Form --}}
    <div class="col-lg-8">
        <div class="fade-in delay-2">
            <div class="glass-card">
                <form action="{{ route('contracts.store') }}" method="POST" id="contract-form">
                    @csrf

                    {{-- Staff Selection --}}
                    <div class="mb-4">
                        <label for="user_id" class="form-label text-white fw-semibold">
                            <i class="bi bi-person-badge me-1"></i> Select Staff Member
                        </label>
                        <select name="user_id" id="user_id" class="form-select @error('user_id') is-invalid @enderror">
                            <option value="">— Choose a staff member —</option>
                            @foreach ($staffMembers as $doc)
                                <option value="{{ $doc->id }}" @selected(old('user_id', $staff->id ?? '') == $doc->id)
                                    data-role="{{ $doc->roles->pluck('name')->join(', ') }}">
                                    {{ $doc->name }} — {{ $doc->roles->pluck('name')->join(', ') }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-4">
                        {{-- Minimum Term --}}
                        <div class="col-md-6">
                            <label for="minimum_term_months" class="form-label text-white fw-semibold">
                                <i class="bi bi-calendar-range me-1"></i> Minimum Term (months)
                            </label>
                            <input type="number" name="minimum_term_months" id="minimum_term_months"
                                value="{{ old('minimum_term_months', 12) }}"
                                min="1" max="120"
                                class="form-control @error('minimum_term_months') is-invalid @enderror">
                            @error('minimum_term_months')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        {{-- Effective From --}}
                        <div class="col-md-6">
                            <label for="effective_from" class="form-label text-white fw-semibold">
                                <i class="bi bi-calendar-check me-1"></i> Effective From
                            </label>
                            <input type="date" name="effective_from" id="effective_from"
                                value="{{ old('effective_from', now()->format('Y-m-d')) }}"
                                class="form-control @error('effective_from') is-invalid @enderror">
                            @error('effective_from')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Template Quick-Fill --}}
                    <div class="mb-3">
                        <label class="form-label text-white fw-semibold">
                            <i class="bi bi-lightning me-1"></i> Quick Templates
                        </label>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-info template-btn" data-template="standard">
                                <i class="bi bi-file-text me-1"></i> Standard Employment
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info template-btn" data-template="doctor">
                                <i class="bi bi-heart-pulse me-1"></i> Doctor Agreement
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info template-btn" data-template="probation">
                                <i class="bi bi-hourglass-split me-1"></i> Probation Period
                            </button>
                        </div>
                    </div>

                    {{-- Toolbar --}}
                    <div class="mb-2">
                        <label class="form-label text-white fw-semibold">
                            <i class="bi bi-pencil-square me-1"></i> Contract Content
                        </label>
                        <div class="contract-toolbar" id="editor-toolbar">
                            <div class="toolbar-group">
                                <button type="button" class="toolbar-btn" data-cmd="bold" title="Bold (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
                                <button type="button" class="toolbar-btn" data-cmd="italic" title="Italic (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
                                <button type="button" class="toolbar-btn" data-cmd="underline" title="Underline (Ctrl+U)"><i class="bi bi-type-underline"></i></button>
                            </div>
                            <div class="toolbar-separator"></div>
                            <div class="toolbar-group">
                                <button type="button" class="toolbar-btn" data-cmd="formatBlock" data-value="H2" title="Heading 2"><i class="bi bi-type-h2"></i></button>
                                <button type="button" class="toolbar-btn" data-cmd="formatBlock" data-value="H3" title="Heading 3"><i class="bi bi-type-h3"></i></button>
                                <button type="button" class="toolbar-btn" data-cmd="formatBlock" data-value="P" title="Paragraph"><i class="bi bi-text-paragraph"></i></button>
                            </div>
                            <div class="toolbar-separator"></div>
                            <div class="toolbar-group">
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList" title="Bullet List"><i class="bi bi-list-ul"></i></button>
                                <button type="button" class="toolbar-btn" data-cmd="insertOrderedList" title="Numbered List"><i class="bi bi-list-ol"></i></button>
                            </div>
                            <div class="toolbar-separator"></div>
                            <div class="toolbar-group">
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft" title="Align Left"><i class="bi bi-text-left"></i></button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter" title="Align Center"><i class="bi bi-text-center"></i></button>
                            </div>
                            <div class="toolbar-separator"></div>
                            <div class="toolbar-group">
                                <button type="button" class="toolbar-btn" data-cmd="insertHorizontalRule" title="Horizontal Line"><i class="bi bi-hr"></i></button>
                                <button type="button" class="toolbar-btn" data-cmd="removeFormat" title="Clear Formatting"><i class="bi bi-eraser"></i></button>
                            </div>
                            <div class="toolbar-separator"></div>
                            <div class="toolbar-group">
                                <button type="button" class="toolbar-btn" id="btn-toggle-source" title="Toggle HTML Source"><i class="bi bi-code-slash"></i></button>
                                <button type="button" class="toolbar-btn" id="btn-fullscreen" title="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
                            </div>
                        </div>
                    </div>

                    {{-- WYSIWYG Editor --}}
                    <div class="contract-editor-wrapper" id="editor-wrapper">
                        <div class="contract-editor" id="contract-editor" contenteditable="true">{!! old('contract_html', '') !!}</div>
                        <textarea name="contract_html" id="contract_html" class="contract-source-editor d-none" rows="20">{{ old('contract_html', '') }}</textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 mb-4">
                        <small class="text-white-50"><i class="bi bi-info-circle me-1"></i>Use the toolbar or Ctrl+B/I/U shortcuts. Toggle <code>&lt;/&gt;</code> for raw HTML.</small>
                        <small class="text-white-50" id="word-count">0 words</small>
                    </div>
                    @error('contract_html')
                        <div class="text-danger small mb-3">{{ $message }}</div>
                    @enderror

                    <div class="glass-divider mb-4"></div>

                    {{-- Preview Toggle --}}
                    <div class="mb-4">
                        <button type="button" class="btn btn-outline-info w-100 fw-semibold" id="btn-preview-toggle">
                            <i class="bi bi-eye me-1"></i> Preview Contract as Staff Will See It
                        </button>
                        <div id="contract-preview" class="contract-preview d-none mt-3">
                            <div class="contract-preview-inner contract-content" id="preview-content"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('contracts.index') }}" class="btn btn-outline-light">
                            <i class="bi bi-x-lg me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary fw-semibold px-4">
                            <i class="bi bi-check-lg me-1"></i> Create Contract
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Info Sidebar --}}
    <div class="col-lg-4">
        {{-- Dynamic Placeholders --}}
        <div class="fade-in delay-3">
            <div class="glass-card accent-left-info mb-4">
                <h3 class="h6 fw-bold text-white mb-3">
                    <i class="bi bi-braces me-2"></i> Smart Placeholders
                </h3>
                <p class="small text-white-50 mb-2">Click to insert into the editor at cursor position:</p>
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-light placeholder-btn" data-placeholder="[STAFF_NAME]">Staff Name</button>
                    <button type="button" class="btn btn-sm btn-outline-light placeholder-btn" data-placeholder="[ROLE]">Role</button>
                    <button type="button" class="btn btn-sm btn-outline-light placeholder-btn" data-placeholder="[EFFECTIVE_DATE]">Start Date</button>
                    <button type="button" class="btn btn-sm btn-outline-light placeholder-btn" data-placeholder="[TERM_MONTHS]">Term</button>
                    <button type="button" class="btn btn-sm btn-outline-light placeholder-btn" data-placeholder="[CLINIC_NAME]">Clinic Name</button>
                    <button type="button" class="btn btn-sm btn-outline-light placeholder-btn" data-placeholder="[TODAY_DATE]">Today</button>
                </div>
            </div>
        </div>

        <div class="fade-in delay-4">
            <div class="glass-card accent-left-primary">
                <h3 class="h6 fw-bold text-white mb-3">
                    <i class="bi bi-lightbulb me-2"></i> Contract Notes
                </h3>
                <ul class="list-unstyled small text-white-50 mb-0">
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <i class="bi bi-check2-circle text-info mt-1 flex-shrink-0"></i>
                        <span>Contracts are created in <strong class="text-white">draft</strong> status and require staff signature to activate.</span>
                    </li>
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <i class="bi bi-lock text-warning mt-1 flex-shrink-0"></i>
                        <span>Once created, content is stored as an <strong class="text-white">immutable snapshot</strong> — it cannot be edited.</span>
                    </li>
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <i class="bi bi-layers text-info mt-1 flex-shrink-0"></i>
                        <span>Creating a new contract auto-supersedes any existing active contract for that staff member.</span>
                    </li>
                    <li class="d-flex align-items-start gap-2">
                        <i class="bi bi-pen text-success mt-1 flex-shrink-0"></i>
                        <span>Staff will see the contract and can <strong class="text-white">digitally sign</strong> from their portal.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.contract-toolbar {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 6px 8px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--glass-border);
    border-bottom: none;
    border-radius: var(--card-radius) var(--card-radius) 0 0;
    flex-wrap: wrap;
}
.toolbar-group { display: flex; gap: 1px; }
.toolbar-separator { width: 1px; height: 24px; background: var(--glass-border); margin: 0 4px; }
.toolbar-btn {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-secondary);
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 0.9rem;
}
.toolbar-btn:hover { background: rgba(255,255,255,0.08); color: var(--text-primary); border-color: var(--glass-border); }
.toolbar-btn.active { background: rgba(var(--accent-primary-rgb),0.2); color: var(--accent-primary); border-color: var(--accent-primary); }

.contract-editor-wrapper {
    border: 1px solid var(--glass-border);
    border-radius: 0 0 var(--card-radius) var(--card-radius);
    overflow: hidden;
    transition: all 0.2s ease;
}
.contract-editor-wrapper.fullscreen {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 9999;
    border-radius: 0;
    background: var(--bg-primary, #0f0f23);
}
.contract-editor-wrapper.fullscreen .contract-editor,
.contract-editor-wrapper.fullscreen .contract-source-editor {
    min-height: 100vh !important;
    max-height: 100vh !important;
}

.contract-editor {
    min-height: 400px;
    max-height: 70vh;
    overflow-y: auto;
    padding: 24px 28px;
    background: rgba(255,255,255,0.03);
    color: var(--text-primary);
    font-family: 'Inter', sans-serif;
    font-size: 0.92rem;
    line-height: 1.75;
    outline: none;
}
.contract-editor:focus { background: rgba(255,255,255,0.05); }
.contract-editor h1, .contract-editor h2, .contract-editor h3, .contract-editor h4 { color: var(--text-primary); margin-top: 1.2rem; margin-bottom: 0.5rem; }
.contract-editor h2 { font-size: 1.3rem; font-weight: 700; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.4rem; }
.contract-editor h3 { font-size: 1.1rem; font-weight: 600; }
.contract-editor p { margin-bottom: 0.75rem; }
.contract-editor ul, .contract-editor ol { padding-left: 1.5rem; margin-bottom: 0.75rem; }
.contract-editor hr { border-color: var(--glass-border); margin: 1rem 0; }

.contract-source-editor {
    min-height: 400px;
    max-height: 70vh;
    padding: 16px 20px;
    background: rgba(0,0,0,0.3);
    color: #a5d6ff;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 0.82rem;
    line-height: 1.6;
    border: none;
    resize: vertical;
    width: 100%;
}

.contract-preview {
    border: 1px solid var(--glass-border);
    border-radius: var(--card-radius);
    background: #fff;
    overflow: hidden;
}
.contract-preview-inner {
    padding: 32px;
    color: #1a1a2e !important;
    font-family: 'Inter', serif;
    font-size: 0.92rem;
    line-height: 1.75;
}
.contract-preview-inner h1, .contract-preview-inner h2, .contract-preview-inner h3, .contract-preview-inner h4 { color: #1a1a2e; }
.contract-preview-inner h2 { border-bottom: 2px solid #1a56a0; padding-bottom: 6px; color: #1a56a0; }
.contract-preview-inner hr { border-color: #dde4ef; }
</style>
@endpush

@push('scripts')
<script>
(function() {
    const editor = document.getElementById('contract-editor');
    const source = document.getElementById('contract_html');
    const wrapper = document.getElementById('editor-wrapper');
    const wordCount = document.getElementById('word-count');
    const previewBtn = document.getElementById('btn-preview-toggle');
    const previewDiv = document.getElementById('contract-preview');
    const previewContent = document.getElementById('preview-content');
    const toggleSourceBtn = document.getElementById('btn-toggle-source');
    const fullscreenBtn = document.getElementById('btn-fullscreen');
    let sourceMode = false;

    // Sync editor → hidden textarea
    function syncToSource() {
        source.value = editor.innerHTML;
        updateWordCount();
    }
    function updateWordCount() {
        const text = editor.innerText.trim();
        const count = text ? text.split(/\s+/).length : 0;
        wordCount.textContent = count + ' word' + (count !== 1 ? 's' : '');
    }

    editor.addEventListener('input', syncToSource);
    editor.addEventListener('keyup', syncToSource);

    // Toolbar commands
    document.querySelectorAll('.toolbar-btn[data-cmd]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (sourceMode) return;
            const cmd = this.dataset.cmd;
            const val = this.dataset.value || null;
            editor.focus();
            document.execCommand(cmd, false, val);
            syncToSource();
        });
    });

    // Toggle source
    toggleSourceBtn.addEventListener('click', function() {
        sourceMode = !sourceMode;
        if (sourceMode) {
            source.value = editor.innerHTML;
            source.classList.remove('d-none');
            editor.classList.add('d-none');
            this.classList.add('active');
        } else {
            editor.innerHTML = source.value;
            editor.classList.remove('d-none');
            source.classList.add('d-none');
            this.classList.remove('active');
        }
        syncToSource();
    });

    // Fullscreen
    fullscreenBtn.addEventListener('click', function() {
        wrapper.classList.toggle('fullscreen');
        const isFS = wrapper.classList.contains('fullscreen');
        this.innerHTML = isFS ? '<i class="bi bi-fullscreen-exit"></i>' : '<i class="bi bi-arrows-fullscreen"></i>';
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && wrapper.classList.contains('fullscreen')) {
            wrapper.classList.remove('fullscreen');
            fullscreenBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        }
    });

    // Preview toggle
    previewBtn.addEventListener('click', function() {
        const showing = !previewDiv.classList.contains('d-none');
        if (showing) {
            previewDiv.classList.add('d-none');
            this.innerHTML = '<i class="bi bi-eye me-1"></i> Preview Contract as Staff Will See It';
        } else {
            syncToSource();
            previewContent.innerHTML = source.value;
            previewDiv.classList.remove('d-none');
            this.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Hide Preview';
        }
    });

    // Placeholder insertion
    document.querySelectorAll('.placeholder-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (sourceMode) return;
            editor.focus();
            document.execCommand('insertText', false, this.dataset.placeholder);
            syncToSource();
        });
    });

    // Resolve placeholder values from form fields
    function resolvePlaceholders(html) {
        const staffSelect = document.getElementById('user_id');
        const selectedOpt = staffSelect ? staffSelect.options[staffSelect.selectedIndex] : null;
        const staffName   = selectedOpt && selectedOpt.value ? selectedOpt.text.split(' — ')[0].trim() : '[STAFF_NAME]';
        const role        = selectedOpt && selectedOpt.value ? (selectedOpt.dataset.role || '[ROLE]') : '[ROLE]';
        const effDate     = document.getElementById('effective_from')?.value || '[EFFECTIVE_DATE]';
        const term        = document.getElementById('minimum_term_months')?.value || '[TERM_MONTHS]';
        return html
            .replaceAll('[STAFF_NAME]', staffName)
            .replaceAll('[ROLE]', role)
            .replaceAll('[EFFECTIVE_DATE]', effDate)
            .replaceAll('[TERM_MONTHS]', term + ' months')
            .replaceAll('[CLINIC_NAME]', 'Aviva Healthcare')
            .replaceAll('[TODAY_DATE]', new Date().toLocaleDateString('en-GB', {day:'numeric',month:'long',year:'numeric'}));
    }

    // Template quick-fill
    const templates = {
        standard: `<h2>Employment Contract</h2>
<p>This Employment Contract ("Agreement") is entered into between <strong>[CLINIC_NAME]</strong> ("Employer") and <strong>[STAFF_NAME]</strong> ("Employee"), effective as of <strong>[EFFECTIVE_DATE]</strong>.</p>

<h3>1. Position & Duties</h3>
<p>The Employee shall serve in the role of <strong>[ROLE]</strong> and perform all duties reasonably associated with this position, as directed by the Employer.</p>

<h3>2. Term of Employment</h3>
<p>This agreement shall commence on <strong>[EFFECTIVE_DATE]</strong> for a minimum term of <strong>[TERM_MONTHS]</strong> months. After the minimum term, employment continues on a month-to-month basis unless terminated by either party with 30 days' written notice.</p>

<h3>3. Compensation</h3>
<p>The Employee shall receive a monthly salary as agreed upon separately. Payment shall be made on the last working day of each month via bank transfer.</p>

<h3>4. Working Hours</h3>
<p>Standard working hours are 8 hours per day, 6 days per week. Overtime and on-call duties shall be compensated as per clinic policy.</p>

<h3>5. Leave & Absence</h3>
<p>The Employee is entitled to annual leave, sick leave, and public holidays as per applicable labour law and clinic policy.</p>

<h3>6. Confidentiality</h3>
<p>The Employee shall maintain strict confidentiality of all patient records, business operations, and proprietary information both during and after employment.</p>

<h3>7. Termination</h3>
<ul>
<li>Either party may terminate this contract with 30 days' written notice after the minimum term.</li>
<li>Termination during the minimum term constitutes early exit and may incur penalties as outlined in clinic policy.</li>
<li>Gross misconduct or breach of confidentiality may result in immediate termination.</li>
</ul>

<h3>8. Dispute Resolution</h3>
<p>Any disputes arising from this agreement shall be resolved through mediation. If mediation fails, the matter shall be referred to the appropriate legal authority.</p>

<hr>
<p><em>This contract is legally binding upon signature by both parties.</em></p>`,

        doctor: `<h2>Doctor Service Agreement</h2>
<p>This Service Agreement ("Agreement") is entered into between <strong>[CLINIC_NAME]</strong> ("Clinic") and <strong>Dr. [STAFF_NAME]</strong> ("Doctor"), effective as of <strong>[EFFECTIVE_DATE]</strong>.</p>

<h3>1. Engagement</h3>
<p>The Clinic engages the Doctor to provide medical consultation, diagnosis, and treatment services in the capacity of <strong>[ROLE]</strong>.</p>

<h3>2. Term</h3>
<p>This agreement is for a minimum term of <strong>[TERM_MONTHS]</strong> months starting <strong>[EFFECTIVE_DATE]</strong>. Renewal is automatic unless either party provides 60 days' written notice.</p>

<h3>3. Compensation & Revenue Sharing</h3>
<p>The Doctor shall receive compensation based on a commission structure agreed upon separately, calculated on consultation fees collected. Payouts are processed bi-monthly.</p>

<h3>4. Schedule & Availability</h3>
<p>The Doctor shall maintain an agreed-upon schedule. Changes to availability must be communicated at least 48 hours in advance except in emergencies.</p>

<h3>5. Medical Standards</h3>
<ul>
<li>The Doctor shall maintain a valid medical license and all required certifications.</li>
<li>All clinical decisions shall follow evidence-based practice guidelines.</li>
<li>Complete and accurate medical records must be maintained for every patient encounter.</li>
</ul>

<h3>6. Patient Confidentiality</h3>
<p>The Doctor shall comply with all applicable patient privacy laws and maintain strict confidentiality of patient information.</p>

<h3>7. Malpractice & Insurance</h3>
<p>The Doctor shall maintain professional indemnity insurance. The Clinic maintains facility-level coverage.</p>

<h3>8. Non-Compete</h3>
<p>During the term and for 6 months after termination, the Doctor shall not establish or work at a competing practice within a 5-kilometre radius.</p>

<h3>9. Termination</h3>
<ul>
<li>60 days' written notice required after minimum term.</li>
<li>Immediate termination may occur for license revocation, malpractice, or gross negligence.</li>
</ul>

<hr>
<p><em>This agreement is binding upon signature by both parties.</em></p>`,

        probation: `<h2>Probationary Employment Contract</h2>
<p>This Probationary Contract ("Agreement") is between <strong>[CLINIC_NAME]</strong> ("Employer") and <strong>[STAFF_NAME]</strong> ("Employee"), effective <strong>[EFFECTIVE_DATE]</strong>.</p>

<h3>1. Probation Period</h3>
<p>The Employee is hired on a probationary basis for <strong>[TERM_MONTHS]</strong> months. Performance and conduct will be evaluated during this period to determine suitability for permanent employment.</p>

<h3>2. Position</h3>
<p>The Employee shall serve as <strong>[ROLE]</strong> and perform all duties assigned.</p>

<h3>3. Evaluation</h3>
<ul>
<li>Performance reviews will be conducted monthly during probation.</li>
<li>The Employer may extend probation by up to 3 months if improvement is needed.</li>
<li>Upon successful completion, the Employee will be offered a standard employment contract.</li>
</ul>

<h3>4. Compensation</h3>
<p>Probationary salary shall be as agreed separately. Upon successful completion, compensation may be adjusted upward.</p>

<h3>5. Termination During Probation</h3>
<p>Either party may terminate this agreement with 7 days' written notice during the probation period. No penalty applies for termination during probation.</p>

<h3>6. Confidentiality</h3>
<p>All confidentiality obligations apply from day one and survive termination of this agreement.</p>

<hr>
<p><em>This probationary contract is effective upon signature.</em></p>`
    };

    document.querySelectorAll('.template-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const key = this.dataset.template;
            if (!templates[key]) return;
            if (editor.innerText.trim().length > 10 && !confirm('Replace current content with template?')) return;
            editor.innerHTML = resolvePlaceholders(templates[key]);
            syncToSource();
        });
    });

    // Form submit: sync one final time
    document.getElementById('contract-form').addEventListener('submit', function() {
        if (!sourceMode) source.value = editor.innerHTML;
    });

    // Initial sync
    updateWordCount();
})();
</script>
@endpush
@endsection