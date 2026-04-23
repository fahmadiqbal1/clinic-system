{{-- Invoice OCR Component --}}
{{-- Usage: @include('components.invoice-ocr', ['id' => 'receiptOcr']) --}}
{{-- Displays raw OCR text in a read-only panel. User reads and manually fills invoice form. --}}

@php
    $ocrId = $id ?? 'invoice-ocr-' . uniqid();
@endphp

<div class="ocr-widget" id="{{ $ocrId }}-wrapper">
    {{-- Trigger Button + File Input --}}
    <div class="ocr-upload-area">
        <button type="button" class="btn btn-sm btn-outline-info ocr-trigger-btn">
            <i class="bi bi-file-earmark-text me-1"></i>Scan Invoice with OCR
        </button>
        <input type="file" class="ocr-file-input" accept="image/*,.pdf" style="display:none;">
        <small class="d-block mt-1" style="color:var(--text-muted);font-size:0.75rem;">
            Upload supplier invoice image to extract text. You'll review before using any values.
        </small>
    </div>

    {{-- Processing State --}}
    <div class="ocr-processing mt-3" style="display:none;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="spinner-border spinner-border-sm text-info" role="status"></div>
            <span style="color:var(--text-secondary);font-size:0.9rem;">Extracting text from invoice...</span>
        </div>
        <div class="progress" style="height:6px;background:var(--glass-bg);">
            <div class="progress-bar bg-info ocr-progress-bar" style="width:0%;transition:width 0.3s;"></div>
        </div>
        <small class="ocr-progress-text d-block mt-1" style="color:var(--text-muted);font-size:0.75rem;">Initializing OCR engine...</small>
    </div>

    {{-- Results Panel — Raw Text (Read-Only) --}}
    <div class="ocr-results mt-3" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0" style="color:var(--text-primary);font-size:0.9rem;">
                <i class="bi bi-file-text me-1 text-info"></i>Extracted Invoice Text
            </h6>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary ocr-copy-btn" title="Copy text">
                    <i class="bi bi-clipboard me-1"></i>Copy
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger ocr-clear-btn" title="Clear">
                    <i class="bi bi-x-lg me-1"></i>Clear
                </button>
            </div>
        </div>
        <div class="ocr-text-panel" style="
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--glass-border);
            border-radius: var(--input-radius);
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        "></div>
        <div class="mt-2 px-1">
            <small style="color:var(--accent-warning);font-size:0.75rem;">
                <i class="bi bi-exclamation-triangle me-1"></i>
                OCR text is approximate. Read the text above and enter the correct values in the form fields below.
            </small>
        </div>
    </div>

    {{-- Error State --}}
    <div class="ocr-error mt-3" style="display:none;">
        <div class="alert alert-danger py-2 px-3 mb-0" style="font-size:0.85rem;">
            <i class="bi bi-exclamation-circle me-1"></i>
            <span class="ocr-error-text">Failed to extract text from image.</span>
            <button type="button" class="btn btn-sm btn-outline-danger ms-2 ocr-retry-btn">Retry</button>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
.ocr-widget .ocr-text-panel::-webkit-scrollbar { width: 6px; }
.ocr-widget .ocr-text-panel::-webkit-scrollbar-track { background: transparent; }
.ocr-widget .ocr-text-panel::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }
</style>
@endpush

@push('scripts')
<script>
class InvoiceOCR {
    constructor(wrapperId) {
        this.wrapper = document.getElementById(wrapperId);
        if (!this.wrapper) return;
        this.tesseractLoaded = false;
        this.worker = null;
        this._bindEvents();
    }

    _bindEvents() {
        const triggerBtn = this.wrapper.querySelector('.ocr-trigger-btn');
        const fileInput = this.wrapper.querySelector('.ocr-file-input');
        const clearBtn = this.wrapper.querySelector('.ocr-clear-btn');
        const copyBtn = this.wrapper.querySelector('.ocr-copy-btn');
        const retryBtn = this.wrapper.querySelector('.ocr-retry-btn');

        if (triggerBtn) triggerBtn.addEventListener('click', () => fileInput?.click());
        if (fileInput) fileInput.addEventListener('change', () => {
            if (fileInput.files.length) this._processFile(fileInput.files[0]);
            fileInput.value = '';
        });
        if (clearBtn) clearBtn.addEventListener('click', () => this._clear());
        if (copyBtn) copyBtn.addEventListener('click', () => this._copyText());
        if (retryBtn) retryBtn.addEventListener('click', () => triggerBtn?.click());
    }

    async _loadTesseract() {
        if (this.tesseractLoaded) return;
        return new Promise((resolve, reject) => {
            if (window.Tesseract) { this.tesseractLoaded = true; resolve(); return; }
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
            script.onload = () => { this.tesseractLoaded = true; resolve(); };
            script.onerror = () => reject(new Error('Failed to load OCR engine'));
            document.head.appendChild(script);
        });
    }

    async _processFile(file) {
        const processingEl = this.wrapper.querySelector('.ocr-processing');
        const resultsEl = this.wrapper.querySelector('.ocr-results');
        const errorEl = this.wrapper.querySelector('.ocr-error');
        const progressBar = this.wrapper.querySelector('.ocr-progress-bar');
        const progressText = this.wrapper.querySelector('.ocr-progress-text');
        const textPanel = this.wrapper.querySelector('.ocr-text-panel');

        // Reset states
        resultsEl.style.display = 'none';
        errorEl.style.display = 'none';
        processingEl.style.display = '';
        progressBar.style.width = '0%';
        progressText.textContent = 'Loading OCR engine...';

        try {
            await this._loadTesseract();
            progressBar.style.width = '15%';
            progressText.textContent = 'Initializing...';

            const result = await Tesseract.recognize(file, 'eng', {
                logger: (m) => {
                    if (m.status === 'recognizing text') {
                        const pct = Math.round(15 + m.progress * 80);
                        progressBar.style.width = pct + '%';
                        progressText.textContent = 'Recognizing text... ' + Math.round(m.progress * 100) + '%';
                    }
                }
            });

            progressBar.style.width = '100%';
            progressText.textContent = 'Done!';

            const text = result.data.text?.trim();
            if (!text) throw new Error('No text found in image');

            textPanel.textContent = text;
            processingEl.style.display = 'none';
            resultsEl.style.display = '';

            // Dispatch event with raw text
            document.dispatchEvent(new CustomEvent('ocr-complete', {
                detail: { text, ocrId: this.wrapper.id }
            }));

        } catch (err) {
            processingEl.style.display = 'none';
            errorEl.style.display = '';
            const errorText = this.wrapper.querySelector('.ocr-error-text');
            if (errorText) errorText.textContent = err.message || 'Failed to extract text from image.';
        }
    }

    _clear() {
        this.wrapper.querySelector('.ocr-results').style.display = 'none';
        this.wrapper.querySelector('.ocr-text-panel').textContent = '';
    }

    _copyText() {
        const text = this.wrapper.querySelector('.ocr-text-panel')?.textContent;
        if (text) navigator.clipboard.writeText(text);
    }

    destroy() {
        if (this.worker) this.worker.terminate();
    }
}

window.InvoiceOCR = InvoiceOCR;
</script>
@endpush
@endonce
