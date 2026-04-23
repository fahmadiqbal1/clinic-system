{{-- Barcode/QR Scanner Component --}}
{{-- Usage: @include('components.barcode-scanner', ['id' => 'myScanner', 'modes' => 'all', 'placeholder' => 'Scan barcode...']) --}}
{{-- Fires: 'barcode-scanned' CustomEvent on document with detail: { code, source } --}}

@php
    $scannerId = $id ?? 'barcode-scanner-' . uniqid();
    $modes = $modes ?? 'all'; // usb|camera|file|all
    $placeholder = $placeholder ?? 'Scan or type barcode / QR code...';
    $showUsb = in_array($modes, ['all', 'usb']);
    $showCamera = in_array($modes, ['all', 'camera']);
    $showFile = in_array($modes, ['all', 'file']);
@endphp

<div class="scanner-widget glass-card p-3" id="{{ $scannerId }}-wrapper">
    {{-- Mode Tabs --}}
    @if($modes === 'all')
    <ul class="nav nav-pills nav-fill mb-3 scanner-mode-tabs" role="tablist" style="gap:4px;">
        <li class="nav-item" role="presentation">
            <button class="nav-link active scanner-tab-btn" data-mode="usb" type="button">
                <i class="bi bi-keyboard me-1"></i>USB / Type
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link scanner-tab-btn" data-mode="camera" type="button">
                <i class="bi bi-camera me-1"></i>Camera
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link scanner-tab-btn" data-mode="file" type="button">
                <i class="bi bi-image me-1"></i>File
            </button>
        </li>
    </ul>
    @endif

    {{-- USB / Keyboard Input Mode --}}
    <div class="scanner-panel scanner-usb" @if(!$showUsb) style="display:none" @endif>
        <div class="input-group">
            <span class="input-group-text" style="background:var(--glass-bg);border-color:var(--glass-border);color:var(--accent-primary);">
                <i class="bi bi-upc-scan"></i>
            </span>
            <input type="text"
                   class="form-control scanner-usb-input"
                   placeholder="{{ $placeholder }}"
                   autocomplete="off"
                   autofocus>
            <span class="input-group-text scanner-status-icon" style="background:var(--glass-bg);border-color:var(--glass-border);display:none;">
                <i class="bi bi-check-circle-fill" style="color:var(--accent-success);"></i>
            </span>
        </div>
        <small class="text-muted d-block mt-1" style="color:var(--text-muted)!important;font-size:0.75rem;">
            <i class="bi bi-info-circle me-1"></i>Point USB scanner at barcode or type code and press Enter
        </small>
    </div>

    {{-- Camera Scan Mode --}}
    <div class="scanner-panel scanner-camera" style="display:none;">
        <div class="scanner-camera-container">
            <div id="{{ $scannerId }}-camera-viewfinder" class="scanner-viewfinder" style="width:100%;border-radius:var(--input-radius);overflow:hidden;background:#000;min-height:200px;display:none;"></div>
            <div class="scanner-camera-controls mt-2 d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-outline-primary scanner-camera-start">
                    <i class="bi bi-play-fill me-1"></i>Start Camera
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger scanner-camera-stop" style="display:none;">
                    <i class="bi bi-stop-fill me-1"></i>Stop
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary scanner-camera-flip" style="display:none;">
                    <i class="bi bi-arrow-repeat me-1"></i>Flip
                </button>
            </div>
            <div class="scanner-camera-unavailable alert alert-warning mt-2 py-2 px-3" style="display:none;font-size:0.85rem;">
                <i class="bi bi-exclamation-triangle me-1"></i>Camera requires HTTPS or localhost. Use USB or File mode instead.
            </div>
        </div>
    </div>

    {{-- File Upload Mode --}}
    <div class="scanner-panel scanner-file" style="display:none;">
        <div class="scanner-file-drop" style="border:2px dashed var(--glass-border);border-radius:var(--input-radius);padding:2rem;text-align:center;cursor:pointer;transition:all 0.2s;">
            <i class="bi bi-cloud-arrow-up" style="font-size:1.8rem;color:var(--accent-primary);opacity:0.6;"></i>
            <p class="mb-1 mt-2" style="color:var(--text-secondary);font-size:0.9rem;">Drop barcode/QR image here or click to browse</p>
            <input type="file" class="scanner-file-input" accept="image/*" style="display:none;">
            <small style="color:var(--text-muted);">Supports JPG, PNG, GIF, WebP</small>
        </div>
    </div>

    {{-- Last Scanned Display --}}
    <div class="scanner-last-result mt-2" style="display:none;">
        <div class="d-flex align-items-center gap-2 px-2 py-1" style="background:rgba(52,211,153,0.1);border-radius:var(--input-radius);border:1px solid rgba(52,211,153,0.2);">
            <i class="bi bi-check-circle-fill" style="color:var(--accent-success);"></i>
            <span class="scanner-last-code fw-medium" style="font-size:0.85rem;"></span>
            <button type="button" class="btn btn-sm p-0 ms-auto scanner-copy-btn" title="Copy" style="color:var(--text-muted);border:none;background:none;">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
.scanner-widget .nav-pills .nav-link {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    color: var(--text-secondary);
    font-size: 0.82rem;
    padding: 0.4rem 0.8rem;
    border-radius: var(--btn-radius);
    transition: all 0.2s;
}
.scanner-widget .nav-pills .nav-link.active {
    background: rgba(129,140,248,0.15);
    border-color: var(--accent-primary);
    color: var(--accent-primary);
}
.scanner-widget .scanner-usb-input {
    background: var(--glass-bg);
    border-color: var(--glass-border);
    color: var(--text-primary);
    font-family: 'JetBrains Mono', monospace;
    letter-spacing: 0.5px;
}
.scanner-widget .scanner-usb-input:focus {
    border-color: var(--accent-primary);
    box-shadow: 0 0 0 3px rgba(129,140,248,0.15);
}
.scanner-widget .scanner-file-drop:hover,
.scanner-widget .scanner-file-drop.dragover {
    border-color: var(--accent-primary);
    background: rgba(129,140,248,0.05);
}
@keyframes scanner-flash-success {
    0% { box-shadow: 0 0 0 0 rgba(52,211,153,0.4); }
    70% { box-shadow: 0 0 0 8px rgba(52,211,153,0); }
    100% { box-shadow: 0 0 0 0 rgba(52,211,153,0); }
}
@keyframes scanner-flash-error {
    0% { box-shadow: 0 0 0 0 rgba(248,113,113,0.4); }
    70% { box-shadow: 0 0 0 8px rgba(248,113,113,0); }
    100% { box-shadow: 0 0 0 0 rgba(248,113,113,0); }
}
.scanner-success { animation: scanner-flash-success 0.6s ease-out; }
.scanner-error { animation: scanner-flash-error 0.6s ease-out; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
class BarcodeScanner {
    constructor(wrapperId) {
        this.wrapper = document.getElementById(wrapperId);
        if (!this.wrapper) return;
        this.id = wrapperId;
        this.html5QrCode = null;
        this.audioCtx = null;
        this.cameraActive = false;
        this.lastScanTime = 0;
        this.debounceMs = 400;
        this.usingFrontCamera = false;
        this._bindEvents();
    }

    _bindEvents() {
        // Tab switching
        this.wrapper.querySelectorAll('.scanner-tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this._switchMode(e.target.closest('.scanner-tab-btn').dataset.mode));
        });

        // USB input — Enter key
        const usbInput = this.wrapper.querySelector('.scanner-usb-input');
        if (usbInput) {
            usbInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const code = usbInput.value.trim();
                    if (code) {
                        this._emitScan(code, 'usb');
                        usbInput.value = '';
                    }
                }
            });
        }

        // Camera controls
        const startBtn = this.wrapper.querySelector('.scanner-camera-start');
        const stopBtn = this.wrapper.querySelector('.scanner-camera-stop');
        const flipBtn = this.wrapper.querySelector('.scanner-camera-flip');
        if (startBtn) startBtn.addEventListener('click', () => this._startCamera());
        if (stopBtn) stopBtn.addEventListener('click', () => this._stopCamera());
        if (flipBtn) flipBtn.addEventListener('click', () => this._flipCamera());

        // File upload
        const fileDrop = this.wrapper.querySelector('.scanner-file-drop');
        const fileInput = this.wrapper.querySelector('.scanner-file-input');
        if (fileDrop && fileInput) {
            fileDrop.addEventListener('click', () => fileInput.click());
            fileDrop.addEventListener('dragover', (e) => { e.preventDefault(); fileDrop.classList.add('dragover'); });
            fileDrop.addEventListener('dragleave', () => fileDrop.classList.remove('dragover'));
            fileDrop.addEventListener('drop', (e) => {
                e.preventDefault();
                fileDrop.classList.remove('dragover');
                if (e.dataTransfer.files.length) this._scanFile(e.dataTransfer.files[0]);
            });
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) this._scanFile(fileInput.files[0]);
                fileInput.value = '';
            });
        }

        // Copy button
        const copyBtn = this.wrapper.querySelector('.scanner-copy-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const code = this.wrapper.querySelector('.scanner-last-code')?.textContent;
                if (code) navigator.clipboard.writeText(code);
            });
        }
    }

    _switchMode(mode) {
        // Stop camera if switching away
        if (mode !== 'camera') this._stopCamera();

        this.wrapper.querySelectorAll('.scanner-tab-btn').forEach(b => b.classList.remove('active'));
        this.wrapper.querySelector(`.scanner-tab-btn[data-mode="${mode}"]`)?.classList.add('active');

        this.wrapper.querySelectorAll('.scanner-panel').forEach(p => p.style.display = 'none');
        const panel = this.wrapper.querySelector(`.scanner-${mode}`);
        if (panel) panel.style.display = '';

        if (mode === 'usb') {
            setTimeout(() => this.wrapper.querySelector('.scanner-usb-input')?.focus(), 100);
        }
    }

    async _startCamera() {
        const viewfinderEl = this.wrapper.querySelector(`[id$="-camera-viewfinder"]`);
        const startBtn = this.wrapper.querySelector('.scanner-camera-start');
        const stopBtn = this.wrapper.querySelector('.scanner-camera-stop');
        const flipBtn = this.wrapper.querySelector('.scanner-camera-flip');
        const unavailableMsg = this.wrapper.querySelector('.scanner-camera-unavailable');

        // Check secure context
        if (!window.isSecureContext) {
            if (unavailableMsg) unavailableMsg.style.display = '';
            return;
        }

        try {
            if (!this.html5QrCode) {
                this.html5QrCode = new Html5Qrcode(viewfinderEl.id);
            }

            this._initAudio();

            viewfinderEl.style.display = '';
            startBtn.style.display = 'none';
            stopBtn.style.display = '';
            flipBtn.style.display = '';
            if (unavailableMsg) unavailableMsg.style.display = 'none';

            await this.html5QrCode.start(
                { facingMode: this.usingFrontCamera ? 'user' : 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 },
                (decodedText) => this._emitScan(decodedText, 'camera'),
                () => {} // ignore errors during scanning
            );
            this.cameraActive = true;
        } catch (err) {
            console.warn('Camera start failed:', err);
            if (unavailableMsg) {
                unavailableMsg.textContent = err.message?.includes('Permission')
                    ? 'Camera permission denied. Please allow camera access and try again.'
                    : 'Camera unavailable. Use USB or File mode instead.';
                unavailableMsg.style.display = '';
            }
            viewfinderEl.style.display = 'none';
            startBtn.style.display = '';
            stopBtn.style.display = 'none';
            flipBtn.style.display = 'none';
        }
    }

    async _stopCamera() {
        if (this.html5QrCode && this.cameraActive) {
            try { await this.html5QrCode.stop(); } catch(e) {}
            this.cameraActive = false;
        }
        const viewfinder = this.wrapper.querySelector(`[id$="-camera-viewfinder"]`);
        if (viewfinder) viewfinder.style.display = 'none';

        const startBtn = this.wrapper.querySelector('.scanner-camera-start');
        const stopBtn = this.wrapper.querySelector('.scanner-camera-stop');
        const flipBtn = this.wrapper.querySelector('.scanner-camera-flip');
        if (startBtn) startBtn.style.display = '';
        if (stopBtn) stopBtn.style.display = 'none';
        if (flipBtn) flipBtn.style.display = 'none';
    }

    async _flipCamera() {
        this.usingFrontCamera = !this.usingFrontCamera;
        if (this.cameraActive) {
            await this._stopCamera();
            await this._startCamera();
        }
    }

    async _scanFile(file) {
        if (!file.type.startsWith('image/')) return;
        try {
            const html5QrCode = new Html5Qrcode(this.id + '-file-temp');
            const result = await html5QrCode.scanFile(file, true);
            this._emitScan(result, 'file');
            html5QrCode.clear();
        } catch (err) {
            this._showError('No barcode/QR found in image');
        }
    }

    _emitScan(code, source) {
        const now = Date.now();
        if (now - this.lastScanTime < this.debounceMs) return;
        this.lastScanTime = now;

        // Fire custom event
        document.dispatchEvent(new CustomEvent('barcode-scanned', {
            detail: { code: code.trim(), source, scannerId: this.id }
        }));

        // Visual feedback
        this._showSuccess(code);
        this._playBeep(true);
    }

    _showSuccess(code) {
        const resultEl = this.wrapper.querySelector('.scanner-last-result');
        const codeEl = this.wrapper.querySelector('.scanner-last-code');
        if (resultEl && codeEl) {
            codeEl.textContent = code;
            resultEl.style.display = '';
        }
        // Flash
        this.wrapper.classList.remove('scanner-success', 'scanner-error');
        void this.wrapper.offsetWidth;
        this.wrapper.classList.add('scanner-success');

        // Status icon on USB input
        const icon = this.wrapper.querySelector('.scanner-status-icon');
        if (icon) {
            icon.style.display = '';
            setTimeout(() => icon.style.display = 'none', 1500);
        }
    }

    _showError(msg) {
        this.wrapper.classList.remove('scanner-success', 'scanner-error');
        void this.wrapper.offsetWidth;
        this.wrapper.classList.add('scanner-error');
        this._playBeep(false);
    }

    _initAudio() {
        if (!this.audioCtx) {
            this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }
    }

    _playBeep(success) {
        if (!this.audioCtx) this._initAudio();
        if (!this.audioCtx) return;
        try {
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);
            osc.frequency.value = success ? 880 : 280;
            osc.type = success ? 'sine' : 'square';
            gain.gain.value = 0.1;
            osc.start();
            osc.stop(this.audioCtx.currentTime + (success ? 0.12 : 0.25));
        } catch(e) {}
    }

    destroy() {
        this._stopCamera();
    }
}

// Auto-init: do NOT auto-init — pages will init their own scanners
window.BarcodeScanner = BarcodeScanner;
</script>
@endpush
@endonce
