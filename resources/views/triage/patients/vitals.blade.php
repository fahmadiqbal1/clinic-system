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
        <div class="card-header"><i class="bi bi-heart-pulse me-2" style="color:var(--accent-danger);"></i>Vital Signs</div>
        <div class="card-body">
            <form action="{{ route('triage.patients.save-vitals', $patient->id) }}" method="POST">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="blood_pressure" class="form-label">Blood Pressure</label>
                        <input type="text" class="form-control @error('blood_pressure') is-invalid @enderror" id="blood_pressure" name="blood_pressure" placeholder="e.g., 120/80" value="{{ old('blood_pressure') }}">
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

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="chief_complaint" class="form-label">Chief Complaint</label>
                        <input type="text" class="form-control @error('chief_complaint') is-invalid @enderror" id="chief_complaint" name="chief_complaint" placeholder="Primary reason for visit" value="{{ old('chief_complaint') }}">
                        @error('chief_complaint')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="priority" class="form-label">Priority</label>
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

                <div class="mb-4">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="4" placeholder="Additional observations ...">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="glass-divider mb-3"></div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Vitals & Send to Doctor</button>
                    <a href="{{ route('triage.patients.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
