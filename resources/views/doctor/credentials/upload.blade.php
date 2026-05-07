@extends('layouts.app')
@section('title', 'Upload Credentials — ' . config('app.name'))

@php
    $credUser    = auth()->user();
    $deadline    = $credUser->created_at->addHours(48);
    $secsLeft    = max(0, (int) now()->diffInSeconds($deadline, false));
    $overDeadline = $secsLeft === 0;
@endphp

@section('content')
<div class="container mt-4" style="max-width: 640px;">
    <div class="page-header fade-in mb-3">
        <h2 class="mb-1"><i class="bi bi-cloud-upload me-2" style="color:var(--accent-primary);"></i>Upload Medical Credentials</h2>
        <p class="page-subtitle mb-0">Your account requires credential verification to access the system.</p>
    </div>

    {{-- Countdown card --}}
    <div class="glass-card fade-in mb-4 text-center py-3 px-4" style="
        border: 2px solid {{ $overDeadline ? '#ef4444' : '#f59e0b' }};
        background: {{ $overDeadline ? 'rgba(239,68,68,0.08)' : 'rgba(245,158,11,0.08)' }};
    ">
        <div style="font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">
            @if($overDeadline)
                Submission window expired
            @else
                Time remaining to submit
            @endif
        </div>
        <div id="bigCountdown" style="
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: {{ $overDeadline ? '#ef4444' : '#f59e0b' }};
            font-variant-numeric: tabular-nums;
        ">
            @if($overDeadline)
                EXPIRED
            @else
                --:--:--
            @endif
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
            Deadline: {{ $deadline->format('d M Y, h:i A') }}
        </div>
    </div>

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="glass-card fade-in">
        <p class="text-muted mb-4">
            Upload both your <strong>Medical Licence</strong> and <strong>Degree Certificate</strong>.<br>
            <span class="small"><i class="bi bi-paperclip me-1"></i>Accepted: PDF, JPG, JPEG, PNG or a <strong>camera photo</strong>. Max 5 MB each.</span>
        </p>

        <form method="POST" action="{{ route('doctor.credentials.store') }}" enctype="multipart/form-data">
            @csrf

            {{-- Medical Licence --}}
            <div class="mb-4">
                <label for="medical_license" class="form-label fw-semibold">
                    <i class="bi bi-file-earmark-medical me-2" style="color:var(--accent-primary);"></i>Medical Licence <span class="text-danger">*</span>
                </label>
                <div class="d-flex flex-column gap-2">
                    {{-- Standard file picker --}}
                    <input type="file" name="medical_license" id="medical_license"
                        class="form-control @error('medical_license') is-invalid @enderror"
                        accept=".pdf,.jpg,.jpeg,.png,image/*"
                        onchange="previewFile(this, 'prevLicense')">
                    {{-- Camera capture (mobile-first) --}}
                    <label class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" style="cursor:pointer; width: fit-content;">
                        <i class="bi bi-camera"></i> Take Photo
                        <input type="file" accept="image/*" capture="environment"
                               class="d-none" onchange="bridgeCapture(this, document.getElementById('medical_license'), 'prevLicense')">
                    </label>
                    <div id="prevLicense" class="mt-1" style="display:none;">
                        <img src="" alt="Preview" style="max-height:120px; border-radius:6px; border:1px solid rgba(255,255,255,0.1);">
                        <span class="small text-muted ms-2 fileName"></span>
                    </div>
                </div>
                @error('medical_license')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">PMDC or equivalent registration certificate</div>
            </div>

            {{-- Degree Certificate --}}
            <div class="mb-4">
                <label for="degree" class="form-label fw-semibold">
                    <i class="bi bi-mortarboard me-2" style="color:var(--accent-primary);"></i>Degree Certificate <span class="text-danger">*</span>
                </label>
                <div class="d-flex flex-column gap-2">
                    <input type="file" name="degree" id="degree"
                        class="form-control @error('degree') is-invalid @enderror"
                        accept=".pdf,.jpg,.jpeg,.png,image/*"
                        onchange="previewFile(this, 'prevDegree')">
                    <label class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" style="cursor:pointer; width: fit-content;">
                        <i class="bi bi-camera"></i> Take Photo
                        <input type="file" accept="image/*" capture="environment"
                               class="d-none" onchange="bridgeCapture(this, document.getElementById('degree'), 'prevDegree')">
                    </label>
                    <div id="prevDegree" class="mt-1" style="display:none;">
                        <img src="" alt="Preview" style="max-height:120px; border-radius:6px; border:1px solid rgba(255,255,255,0.1);">
                        <span class="small text-muted ms-2 fileName"></span>
                    </div>
                </div>
                @error('degree')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">MBBS, MD, or relevant medical degree</div>
            </div>

            <div class="alert alert-info mb-4" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);">
                <i class="bi bi-shield-lock me-2"></i>
                Your documents are stored securely and only accessible to clinic management for verification.
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload me-1"></i>Submit Credentials
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── Live countdown ──────────────────────────────────────────────────────────
(function () {
    var secs = {{ $secsLeft }};
    var el   = document.getElementById('bigCountdown');
    if (!el || secs <= 0) return;

    function tick() {
        if (secs <= 0) { el.textContent = 'EXPIRED'; el.style.color = '#ef4444'; return; }
        var h = Math.floor(secs / 3600);
        var m = Math.floor((secs % 3600) / 60);
        var s = secs % 60;
        el.textContent = h + 'h ' + String(m).padStart(2,'0') + 'm ' + String(s).padStart(2,'0') + 's';
        // Turn red when < 4 hours remain
        if (secs < 14400) el.style.color = '#ef4444';
        secs--;
    }
    tick();
    setInterval(tick, 1000);
})();

// ── File preview ────────────────────────────────────────────────────────────
function previewFile(input, previewId) {
    var container = document.getElementById(previewId);
    if (!input.files || !input.files[0]) { container.style.display = 'none'; return; }
    var file = input.files[0];
    container.querySelector('.fileName').textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
    if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function(e) {
            container.querySelector('img').src = e.target.result;
            container.style.display = '';
        };
        reader.readAsDataURL(file);
    } else {
        container.querySelector('img').src = '';
        container.style.display = '';
    }
}

// ── Bridge camera capture → main file input ─────────────────────────────────
// Camera inputs use a separate hidden input; we copy the file to the named input via DataTransfer.
function bridgeCapture(cameraInput, targetInput, previewId) {
    if (!cameraInput.files || !cameraInput.files[0]) return;
    try {
        var dt = new DataTransfer();
        dt.items.add(cameraInput.files[0]);
        targetInput.files = dt.files;
    } catch (e) {
        // DataTransfer not supported (very old Safari) — file stays in cameraInput
        // Rename the input so it submits under the correct key
        cameraInput.name = targetInput.name;
        targetInput.name = '__replaced__';
    }
    previewFile(targetInput.files ? targetInput : cameraInput, previewId);
}
</script>
@endpush
