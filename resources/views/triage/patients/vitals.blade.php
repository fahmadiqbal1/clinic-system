@extends('layouts.app')
@section('title', 'Record Vitals — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-clipboard2-pulse me-2" style="color:var(--accent-warning);"></i>Record Patient Vitals</h2>
            <p class="page-subtitle mb-0">Capture vitals before sending to doctor</p>
        </div>
        <a href="{{ route('triage.patients.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Patients</a>
    </div>

    {{-- Patient Info --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-person me-2" style="color:var(--accent-info);"></i>Patient Information</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value">{{ $patient->first_name }} {{ $patient->last_name }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Assigned Doctor</span>
                    <span class="info-value">{{ $patient->doctor->name }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Normal Range Reference Cards --}}
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-info-circle me-2" style="color:var(--accent-info);"></i>Normal Vital Ranges — Quick Reference</span>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#rangeCards" aria-expanded="true">
                <i class="bi bi-chevron-up"></i>
            </button>
        </div>
        <div class="collapse show" id="rangeCards">
            <div class="card-body pb-2">
                <div class="row g-3">
                    {{-- Blood Pressure --}}
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 hover-lift h-100" style="background:rgba(var(--accent-danger-rgb),0.08); border-left:3px solid var(--accent-danger) !important;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-heart-pulse me-2" style="color:var(--accent-danger); font-size:1.1rem;"></i>
                                    <strong style="font-size:0.8rem;">Blood Pressure</strong>
                                </div>
                                <div style="font-size:1.3rem; font-weight:700; color:var(--text-primary);">120/80</div>
                                <small style="color:var(--text-muted);">mmHg &bull; Normal adult</small>
                                <div class="mt-1" style="font-size:0.7rem; color:var(--text-secondary);">
                                    <span class="badge-glass me-1" style="background:rgba(var(--accent-success-rgb),0.15); color:var(--accent-success); font-size:0.65rem;">Optimal &lt;120/80</span>
                                    <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.15); color:var(--accent-warning); font-size:0.65rem;">High &ge;140/90</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Temperature --}}
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 hover-lift h-100" style="background:rgba(var(--accent-warning-rgb),0.08); border-left:3px solid var(--accent-warning) !important;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-thermometer-half me-2" style="color:var(--accent-warning); font-size:1.1rem;"></i>
                                    <strong style="font-size:0.8rem;">Temperature</strong>
                                </div>
                                <div style="font-size:1.3rem; font-weight:700; color:var(--text-primary);">36.1 – 37.2</div>
                                <small style="color:var(--text-muted);">&deg;C &bull; Oral average 36.6</small>
                                <div class="mt-1" style="font-size:0.7rem; color:var(--text-secondary);">
                                    <span class="badge-glass me-1" style="background:rgba(var(--accent-info-rgb),0.15); color:var(--accent-info); font-size:0.65rem;">Low &lt;36.1</span>
                                    <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.15); color:var(--accent-danger); font-size:0.65rem;">Fever &ge;38.0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Heart Rate --}}
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 hover-lift h-100" style="background:rgba(var(--accent-primary-rgb),0.08); border-left:3px solid var(--accent-primary) !important;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-activity me-2" style="color:var(--accent-primary); font-size:1.1rem;"></i>
                                    <strong style="font-size:0.8rem;">Heart Rate</strong>
                                </div>
                                <div style="font-size:1.3rem; font-weight:700; color:var(--text-primary);">60 – 100</div>
                                <small style="color:var(--text-muted);">bpm &bull; Resting adult</small>
                                <div class="mt-1" style="font-size:0.7rem; color:var(--text-secondary);">
                                    <span class="badge-glass me-1" style="background:rgba(var(--accent-info-rgb),0.15); color:var(--accent-info); font-size:0.65rem;">Brady &lt;60</span>
                                    <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.15); color:var(--accent-danger); font-size:0.65rem;">Tachy &gt;100</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Respiratory Rate --}}
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 hover-lift h-100" style="background:rgba(var(--accent-success-rgb),0.08); border-left:3px solid var(--accent-success) !important;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-lungs me-2" style="color:var(--accent-success); font-size:1.1rem;"></i>
                                    <strong style="font-size:0.8rem;">Respiratory Rate</strong>
                                </div>
                                <div style="font-size:1.3rem; font-weight:700; color:var(--text-primary);">12 – 20</div>
                                <small style="color:var(--text-muted);">breaths/min &bull; Adult</small>
                                <div class="mt-1" style="font-size:0.7rem; color:var(--text-secondary);">
                                    <span class="badge-glass me-1" style="background:rgba(var(--accent-info-rgb),0.15); color:var(--accent-info); font-size:0.65rem;">Slow &lt;12</span>
                                    <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.15); color:var(--accent-danger); font-size:0.65rem;">Fast &gt;20</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- SpO2 --}}
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 hover-lift h-100" style="background:rgba(var(--accent-info-rgb),0.08); border-left:3px solid var(--accent-info) !important;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-droplet-half me-2" style="color:var(--accent-info); font-size:1.1rem;"></i>
                                    <strong style="font-size:0.8rem;">SpO₂</strong>
                                </div>
                                <div style="font-size:1.3rem; font-weight:700; color:var(--text-primary);">95 – 100</div>
                                <small style="color:var(--text-muted);">% &bull; Oxygen saturation</small>
                                <div class="mt-1" style="font-size:0.7rem; color:var(--text-secondary);">
                                    <span class="badge-glass me-1" style="background:rgba(var(--accent-success-rgb),0.15); color:var(--accent-success); font-size:0.65rem;">Normal &ge;95%</span>
                                    <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.15); color:var(--accent-danger); font-size:0.65rem;">Low &lt;90%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Weight --}}
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 hover-lift h-100" style="background:rgba(var(--accent-secondary-rgb),0.08); border-left:3px solid var(--accent-secondary) !important;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-speedometer me-2" style="color:var(--accent-secondary); font-size:1.1rem;"></i>
                                    <strong style="font-size:0.8rem;">BMI Range</strong>
                                </div>
                                <div style="font-size:1.3rem; font-weight:700; color:var(--text-primary);">18.5 – 24.9</div>
                                <small style="color:var(--text-muted);">kg/m&sup2; &bull; Healthy weight</small>
                                <div class="mt-1" style="font-size:0.7rem; color:var(--text-secondary);">
                                    <span class="badge-glass me-1" style="background:rgba(var(--accent-warning-rgb),0.15); color:var(--accent-warning); font-size:0.65rem;">Under &lt;18.5</span>
                                    <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.15); color:var(--accent-danger); font-size:0.65rem;">Over &ge;25</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Height/Weight reference --}}
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 hover-lift h-100" style="background:rgba(var(--accent-primary-rgb),0.05); border-left:3px solid var(--text-muted) !important;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-rulers me-2" style="color:var(--text-muted); font-size:1.1rem;"></i>
                                    <strong style="font-size:0.8rem;">Height (Adult Avg)</strong>
                                </div>
                                <div style="font-size:1.3rem; font-weight:700; color:var(--text-primary);">160 – 175</div>
                                <small style="color:var(--text-muted);">cm &bull; Varies by gender</small>
                                <div class="mt-1" style="font-size:0.7rem; color:var(--text-secondary);">
                                    <span class="badge-glass" style="background:rgba(var(--accent-info-rgb),0.1); color:var(--accent-info); font-size:0.65rem;">Male avg ~170cm</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Blood Glucose (bonus) --}}
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 hover-lift h-100" style="background:rgba(var(--accent-warning-rgb),0.06); border-left:3px solid var(--accent-warning) !important;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-droplet me-2" style="color:var(--accent-warning); font-size:1.1rem;"></i>
                                    <strong style="font-size:0.8rem;">Blood Glucose</strong>
                                </div>
                                <div style="font-size:1.3rem; font-weight:700; color:var(--text-primary);">70 – 100</div>
                                <small style="color:var(--text-muted);">mg/dL &bull; Fasting</small>
                                <div class="mt-1" style="font-size:0.7rem; color:var(--text-secondary);">
                                    <span class="badge-glass me-1" style="background:rgba(var(--accent-info-rgb),0.15); color:var(--accent-info); font-size:0.65rem;">Low &lt;70</span>
                                    <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.15); color:var(--accent-danger); font-size:0.65rem;">High &gt;126</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Vitals Form --}}
    <div class="card fade-in delay-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-heart-pulse me-2" style="color:var(--accent-danger);"></i>Vital Signs</span>
            {{-- Quick-fill toolbar --}}
            <div class="d-flex align-items-center gap-2">
                <span id="prioritySuggestion" class="badge d-none" style="font-size:0.75rem;"></span>
                <button type="button" id="fillNormalBtn" class="btn btn-sm btn-outline-success" title="Fill all vitals with typical normal values">
                    <i class="bi bi-lightning-fill me-1"></i>All Normal
                </button>
                <button type="button" id="clearVitalsBtn" class="btn btn-sm btn-outline-secondary" title="Clear all vital fields">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </button>
            </div>
        </div>
        <div class="card-body">
            <form id="vitalsForm" action="{{ route('triage.patients.save-vitals', $patient->id) }}" method="POST">
                @csrf
                <input type="hidden" name="send_to_doctor" id="sendToDoctorFlag" value="0">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="blood_pressure" class="form-label">Blood Pressure</label>
                        <input type="text" class="form-control @error('blood_pressure') is-invalid @enderror" id="blood_pressure" name="blood_pressure" placeholder="e.g., 120/80" value="{{ old('blood_pressure') }}" autocomplete="off">
                        @error('blood_pressure')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="temperature" class="form-label">Temperature (°C)</label>
                        <input type="number" step="0.1" class="form-control @error('temperature') is-invalid @enderror" id="temperature" name="temperature" placeholder="e.g., 36.6" value="{{ old('temperature') }}">
                        @error('temperature')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="heart_rate" class="form-label">Heart Rate (bpm)</label>
                        <input type="number" class="form-control @error('heart_rate') is-invalid @enderror" id="heart_rate" name="heart_rate" placeholder="e.g., 72" value="{{ old('heart_rate') }}">
                        @error('heart_rate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="respiratory_rate" class="form-label">Respiratory Rate (breaths/min)</label>
                        <input type="number" class="form-control @error('respiratory_rate') is-invalid @enderror" id="respiratory_rate" name="respiratory_rate" placeholder="e.g., 16" value="{{ old('respiratory_rate') }}">
                        @error('respiratory_rate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="weight" class="form-label">Weight (kg)</label>
                        <input type="number" step="0.1" class="form-control @error('weight') is-invalid @enderror" id="weight" name="weight" placeholder="e.g., 70" value="{{ old('weight') }}">
                        @error('weight')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="height" class="form-label">Height (cm)</label>
                        <input type="number" step="0.1" class="form-control @error('height') is-invalid @enderror" id="height" name="height" placeholder="e.g., 170" value="{{ old('height') }}">
                        @error('height')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="oxygen_saturation" class="form-label">SpO₂ (%)</label>
                        <input type="number" step="0.1" class="form-control @error('oxygen_saturation') is-invalid @enderror" id="oxygen_saturation" name="oxygen_saturation" placeholder="e.g., 98" value="{{ old('oxygen_saturation') }}">
                        @error('oxygen_saturation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Chief Complaint — chips + datalist --}}
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label for="chief_complaint" class="form-label">Chief Complaint</label>
                        {{-- Common complaint chips --}}
                        <div class="d-flex flex-wrap gap-1 mb-2" id="complaintChips">
                            @foreach([
                                'Chest pain','Shortness of breath','Fever','Headache','Abdominal pain',
                                'Nausea / vomiting','Back pain','Dizziness','Cough','Sore throat',
                                'Leg pain / swelling','Palpitations','Fainting','Eye pain / redness',
                                'Ear pain','Rash / skin lesion','Urinary complaint','Anxiety / panic',
                                'Trauma / injury','Weakness / fatigue',
                            ] as $complaint)
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary complaint-chip-btn"
                                    data-complaint="{{ $complaint }}">{{ $complaint }}</button>
                            @endforeach
                        </div>
                        <input type="text"
                               class="form-control @error('chief_complaint') is-invalid @enderror"
                               id="chief_complaint"
                               name="chief_complaint"
                               placeholder="Select above or type a complaint…"
                               value="{{ old('chief_complaint') }}"
                               list="complaintList"
                               autocomplete="off">
                        <datalist id="complaintList">
                            @foreach([
                                'Chest pain','Shortness of breath','Fever','Headache','Abdominal pain',
                                'Nausea / vomiting','Back pain','Dizziness','Cough','Sore throat',
                                'Leg pain / swelling','Palpitations','Fainting','Eye pain / redness',
                                'Ear pain','Rash / skin lesion','Urinary complaint','Anxiety / panic',
                                'Trauma / injury','Weakness / fatigue',
                            ] as $complaint)
                            <option value="{{ $complaint }}">
                            @endforeach
                        </datalist>
                        @error('chief_complaint')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Priority + auto-suggest --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="priority" class="form-label d-flex align-items-center gap-2">
                            Priority
                            <span id="prioritySuggestBadge" class="badge d-none" style="font-size:0.7rem; cursor:pointer;" title="Click to apply suggestion"></span>
                        </label>
                        <select class="form-select @error('priority') is-invalid @enderror" id="priority" name="priority">
                            <option value="">Select priority</option>
                            <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Low</option>
                            <option value="normal" {{ old('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
                            <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>High</option>
                            <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                            <option value="critical" {{ old('priority') === 'critical' ? 'selected' : '' }}>Critical</option>
                            <option value="emergency" {{ old('priority') === 'emergency' ? 'selected' : '' }}>Emergency</option>
                        </select>
                        @error('priority')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Notes + STT --}}
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <label for="notes" class="form-label mb-0">Notes</label>
                        <button type="button"
                                id="triageSttBtn"
                                class="btn btn-sm btn-outline-secondary ms-auto"
                                title="Dictate notes (speech to text)"
                                style="display:none;">
                            <i class="bi bi-mic" id="triageSttIcon"></i>
                            <span id="triageSttLabel">Dictate</span>
                        </button>
                        <span id="triageSttStatus"
                              class="badge bg-danger"
                              style="display:none; font-size:0.7rem;">● REC</span>
                    </div>
                    <textarea class="form-control @error('notes') is-invalid @enderror"
                              id="notes"
                              name="notes"
                              rows="4"
                              placeholder="Additional observations …">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="glass-divider mb-3"></div>

                {{-- Action buttons --}}
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="submit" id="btnSaveOnly" class="btn btn-outline-primary">
                        <i class="bi bi-floppy me-1"></i>Save Vitals
                    </button>
                    <button type="button" id="btnSaveAndSend" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Save &amp; Send to Doctor
                    </button>
                    <a href="{{ route('triage.patients.index') }}" class="btn btn-outline-secondary ms-auto">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form     = document.getElementById('vitalsForm');
    var flagEl   = document.getElementById('sendToDoctorFlag');
    var btnSend  = document.getElementById('btnSaveAndSend');

    // ── Save & Send to Doctor ───────────────────────────────────────────────
    btnSend.addEventListener('click', function () {
        flagEl.value = '1';
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });

    // Reset flag if Save Only is used (in case Send was clicked then user changed mind)
    document.getElementById('btnSaveOnly').addEventListener('click', function () {
        flagEl.value = '0';
    });

    // ── All Normal quick-fill ───────────────────────────────────────────────
    document.getElementById('fillNormalBtn').addEventListener('click', function () {
        document.getElementById('blood_pressure').value    = '120/80';
        document.getElementById('temperature').value       = '36.6';
        document.getElementById('heart_rate').value        = '72';
        document.getElementById('respiratory_rate').value  = '16';
        document.getElementById('oxygen_saturation').value = '98';
        var pri = document.getElementById('priority');
        if (!pri.value) pri.value = 'normal';
        updatePrioritySuggestion();
    });

    // ── Clear button ────────────────────────────────────────────────────────
    document.getElementById('clearVitalsBtn').addEventListener('click', function () {
        ['blood_pressure','temperature','heart_rate','respiratory_rate',
         'weight','height','oxygen_saturation','chief_complaint','notes'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('priority').value = '';
        hidePrioritySuggestion();
    });

    // ── Complaint chips ─────────────────────────────────────────────────────
    document.querySelectorAll('.complaint-chip-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('chief_complaint').value = btn.dataset.complaint;
            // Highlight selected chip
            document.querySelectorAll('.complaint-chip-btn').forEach(function (b) {
                b.classList.remove('btn-secondary');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-secondary');
        });
    });

    // Pre-select chip if field already has a value (e.g. after validation error)
    (function () {
        var existing = document.getElementById('chief_complaint').value.trim();
        if (!existing) return;
        document.querySelectorAll('.complaint-chip-btn').forEach(function (btn) {
            if (btn.dataset.complaint === existing) {
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-secondary');
            }
        });
    }());

    // ── Auto-priority suggestion ────────────────────────────────────────────
    var vitalIds = ['blood_pressure','temperature','heart_rate','respiratory_rate','oxygen_saturation'];
    vitalIds.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', updatePrioritySuggestion);
        if (el) el.addEventListener('change', updatePrioritySuggestion);
    });

    var badge = document.getElementById('prioritySuggestBadge');
    badge.addEventListener('click', function () {
        if (badge.dataset.value) {
            document.getElementById('priority').value = badge.dataset.value;
            hidePrioritySuggestion();
        }
    });

    function hidePrioritySuggestion() {
        badge.classList.add('d-none');
    }

    function updatePrioritySuggestion() {
        var suggested = computeSuggestedPriority();
        if (!suggested) { hidePrioritySuggestion(); return; }

        var colors = {
            emergency: ['bg-danger','Emergency'],
            critical:  ['bg-danger','Critical'],
            urgent:    ['bg-warning text-dark','Urgent'],
            high:      ['bg-warning text-dark','High'],
            normal:    ['bg-success','Normal'],
            low:       ['bg-secondary','Low'],
        };
        var info = colors[suggested] || ['bg-secondary', suggested];
        badge.className = 'badge ' + info[0];
        badge.textContent = 'Suggest: ' + info[1] + ' ↑ Apply';
        badge.dataset.value = suggested;
        badge.classList.remove('d-none');
    }

    function computeSuggestedPriority() {
        var bp    = document.getElementById('blood_pressure').value;
        var temp  = parseFloat(document.getElementById('temperature').value);
        var hr    = parseInt(document.getElementById('heart_rate').value);
        var rr    = parseInt(document.getElementById('respiratory_rate').value);
        var spo2  = parseFloat(document.getElementById('oxygen_saturation').value);

        var score = 0; // 0=low, 1=normal, 2=high, 3=urgent, 4=critical, 5=emergency

        // SpO2
        if (!isNaN(spo2)) {
            if (spo2 < 85) score = Math.max(score, 5);
            else if (spo2 < 90) score = Math.max(score, 4);
            else if (spo2 < 94) score = Math.max(score, 3);
        }

        // Heart rate
        if (!isNaN(hr)) {
            if (hr < 40 || hr > 150) score = Math.max(score, 5);
            else if (hr < 50 || hr > 130) score = Math.max(score, 4);
            else if (hr < 55 || hr > 110) score = Math.max(score, 2);
        }

        // Temperature
        if (!isNaN(temp)) {
            if (temp > 40 || temp < 35) score = Math.max(score, 4);
            else if (temp > 39 || temp < 36) score = Math.max(score, 2);
        }

        // Blood pressure
        if (bp) {
            var parts = bp.split('/');
            var sys = parseInt(parts[0]);
            var dia = parseInt(parts[1]);
            if (!isNaN(sys)) {
                if (sys > 200 || sys < 70) score = Math.max(score, 5);
                else if (sys > 180 || sys < 80) score = Math.max(score, 4);
                else if (sys > 160 || sys < 90) score = Math.max(score, 3);
            }
            if (!isNaN(dia) && dia > 120) score = Math.max(score, 4);
        }

        // Respiratory rate
        if (!isNaN(rr)) {
            if (rr < 8 || rr > 30) score = Math.max(score, 4);
            else if (rr < 10 || rr > 25) score = Math.max(score, 3);
        }

        // No vitals entered yet
        var anyEntered = bp || !isNaN(temp) || !isNaN(hr) || !isNaN(rr) || !isNaN(spo2);
        if (!anyEntered) return null;

        var map = ['low','normal','high','urgent','critical','emergency'];
        return map[score] || 'normal';
    }

    // ── Speech-to-text for notes ────────────────────────────────────────────
    (function () {
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) return;

        var btn    = document.getElementById('triageSttBtn');
        var icon   = document.getElementById('triageSttIcon');
        var label  = document.getElementById('triageSttLabel');
        var status = document.getElementById('triageSttStatus');
        var ta     = document.getElementById('notes');

        if (!btn || !ta) return;
        btn.style.display = '';

        var recognition = new SR();
        recognition.continuous     = true;
        recognition.interimResults = true;
        recognition.lang           = 'en-US';
        var isListening = false;

        recognition.onresult = function (event) {
            var interim = '', finalText = '';
            for (var i = event.resultIndex; i < event.results.length; i++) {
                if (event.results[i].isFinal) {
                    finalText += event.results[i][0].transcript;
                } else {
                    interim += event.results[i][0].transcript;
                }
            }
            if (finalText) {
                var cur = ta.value;
                ta.value = cur + (cur && !cur.endsWith(' ') && !cur.endsWith('\n') ? ' ' : '') + finalText;
            }
            status.textContent = interim
                ? ('● ' + interim.substring(0, 40) + (interim.length > 40 ? '…' : ''))
                : '● REC';
        };

        recognition.onerror = function (event) {
            if (event.error === 'no-speech') return;
            isListening = false;
            setIdle();
            if (event.error === 'not-allowed') {
                alert('Microphone access denied. Please allow microphone permission to use dictation.');
            }
        };

        recognition.onend = function () {
            if (isListening) {
                try { recognition.start(); } catch (e) {}
            } else {
                setIdle();
            }
        };

        function setIdle() {
            icon.className = 'bi bi-mic';
            label.textContent = 'Dictate';
            status.style.display = 'none';
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-outline-secondary');
        }

        function setRecording() {
            icon.className = 'bi bi-mic-fill';
            label.textContent = 'Stop';
            status.style.display = '';
            status.textContent = '● REC';
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-danger');
        }

        btn.addEventListener('click', function () {
            if (isListening) {
                isListening = false;
                recognition.stop();
            } else {
                isListening = true;
                setRecording();
                try { recognition.start(); } catch(e) {}
            }
        });
    }());

    // ── Critical vitals confirmation modal ──────────────────────────────────
    var modal = document.createElement('div');
    modal.innerHTML = `
        <div id="criticalVitalsModal" class="modal fade" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-danger">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Critical Values Detected</h5>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">The following critical vital sign values were entered:</p>
                        <ul id="criticalValuesList" class="mb-3 text-danger fw-semibold"></ul>
                        <p class="mb-0">Do you want to save these values and proceed?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Go Back &amp; Review</button>
                        <button type="button" id="confirmCriticalSave" class="btn btn-danger">Yes, Save &amp; Proceed</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);

    var bsModal = new bootstrap.Modal(document.getElementById('criticalVitalsModal'));

    document.getElementById('confirmCriticalSave').addEventListener('click', function () {
        bsModal.hide();
        form.dataset.confirmed = '1';
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });

    form.addEventListener('submit', function (e) {
        if (form.dataset.confirmed === '1') return;

        var alerts = [];

        var bpField = form.querySelector('[name="blood_pressure"]');
        if (bpField && bpField.value) {
            var parts = bpField.value.split('/');
            var systolic  = parseInt(parts[0]);
            var diastolic = parseInt(parts[1]);
            if (!isNaN(systolic)) {
                if (systolic > 180) alerts.push('Blood pressure systolic ' + systolic + ' mmHg — severely elevated (>180)');
                if (systolic < 90)  alerts.push('Blood pressure systolic ' + systolic + ' mmHg — hypotension (<90)');
            }
            if (!isNaN(diastolic) && diastolic > 120) alerts.push('Blood pressure diastolic ' + diastolic + ' mmHg — severely elevated (>120)');
        }

        var o2Field = form.querySelector('[name="oxygen_saturation"]');
        if (o2Field && o2Field.value !== '') {
            var o2 = parseFloat(o2Field.value);
            if (!isNaN(o2) && o2 < 92) alerts.push('Oxygen saturation ' + o2 + '% — critically low (<92%)');
        }

        var hrField = form.querySelector('[name="heart_rate"]');
        if (hrField && hrField.value !== '') {
            var hr = parseInt(hrField.value);
            if (!isNaN(hr)) {
                if (hr < 50)  alerts.push('Heart rate ' + hr + ' bpm — bradycardia (<50)');
                if (hr > 120) alerts.push('Heart rate ' + hr + ' bpm — tachycardia (>120)');
            }
        }

        var tempField = form.querySelector('[name="temperature"]');
        if (tempField && tempField.value !== '') {
            var temp = parseFloat(tempField.value);
            if (!isNaN(temp)) {
                if (temp > 39) alerts.push('Temperature ' + temp + '°C — fever (>39°C)');
                if (temp < 36) alerts.push('Temperature ' + temp + '°C — hypothermia (<36°C)');
            }
        }

        if (alerts.length > 0) {
            e.preventDefault();
            document.getElementById('criticalValuesList').innerHTML = alerts.map(function(a){ return '<li>' + a + '</li>'; }).join('');
            bsModal.show();
        }
    });
});
</script>
@endpush

@endsection
