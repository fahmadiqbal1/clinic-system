@extends('layouts.app')
@section('title', 'Register Patient — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-person-plus me-2" style="color:var(--accent-success);"></i>Register New Patient</h1>
            <p class="page-subtitle">Enter patient details, select a doctor and collect the consultation fee</p>
        </div>
        <a href="{{ route('receptionist.dashboard') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
    </div>

    <div class="glass-card p-4 fade-in delay-1">

        {{-- ── ID Card Scanner bar ── --}}
        <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background:rgba(var(--accent-info-rgb),0.07); border:1px dashed rgba(var(--accent-info-rgb),0.3);">
            <i class="bi bi-upc-scan" style="font-size:1.5rem; color:var(--accent-info);"></i>
            <div class="flex-grow-1">
                <strong style="font-size:0.9rem;">ID Card Auto-Fill</strong>
                <p class="mb-0 small" style="color:var(--text-muted);">Scan the barcode on the back of the CNIC with a USB scanner, or use the camera to OCR the front.</p>
            </div>
            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#idScanModal">
                <i class="bi bi-upc-scan me-1"></i>Scan ID Card
            </button>
        </div>

        <form action="{{ route('receptionist.patients.store') }}" method="POST">
            @csrf

            {{-- ── Patient Details ── --}}
            <h5 class="mb-3"><i class="bi bi-person me-1" style="color:var(--accent-primary);"></i>Patient Details</h5>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label"><i class="bi bi-person me-1" style="color:var(--accent-primary);"></i>First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                    @error('first_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label"><i class="bi bi-person me-1" style="color:var(--accent-primary);"></i>Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                    @error('last_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="phone" class="form-label"><i class="bi bi-telephone me-1" style="color:var(--accent-info);"></i>Phone</label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone"
                           value="{{ old('phone') }}" placeholder="03XX-XXXXXXX" autocomplete="tel">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="cnic" class="form-label"><i class="bi bi-credit-card me-1" style="color:var(--accent-info);"></i>CNIC</label>
                    <input type="text" class="form-control @error('cnic') is-invalid @enderror" id="cnic" name="cnic"
                           value="{{ old('cnic') }}" placeholder="XXXXX-XXXXXXX-X" maxlength="15" autocomplete="off">
                    @error('cnic')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Required for FBR digital invoicing. Format: XXXXX-XXXXXXX-X</div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-gender-ambiguous me-1" style="color:var(--accent-warning);"></i>Gender <span class="text-danger">*</span></label>
                    <input type="hidden" id="gender" name="gender" value="{{ old('gender') }}" required>
                    <div class="d-flex gap-2" id="genderChips">
                        @foreach(['Male' => 'bi-gender-male', 'Female' => 'bi-gender-female', 'Other' => 'bi-gender-ambiguous'] as $g => $icon)
                        <button type="button"
                                class="btn btn-sm gender-chip {{ old('gender') === $g ? 'btn-primary' : 'btn-outline-secondary' }}"
                                data-value="{{ $g }}">
                            <i class="bi {{ $icon }} me-1"></i>{{ $g }}
                        </button>
                        @endforeach
                    </div>
                    @error('gender')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <span id="ageDisplay" class="text-muted small" style="display:none;">Age: <strong id="ageValue"></strong></span>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label"><i class="bi bi-calendar-event me-1" style="color:var(--accent-info);"></i>Date of Birth</label>
                    <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}">
                    @error('date_of_birth')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="doctor_id" class="form-label"><i class="bi bi-heart-pulse me-1" style="color:var(--accent-danger);"></i>Assigned Doctor <span class="text-danger">*</span></label>
                    <select class="form-select @error('doctor_id') is-invalid @enderror" id="doctor_id" name="doctor_id" required>
                        <option value="">Select Doctor</option>
                        @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}" {{ old('doctor_id') == $doctor->id ? 'selected' : '' }}>{{ $doctor->name }}</option>
                        @endforeach
                    </select>
                    @error('doctor_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- ── Consultation Fee (Upfront Payment) ── --}}
            <hr class="my-4" style="border-color:var(--glass-border);">
            <h5 class="mb-3"><i class="bi bi-cash-stack me-1" style="color:var(--accent-success);"></i>Consultation Fee</h5>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="service_catalog_id" class="form-label">Consultation Service <span class="text-danger">*</span></label>
                    <select class="form-select @error('service_catalog_id') is-invalid @enderror" id="service_catalog_id" name="service_catalog_id" required>
                        <option value="">Select Service</option>
                        @foreach($consultationServices as $svc)
                            <option value="{{ $svc->id }}"
                                data-price="{{ $svc->price }}"
                                {{ old('service_catalog_id') == $svc->id ? 'selected' : '' }}>
                                {{ $svc->name }} — {{ config('app.currency') }}{{ number_format($svc->price, 2) }}
                            </option>
                        @endforeach
                    </select>
                    @error('service_catalog_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="consultation_fee" class="form-label">Fee ({{ config('app.currency') }}) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                        <input type="number" step="0.01" min="0"
                               class="form-control @error('consultation_fee') is-invalid @enderror"
                               id="consultation_fee" name="consultation_fee"
                               value="{{ old('consultation_fee') }}" required readonly>
                    </div>
                    @error('consultation_fee')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <input type="hidden" id="payment_method" name="payment_method" value="{{ old('payment_method', 'cash') }}" required>
                    <div class="d-flex gap-2 flex-wrap" id="paymentChips">
                        @foreach(['cash' => ['Cash','bi-cash-stack'], 'card' => ['Card','bi-credit-card'], 'transfer' => ['Transfer','bi-bank']] as $val => [$label, $icon])
                        <button type="button"
                                class="btn btn-sm payment-chip {{ old('payment_method', 'cash') === $val ? 'btn-success' : 'btn-outline-secondary' }}"
                                data-value="{{ $val }}">
                            <i class="bi {{ $icon }} me-1"></i>{{ $label }}
                        </button>
                        @endforeach
                    </div>
                    @error('payment_method')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-3" style="border-top:1px solid var(--glass-border);">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Register & Collect Fee</button>
                <a href="{{ route('receptionist.dashboard') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

{{-- ── ID Card Scanner Modal ── --}}
<div class="modal fade" id="idScanModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upc-scan me-2" style="color:var(--accent-info);"></i>Scan ID Card</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="idScanClose"></button>
            </div>
            <div class="modal-body p-0">
                {{-- Tabs --}}
                <ul class="nav nav-tabs px-3 pt-2" id="scanTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-scan-tab="barcode">
                            <i class="bi bi-upc me-1"></i>Barcode Scanner
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-scan-tab="camera">
                            <i class="bi bi-camera me-1"></i>Camera / OCR
                        </button>
                    </li>
                </ul>

                {{-- Barcode Tab --}}
                <div id="scanTab-barcode" class="p-4">
                    <p class="mb-3" style="color:var(--text-secondary);">
                        <strong>Connect a USB barcode scanner</strong> and click the field below, then scan the barcode on the <strong>back</strong> of the CNIC card.
                        The CNIC number will be extracted automatically.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Scan target field</label>
                        <input type="text" id="barcodeRawInput"
                               class="form-control form-control-lg text-center"
                               placeholder="Click here then scan…"
                               autocomplete="off"
                               style="letter-spacing:0.15em; font-size:1.1rem;">
                        <div class="form-text">The scanner outputs the CNIC number as keystrokes. Once detected, fields fill automatically.</div>
                    </div>
                    <div id="barcodeParsed" class="alert alert-success d-none">
                        <strong>Detected CNIC:</strong> <span id="barcodeParsedCnic"></span>
                    </div>
                </div>

                {{-- Camera Tab --}}
                <div id="scanTab-camera" class="p-4 d-none">
                    <p class="mb-3" style="color:var(--text-secondary);">
                        Hold the <strong>front of the CNIC</strong> (English side) up to the camera clearly, then press Capture.
                        The form will be filled from the OCR result. Urdu text will be translated automatically.
                    </p>
                    <div class="d-flex gap-3 mb-3">
                        <div class="flex-grow-1 position-relative rounded overflow-hidden" style="background:#000; min-height:240px; border:1px solid var(--glass-border);">
                            <video id="cameraVideo" autoplay playsinline style="width:100%; height:100%; object-fit:cover; display:none;"></video>
                            <canvas id="cameraCanvas" style="display:none;"></canvas>
                            <div id="cameraPlaceholder" class="d-flex align-items-center justify-content-center w-100 h-100 position-absolute top-0" style="color:#555;">
                                <div class="text-center">
                                    <i class="bi bi-camera-video" style="font-size:2.5rem;"></i>
                                    <p class="mb-0 mt-2 small">Camera not started</p>
                                </div>
                            </div>
                            <img id="capturedPreview" style="width:100%; height:100%; object-fit:contain; display:none; position:absolute; top:0; left:0;">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" id="startCameraBtn" class="btn btn-outline-info btn-sm"><i class="bi bi-camera-video me-1"></i>Start Camera</button>
                        <button type="button" id="captureBtn" class="btn btn-primary btn-sm" disabled><i class="bi bi-camera me-1"></i>Capture & OCR</button>
                        <button type="button" id="retakeBtn" class="btn btn-outline-secondary btn-sm d-none"><i class="bi bi-arrow-counterclockwise me-1"></i>Retake</button>
                    </div>
                    <div id="ocrStatus" class="d-none">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="spinner-border spinner-border-sm text-info" id="ocrSpinner"></div>
                            <span id="ocrStatusText" style="color:var(--text-muted);">Processing…</span>
                        </div>
                        <div id="ocrRawOutput" class="p-2 rounded small" style="background:var(--glass-bg); border:1px solid var(--glass-border); max-height:120px; overflow-y:auto; white-space:pre-wrap; display:none;"></div>
                    </div>
                    <div id="ocrParsed" class="d-none">
                        <h6 class="mb-2">Extracted Fields</h6>
                        <div class="row g-2" id="ocrFieldsGrid"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="applyOcrBtn" class="btn btn-success d-none">
                    <i class="bi bi-check-circle me-1"></i>Apply to Form
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Service fee auto-populate ───────────────────────────────────────────
    var svcSelect = document.getElementById('service_catalog_id');
    svcSelect.addEventListener('change', function () {
        var sel = this.options[this.selectedIndex];
        document.getElementById('consultation_fee').value = sel.dataset.price || '';
    });
    (function () {
        if (svcSelect.value) {
            var sel = svcSelect.options[svcSelect.selectedIndex];
            if (!document.getElementById('consultation_fee').value) {
                document.getElementById('consultation_fee').value = sel.dataset.price || '';
            }
        }
    }());

    // ── Gender chips ────────────────────────────────────────────────────────
    document.querySelectorAll('.gender-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.gender-chip').forEach(function (b) {
                b.classList.remove('btn-primary'); b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary'); btn.classList.add('btn-primary');
            document.getElementById('gender').value = btn.dataset.value;
        });
    });

    // ── Payment method chips ────────────────────────────────────────────────
    document.querySelectorAll('.payment-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.payment-chip').forEach(function (b) {
                b.classList.remove('btn-success'); b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary'); btn.classList.add('btn-success');
            document.getElementById('payment_method').value = btn.dataset.value;
        });
    });

    // ── CNIC auto-format (XXXXX-XXXXXXX-X) ─────────────────────────────────
    document.getElementById('cnic').addEventListener('input', function () {
        var raw = this.value.replace(/\D/g, '').substring(0, 13);
        var out = raw.substring(0, 5);
        if (raw.length > 5)  out += '-' + raw.substring(5, 12);
        if (raw.length > 12) out += '-' + raw.substring(12);
        this.value = out;
    });

    // ── Age display from DOB ────────────────────────────────────────────────
    document.getElementById('date_of_birth').addEventListener('change', function () {
        var dob = new Date(this.value);
        if (isNaN(dob.getTime())) { document.getElementById('ageDisplay').style.display = 'none'; return; }
        var today = new Date();
        var age = today.getFullYear() - dob.getFullYear();
        var m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        document.getElementById('ageValue').textContent = age + ' yrs';
        document.getElementById('ageDisplay').style.display = '';
    });
    if (document.getElementById('date_of_birth').value) {
        document.getElementById('date_of_birth').dispatchEvent(new Event('change'));
    }

    // ── Helper: fill CNIC field + trigger formatter ─────────────────────────
    window.fillCnicField = function (rawCnic) {
        var inp = document.getElementById('cnic');
        inp.value = rawCnic;
        inp.dispatchEvent(new Event('input'));
    };

    // ── Helper: apply gender chip selection ────────────────────────────────
    window.selectGender = function (val) {
        document.querySelectorAll('.gender-chip').forEach(function (b) {
            b.classList.remove('btn-primary'); b.classList.add('btn-outline-secondary');
        });
        var target = document.querySelector('.gender-chip[data-value="' + val + '"]');
        if (target) { target.classList.remove('btn-outline-secondary'); target.classList.add('btn-primary'); }
        document.getElementById('gender').value = val;
    };

    // ── ID Scanner — Tab switching ──────────────────────────────────────────
    document.querySelectorAll('[data-scan-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-scan-tab]').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            document.querySelectorAll('[id^="scanTab-"]').forEach(function (el) { el.classList.add('d-none'); });
            var tab = document.getElementById('scanTab-' + btn.dataset.scanTab);
            if (tab) tab.classList.remove('d-none');
            if (btn.dataset.scanTab === 'barcode') {
                setTimeout(function () { var inp = document.getElementById('barcodeRawInput'); if (inp) inp.focus(); }, 150);
            }
        });
    });

    // ── ID Scanner — Barcode HID mode ──────────────────────────────────────
    (function () {
        var inp    = document.getElementById('barcodeRawInput');
        var parsed = document.getElementById('barcodeParsed');
        var cnicEl = document.getElementById('barcodeParsedCnic');
        if (!inp) return;

        // Auto-focus when modal opens on barcode tab
        var modal = document.getElementById('idScanModal');
        if (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                if (!document.getElementById('scanTab-barcode').classList.contains('d-none')) {
                    inp.focus();
                }
            });
        }

        var scanTimer = null;
        inp.addEventListener('input', function () {
            clearTimeout(scanTimer);
            // Scanners typically finish in < 100ms — wait 300ms for end of input
            scanTimer = setTimeout(function () {
                var raw = inp.value.trim();
                var digits = raw.replace(/\D/g, '');

                // Extract 13-digit CNIC
                var cnicMatch = digits.match(/\d{13}/);
                if (cnicMatch) {
                    var c = cnicMatch[0];
                    var formatted = c.substring(0,5) + '-' + c.substring(5,12) + '-' + c.substring(12);
                    window.fillCnicField(formatted);
                    cnicEl.textContent = formatted;
                    parsed.classList.remove('d-none');

                    // Close modal after short delay
                    setTimeout(function () {
                        var bsModal = bootstrap.Modal.getInstance(document.getElementById('idScanModal'));
                        if (bsModal) bsModal.hide();
                        inp.value = '';
                        parsed.classList.add('d-none');
                    }, 1500);
                }
            }, 300);
        });
    }());

    // ── ID Scanner — Camera OCR ─────────────────────────────────────────────
    (function () {
        var video      = document.getElementById('cameraVideo');
        var canvas     = document.getElementById('cameraCanvas');
        var preview    = document.getElementById('capturedPreview');
        var placeholder = document.getElementById('cameraPlaceholder');
        var startBtn   = document.getElementById('startCameraBtn');
        var captureBtn = document.getElementById('captureBtn');
        var retakeBtn  = document.getElementById('retakeBtn');
        var ocrStatus  = document.getElementById('ocrStatus');
        var ocrSpinner = document.getElementById('ocrSpinner');
        var ocrStatusText = document.getElementById('ocrStatusText');
        var ocrRaw     = document.getElementById('ocrRawOutput');
        var ocrParsed  = document.getElementById('ocrParsed');
        var ocrFields  = document.getElementById('ocrFieldsGrid');
        var applyBtn   = document.getElementById('applyOcrBtn');
        var stream     = null;
        var parsedData = {};

        if (!startBtn) return;

        // Stop camera when modal closes
        var modal = document.getElementById('idScanModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                stopCamera();
                resetOcr();
            });
        }

        function stopCamera() {
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            if (video) { video.srcObject = null; video.style.display = 'none'; }
            if (placeholder) placeholder.style.display = '';
            if (captureBtn) captureBtn.disabled = true;
        }

        function resetOcr() {
            if (ocrStatus)  ocrStatus.classList.add('d-none');
            if (ocrParsed)  ocrParsed.classList.add('d-none');
            if (applyBtn)   applyBtn.classList.add('d-none');
            if (retakeBtn)  retakeBtn.classList.add('d-none');
            if (preview)    { preview.style.display = 'none'; preview.src = ''; }
            parsedData = {};
        }

        startBtn.addEventListener('click', function () {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera not supported in this browser. Please use a modern browser.');
                return;
            }
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } } })
                .then(function (s) {
                    stream = s;
                    video.srcObject = s;
                    video.style.display = 'block';
                    placeholder.style.display = 'none';
                    captureBtn.disabled = false;
                    startBtn.textContent = 'Camera On';
                    startBtn.disabled = true;
                })
                .catch(function (err) {
                    alert('Camera access denied or unavailable: ' + err.message);
                });
        });

        captureBtn.addEventListener('click', function () {
            if (!stream) return;
            canvas.width  = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            var dataUrl = canvas.toDataURL('image/jpeg', 0.92);

            // Show captured image, hide video
            preview.src = dataUrl;
            preview.style.display = 'block';
            video.style.display = 'none';
            captureBtn.disabled = true;
            retakeBtn.classList.remove('d-none');

            // Run OCR
            runOcr(dataUrl);
        });

        retakeBtn.addEventListener('click', function () {
            preview.style.display = 'none';
            video.style.display = 'block';
            captureBtn.disabled = false;
            retakeBtn.classList.add('d-none');
            resetOcr();
        });

        function runOcr(imageData) {
            ocrStatus.classList.remove('d-none');
            ocrSpinner.style.display = '';
            ocrStatusText.textContent = 'Loading OCR engine…';
            ocrRaw.style.display = 'none';

            // Load Tesseract.js on demand
            if (typeof Tesseract === 'undefined') {
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
                s.onload = function () { doTesseract(imageData); };
                s.onerror = function () { ocrStatusText.textContent = 'Failed to load OCR engine.'; ocrSpinner.style.display = 'none'; };
                document.head.appendChild(s);
            } else {
                doTesseract(imageData);
            }
        }

        function doTesseract(imageData) {
            ocrStatusText.textContent = 'Recognising text…';
            Tesseract.recognize(imageData, 'eng+urd', {
                logger: function (m) {
                    if (m.status === 'recognizing text') {
                        ocrStatusText.textContent = 'Recognising… ' + Math.round((m.progress || 0) * 100) + '%';
                    }
                }
            }).then(function (result) {
                var text = result.data.text || '';
                ocrSpinner.style.display = 'none';
                ocrStatusText.textContent = 'Done — parsing fields…';
                ocrRaw.textContent = text;
                ocrRaw.style.display = 'block';
                parseOcrText(text);
            }).catch(function (err) {
                ocrSpinner.style.display = 'none';
                ocrStatusText.textContent = 'OCR error: ' + (err.message || err);
            });
        }

        function hasUrdu(str) {
            return /[؀-ۿ]/.test(str);
        }

        function parseOcrText(text) {
            var lines = text.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
            parsedData = {};

            // CNIC: 13 consecutive digits (possibly with hyphens)
            var cnicMatch = text.match(/\b(\d{5}[-\s]?\d{7}[-\s]?\d{1})\b/);
            if (cnicMatch) {
                parsedData.cnic = cnicMatch[1].replace(/[\s-]/g, '').replace(/(\d{5})(\d{7})(\d{1})/, '$1-$2-$3');
            }

            // DOB: common formats DD.MM.YYYY DD/MM/YYYY DD-MM-YYYY
            var dobMatch = text.match(/\b(\d{2}[.\/-]\d{2}[.\/-]\d{4})\b/);
            if (dobMatch) {
                var parts = dobMatch[1].split(/[.\/-]/);
                parsedData.dob = parts[2] + '-' + parts[1] + '-' + parts[0]; // YYYY-MM-DD
            }

            // Gender
            if (/\bM\b|Male/i.test(text))   parsedData.gender = 'Male';
            if (/\bF\b|Female/i.test(text)) parsedData.gender = 'Female';

            // Name: look for "Name:" or first all-caps English line after CNIC
            var nameMatch = text.match(/Name[:\s]+([A-Z][A-Z\s]+)/i);
            if (nameMatch) {
                var nameParts = nameMatch[1].trim().split(/\s+/);
                parsedData.first_name = nameParts[0] || '';
                parsedData.last_name  = nameParts.slice(1).join(' ') || '';
            }

            // Separate Urdu lines for translation
            var urduLines = lines.filter(function (l) { return hasUrdu(l); });

            // Show parsed fields
            ocrFields.innerHTML = '';
            var fieldDefs = [
                { key: 'first_name', label: 'First Name' },
                { key: 'last_name',  label: 'Last Name'  },
                { key: 'cnic',       label: 'CNIC'       },
                { key: 'dob',        label: 'Date of Birth' },
                { key: 'gender',     label: 'Gender'     },
            ];
            var anyFound = false;
            fieldDefs.forEach(function (fd) {
                var val = parsedData[fd.key] || '';
                var col = document.createElement('div');
                col.className = 'col-md-6';
                col.innerHTML = '<label class="form-label small mb-1">' + fd.label + '</label>'
                    + '<input type="text" class="form-control form-control-sm ocr-field-input" data-field="' + fd.key + '" value="' + escOcr(val) + '">';
                ocrFields.appendChild(col);
                if (val) anyFound = true;
            });
            ocrParsed.classList.remove('d-none');

            if (urduLines.length > 0 && !parsedData.first_name) {
                translateUrduLines(urduLines);
            }

            if (anyFound) applyBtn.classList.remove('d-none');
            ocrStatusText.textContent = anyFound ? 'Fields extracted — review and apply.' : 'Could not extract fields. Check image clarity.';
        }

        function escOcr(str) {
            return String(str).replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        function translateUrduLines(lines) {
            var joined = lines.join('\n');
            ocrStatusText.textContent = 'Translating Urdu text…';
            fetch('/ai-assistant/query', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ query: 'Translate the following Pakistani CNIC Urdu text to English and extract: full name, father name. Return JSON only: {"name": "...", "father_name": "..."}. Text:\n' + joined })
            }).then(function (r) { return r.json(); }).then(function (data) {
                var answer = data.answer || '';
                var jsonMatch = answer.match(/\{[\s\S]*\}/);
                if (jsonMatch) {
                    try {
                        var parsed = JSON.parse(jsonMatch[0]);
                        if (parsed.name && !parsedData.first_name) {
                            var parts = parsed.name.trim().split(/\s+/);
                            parsedData.first_name = parts[0];
                            parsedData.last_name  = parts.slice(1).join(' ');
                            // Update fields
                            document.querySelectorAll('.ocr-field-input[data-field="first_name"]').forEach(function (el) { el.value = parsedData.first_name; });
                            document.querySelectorAll('.ocr-field-input[data-field="last_name"]').forEach(function (el) { el.value = parsedData.last_name; });
                        }
                    } catch (e) {}
                }
                ocrStatusText.textContent = 'Translation done — review and apply.';
            }).catch(function () {
                ocrStatusText.textContent = 'Urdu translation unavailable — fill name manually.';
            });
        }

        applyBtn.addEventListener('click', function () {
            document.querySelectorAll('.ocr-field-input').forEach(function (inp) {
                var field = inp.dataset.field;
                var val   = inp.value.trim();
                if (!val) return;
                if (field === 'first_name') document.getElementById('first_name').value = val;
                if (field === 'last_name')  document.getElementById('last_name').value  = val;
                if (field === 'cnic')       window.fillCnicField(val);
                if (field === 'dob')        {
                    document.getElementById('date_of_birth').value = val;
                    document.getElementById('date_of_birth').dispatchEvent(new Event('change'));
                }
                if (field === 'gender')     window.selectGender(val);
            });
            var bsModal = bootstrap.Modal.getInstance(document.getElementById('idScanModal'));
            if (bsModal) bsModal.hide();
        });

        // Stop camera on close
        document.getElementById('idScanClose').addEventListener('click', stopCamera);
    }());

});
</script>
@endpush
