@extends('layouts.app')
@section('title', 'Consultation — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    {{-- Page Header --}}
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-journal-medical me-2" style="color:var(--accent-primary);"></i>Consultation — {{ $patient->first_name }} {{ $patient->last_name }}</h2>
            <p class="page-subtitle mb-0">Patient #{{ $patient->id }}</p>
        </div>
        <a href="{{ route('doctor.patients.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Patients</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success fade-in">{{ session('success') }}</div>
    @endif

    {{-- Patient Details --}}
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-person-badge me-2" style="color:var(--accent-info);"></i>Patient Details</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px,1fr));">
                <div class="info-grid-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value">{{ $patient->phone ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Gender</span>
                    <span class="info-value">{{ $patient->gender }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value">{{ $patient->date_of_birth?->format('d/m/Y') ?? 'N/A' }}</span>
                </div>
                <div class="info-grid-item">
                    <span class="info-label">Status</span>
                    @php
                        $sStyle = match($patient->status) {
                            'with_doctor' => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                            'completed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                            default => '',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $sStyle }}">{{ ucfirst(str_replace('_', ' ', $patient->status)) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Triage Vitals --}}
    @if($latestVitals)
    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-heart-pulse me-2" style="color:var(--accent-danger);"></i>Triage Vitals</div>
        <div class="card-body">
            <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px,1fr));">
                @if($latestVitals->blood_pressure)
                <div class="info-grid-item">
                    <span class="info-label">Blood Pressure</span>
                    <span class="info-value">{{ $latestVitals->blood_pressure }} mmHg</span>
                </div>
                @endif
                @if($latestVitals->temperature)
                <div class="info-grid-item">
                    <span class="info-label">Temperature</span>
                    <span class="info-value">{{ $latestVitals->temperature }}°C</span>
                </div>
                @endif
                @if($latestVitals->pulse_rate)
                <div class="info-grid-item">
                    <span class="info-label">Heart Rate</span>
                    <span class="info-value">{{ $latestVitals->pulse_rate }} bpm</span>
                </div>
                @endif
                @if($latestVitals->respiratory_rate)
                <div class="info-grid-item">
                    <span class="info-label">Resp. Rate</span>
                    <span class="info-value">{{ $latestVitals->respiratory_rate }} br/min</span>
                </div>
                @endif
                @if($latestVitals->oxygen_saturation)
                <div class="info-grid-item">
                    <span class="info-label">SpO₂</span>
                    <span class="info-value">{{ $latestVitals->oxygen_saturation }}%</span>
                </div>
                @endif
                @if($latestVitals->weight)
                <div class="info-grid-item">
                    <span class="info-label">Weight</span>
                    <span class="info-value">{{ $latestVitals->weight }} kg</span>
                </div>
                @endif
                @if($latestVitals->height)
                <div class="info-grid-item">
                    <span class="info-label">Height</span>
                    <span class="info-value">{{ $latestVitals->height }} cm</span>
                </div>
                @endif
                @if($latestVitals->chief_complaint)
                <div class="info-grid-item" style="grid-column: span 2;">
                    <span class="info-label">Chief Complaint</span>
                    <span class="info-value" style="color:var(--accent-warning);">{{ $latestVitals->chief_complaint }}</span>
                </div>
                @endif
                @if($latestVitals->priority)
                <div class="info-grid-item">
                    <span class="info-label">Priority</span>
                    @php
                        $prioStyle = match($latestVitals->priority) {
                            'low' => 'background:rgba(var(--accent-secondary-rgb),0.15);color:var(--accent-secondary);',
                            'normal' => 'background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);',
                            'high' => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                            'urgent','critical','emergency' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                            default => '',
                        };
                    @endphp
                    <span class="badge-glass" style="{{ $prioStyle }}">{{ ucfirst($latestVitals->priority) }}</span>
                </div>
                @endif
            </div>
            @if($latestVitals->notes)
                <div class="mt-2 p-2 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                    <small style="color:var(--text-muted);">Triage Notes:</small>
                    <div style="color:var(--text-secondary);">{{ $latestVitals->notes }}</div>
                </div>
            @endif
            <small class="d-block mt-1" style="color:var(--text-muted);">Recorded {{ $latestVitals->created_at->diffForHumans() }}</small>
        </div>
    </div>
    @endif

    {{-- Consultation Notes --}}
    <div class="card mb-4 fade-in delay-2">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-pencil-square me-2" style="color:var(--accent-warning);"></i>Consultation Notes</span>
            @if($patient->status === 'with_doctor')
                <button type="button" id="soapToggle" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-layout-text-sidebar me-1"></i>SOAP Template
                </button>
            @endif
        </div>
        <div class="card-body">
            @if($patient->status === 'with_doctor')
                <div id="soapPanel" class="mb-3 p-3 rounded" style="display:none; background:var(--glass-bg); border:1px solid var(--glass-border);">
                    <p class="small fw-semibold mb-2" style="color:var(--text-muted);">Click a section to insert at cursor position:</p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm soap-insert" data-text="S - Subjective:&#10;Chief Complaint: &#10;History of Present Illness: &#10;&#10;">Subjective (S)</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm soap-insert" data-text="O - Objective:&#10;Vital Signs: BP:  / , HR:  bpm, Temp:  °C, SpO₂:  %&#10;Physical Exam: &#10;&#10;">Objective (O)</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm soap-insert" data-text="A - Assessment:&#10;Diagnosis: &#10;Differential Diagnoses: &#10;&#10;">Assessment (A)</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm soap-insert" data-text="P - Plan:&#10;Investigations: &#10;Treatment: &#10;Follow-up: &#10;&#10;">Plan (P)</button>
                        <button type="button" class="btn btn-outline-info btn-sm soap-insert" data-text="S - Subjective:&#10;Chief Complaint: &#10;History of Present Illness: &#10;&#10;O - Objective:&#10;Vital Signs: BP:  / , HR:  bpm, Temp:  °C, SpO₂:  %&#10;Physical Exam: &#10;&#10;A - Assessment:&#10;Diagnosis: &#10;Differential Diagnoses: &#10;&#10;P - Plan:&#10;Investigations: &#10;Treatment: &#10;Follow-up: &#10;&#10;">Full SOAP</button>
                    </div>
                </div>
                <form action="{{ route('doctor.consultation.save-notes', $patient) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <small class="text-muted">Consultation Notes</small>
                            <button type="button" id="sttBtn" class="btn btn-sm btn-outline-secondary ms-auto" title="Dictate notes (speech to text)" style="display:none;">
                                <i class="bi bi-mic" id="sttIcon"></i> <span id="sttLabel">Dictate</span>
                            </button>
                            <span id="sttStatus" class="badge bg-danger" style="display:none; font-size:0.7rem;">● REC</span>
                        </div>
                        <textarea id="consultationNotesTA" name="consultation_notes" class="form-control" rows="8" placeholder="Enter consultation notes, or click Dictate to speak..." required minlength="3">{{ old('consultation_notes', $patient->consultation_notes) }}</textarea>
                        @error('consultation_notes')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <small class="text-muted mt-1 d-block" id="sttHint" style="display:none!important;"></small>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Notes</button>
                </form>

                {{-- Speech-to-Text via Web Speech API (Chrome/Edge) --}}
                <script>
                (function() {
                    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                    if (!SpeechRecognition) return; // Not supported — button stays hidden

                    var btn = document.getElementById('sttBtn');
                    var icon = document.getElementById('sttIcon');
                    var label = document.getElementById('sttLabel');
                    var status = document.getElementById('sttStatus');
                    var ta = document.getElementById('consultationNotesTA');
                    if (btn) btn.style.display = '';

                    var recognition = new SpeechRecognition();
                    recognition.continuous = true;
                    recognition.interimResults = true;
                    recognition.lang = 'en-US';

                    var isListening = false;
                    var interimSpan = '';

                    recognition.onresult = function(event) {
                        var interim = '';
                        var finalText = '';
                        for (var i = event.resultIndex; i < event.results.length; i++) {
                            if (event.results[i].isFinal) {
                                finalText += event.results[i][0].transcript;
                            } else {
                                interim += event.results[i][0].transcript;
                            }
                        }
                        if (finalText) {
                            // Append final transcript to textarea with a space
                            var cur = ta.value;
                            ta.value = cur + (cur && !cur.endsWith(' ') && !cur.endsWith('\n') ? ' ' : '') + finalText;
                        }
                        // Show interim result as placeholder hint
                        status.textContent = interim ? ('● ' + interim.substring(0, 40) + (interim.length > 40 ? '…' : '')) : '● REC';
                    };

                    recognition.onerror = function(event) {
                        if (event.error === 'no-speech') return;
                        isListening = false;
                        setIdle();
                        if (event.error === 'not-allowed') {
                            alert('Microphone access denied. Please allow microphone permission to use dictation.');
                        }
                    };

                    recognition.onend = function() {
                        if (isListening) {
                            // Auto-restart for continuous dictation
                            try { recognition.start(); } catch(e) {}
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

                    btn.addEventListener('click', function() {
                        if (isListening) {
                            isListening = false;
                            recognition.stop();
                            setIdle();
                        } else {
                            isListening = true;
                            setRecording();
                            recognition.start();
                        }
                    });
                })();
                </script>
            @else
                <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                    {!! nl2br(e($patient->consultation_notes ?? 'No notes recorded.')) !!}
                </div>
            @endif
        </div>
    </div>

    {{-- Create Invoice — Catalog Picker --}}
    @if($patient->status === 'with_doctor')
    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-receipt me-2" style="color:var(--accent-success);"></i>Order Services</div>
        <div class="card-body">
            <form action="{{ route('doctor.consultation.create-invoice', $patient) }}" method="POST" id="invoiceForm">
                @csrf

                {{-- Catalog-based services by department --}}
                @foreach($serviceCatalog as $dept => $deptServices)
                    @php
                        $deptLabel = match($dept) {
                            'lab' => 'Laboratory',
                            'radiology' => 'Radiology',
                            'pharmacy' => 'Pharmacy',
                            'consultation' => 'Consultation',
                            default => ucfirst($dept),
                        };
                        $deptIcon = match($dept) {
                            'lab' => 'bi-droplet',
                            'radiology' => 'bi-radioactive',
                            'pharmacy' => 'bi-capsule',
                            'consultation' => 'bi-journal-medical',
                            default => 'bi-tag',
                        };
                        $deptColor = match($dept) {
                            'lab' => 'var(--accent-info)',
                            'radiology' => 'var(--accent-warning)',
                            'pharmacy' => 'var(--accent-success)',
                            'consultation' => 'var(--accent-primary)',
                            default => 'var(--accent-secondary)',
                        };
                        $grouped = $deptServices->groupBy('category');
                    @endphp

                    <div class="mb-3">
                        <h6 class="fw-bold mb-2">
                            <i class="bi {{ $deptIcon }} me-1" style="color:{{ $deptColor }};"></i>{{ $deptLabel }}
                            <span class="badge badge-glass-secondary ms-1 dept-count" data-dept="{{ $dept }}">0 selected</span>
                        </h6>
                        <div class="row g-2">
                            @foreach($grouped as $category => $tests)
                                <div class="col-12">
                                    @if($category)
                                        <small class="fw-semibold" style="color:var(--text-muted);">{{ $category }}</small>
                                    @endif
                                </div>
                                @foreach($tests as $test)
                                    <div class="col-md-6 col-lg-4">
                                        <label class="d-flex align-items-start gap-2 p-2 rounded catalog-item-label" style="cursor:pointer; border:1px solid var(--glass-border); background:var(--glass-bg); transition:all 0.2s;">
                                            <input type="checkbox" name="services[]" value="{{ $test->id }}" class="form-check-input mt-1 catalog-checkbox" data-dept="{{ $dept }}">
                                            <div class="flex-fill">
                                                <div class="fw-medium" style="font-size:0.9rem;">{{ $test->name }}</div>
                                                <div style="font-size:0.78rem; color:var(--text-muted);">
                                                    <span class="code-tag">{{ $test->code }}</span>
                                                    @if($test->turnaround_time)
                                                        <i class="bi bi-clock ms-1"></i> {{ $test->turnaround_time }}
                                                    @endif
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Manual / Custom Entry (collapsed by default) --}}
                <div class="mt-3 mb-3">
                    <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#manualEntry">
                        <i class="bi bi-pencil-square me-1"></i>Add Custom Service
                    </a>
                    <div class="collapse mt-2" id="manualEntry">
                        <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">Department</label>
                                    <select name="manual_department" class="form-select">
                                        <option value="consultation">Consultation</option>
                                        <option value="lab">Laboratory</option>
                                        <option value="radiology">Radiology</option>
                                        <option value="pharmacy">Pharmacy</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Service / Procedure Name</label>
                                    <input type="text" name="manual_service_name" class="form-control" placeholder="e.g. Follow-up Consultation">
                                </div>
                            </div>
                            <small class="d-block mt-2" style="color:var(--text-muted);"><i class="bi bi-info-circle me-1"></i>Pricing will be set by the receptionist.</small>
                        </div>
                    </div>
                </div>

                {{-- Selection Summary & Submit --}}
                <div class="d-flex justify-content-between align-items-center p-3 rounded" style="background:rgba(var(--accent-success-rgb),0.08); border:1px solid rgba(var(--accent-success-rgb),0.2);">
                    <div>
                        <span class="fw-bold" style="font-size:1.1rem;"><i class="bi bi-check2-square me-1"></i>Selected: <span id="selectedCount" class="glow-primary">0</span> services</span>
                    </div>
                    <button type="submit" class="btn btn-success" id="submitInvoice" disabled onclick="return confirm('Create invoice(s) for the selected services?')">
                        <i class="bi bi-plus-circle me-1"></i>Create Invoice(s)
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.catalog-checkbox');
        const countEl = document.getElementById('selectedCount');
        const submitBtn = document.getElementById('submitInvoice');
        const manualNameEl = document.querySelector('input[name="manual_service_name"]');

        function recalc() {
            let count = 0;
            const deptCounts = {};

            checkboxes.forEach(cb => {
                const dept = cb.dataset.dept;
                if (!deptCounts[dept]) deptCounts[dept] = 0;
                if (cb.checked) {
                    count++;
                    deptCounts[dept]++;
                }
            });

            // Count manual entry if service name is filled
            if (manualNameEl && manualNameEl.value.trim().length > 0) {
                count++;
            }

            countEl.textContent = count;
            submitBtn.disabled = count === 0;

            // Update per-department badges
            document.querySelectorAll('.dept-count').forEach(badge => {
                const c = deptCounts[badge.dataset.dept] || 0;
                badge.textContent = c + ' selected';
                badge.style.display = c > 0 ? 'inline-block' : 'none';
            });
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const label = this.closest('.catalog-item-label');
                if (this.checked) {
                    label.style.borderColor = 'var(--accent-success)';
                    label.style.background = 'rgba(var(--accent-success-rgb),0.08)';
                } else {
                    label.style.borderColor = 'var(--glass-border)';
                    label.style.background = 'var(--glass-bg)';
                }
                recalc();
            });
        });

        if (manualNameEl) {
            manualNameEl.addEventListener('input', recalc);
        }

        recalc();
    });
    </script>
    @endpush
    @endif

    {{-- Existing Prescriptions --}}
    @if($prescriptions->count() > 0)
    <div class="card mb-4 fade-in delay-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-prescription2 me-2" style="color:var(--accent-warning);"></i>Prescriptions</span>
            @if($patient->status === 'with_doctor')
                <a href="{{ route('doctor.prescriptions.create', $patient) }}" class="btn btn-warning btn-sm"><i class="bi bi-plus me-1"></i>New Prescription</a>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medications</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prescriptions as $rx)
                            <tr>
                                <td style="color:var(--text-muted);">{{ $rx->id }}</td>
                                <td>
                                    @foreach($rx->items as $item)
                                        <span class="badge badge-glass-secondary me-1 mb-1" title="Dosage: {{ $item->dosage }}, Freq: {{ $item->frequency }}, Duration: {{ $item->duration }}">
                                            {{ $item->medication_name }} — {{ $item->dosage }}
                                        </span>
                                    @endforeach
                                </td>
                                <td style="color:var(--text-muted);">{{ $rx->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Existing Invoices --}}
    @if($invoices->count() > 0)
    @php
        $labInvoices = $invoices->where('department', 'lab');
        $radInvoices = $invoices->where('department', 'radiology');
        $otherInvoices = $invoices->whereNotIn('department', ['lab', 'radiology']);
        $pendingInvestigations = $invoices->whereIn('department', ['lab', 'radiology'])
            ->filter(fn($inv) => !$inv->isWorkCompleted())->count();
        $completedInvestigations = $invoices->whereIn('department', ['lab', 'radiology'])
            ->filter(fn($inv) => $inv->isWorkCompleted())->count();
        $totalInvestigations = $labInvoices->count() + $radInvoices->count();
    @endphp

    {{-- Investigation Status Summary --}}
    @if($totalInvestigations > 0)
    <div class="card mb-4 fade-in delay-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clipboard2-check me-2" style="color:var(--accent-info);"></i>Investigation Status</span>
            <span>
                @if($pendingInvestigations === 0 && $totalInvestigations > 0)
                    <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);">
                        <i class="bi bi-check-circle me-1"></i>All {{ $totalInvestigations }} investigation(s) complete
                    </span>
                @else
                    <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);">
                        {{ $completedInvestigations }}/{{ $totalInvestigations }} complete
                    </span>
                @endif
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Department</th>
                            <th>Services</th>
                            <th>Status</th>
                            <th>Results</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices->whereIn('department', ['lab', 'radiology']) as $inv)
                            <tr>
                                <td style="color:var(--text-muted);">{{ $inv->id }}</td>
                                <td>
                                    <i class="bi {{ $inv->department === 'lab' ? 'bi-droplet' : 'bi-broadcast' }} me-1" style="color:{{ $inv->department === 'lab' ? 'var(--accent-info)' : 'var(--accent-warning)' }};"></i>
                                    {{ ucfirst($inv->department) }}
                                </td>
                                <td>
                                    @if($inv->items->count() > 0)
                                        @foreach($inv->items as $item)
                                            <span class="badge badge-glass-secondary me-1 mb-1">{{ $item->description }}</span>
                                        @endforeach
                                    @else
                                        {{ $inv->service_name }}
                                    @endif
                                </td>
                                <td>
                                    @if($inv->isWorkCompleted())
                                        <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);"><i class="bi bi-check-circle me-1"></i>Complete</span>
                                    @elseif($inv->performed_by_user_id)
                                        <span class="badge-glass" style="background:rgba(var(--accent-primary-rgb),0.15);color:var(--accent-primary);"><i class="bi bi-gear me-1"></i>In Progress</span>
                                    @elseif($inv->isPaid())
                                        <span class="badge-glass" style="background:rgba(var(--accent-info-rgb),0.15);color:var(--accent-info);">Paid — Awaiting Work</span>
                                    @else
                                        <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);">{{ ucfirst($inv->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($inv->department === 'lab' && $inv->lab_results)
                                        <span class="text-success"><i class="bi bi-table me-1"></i>Results ready</span>
                                    @elseif($inv->department === 'radiology' && $inv->report_text)
                                        <span class="text-success"><i class="bi bi-file-earmark-medical me-1"></i>Report ready</span>
                                    @else
                                        <span style="color:var(--text-muted);"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Lab Results Detail --}}
    @foreach($labInvoices as $labInv)
        @if($labInv->lab_results || $labInv->report_text)
        <div class="card mb-4 fade-in delay-4">
            <div class="card-header">
                <i class="bi bi-droplet me-2" style="color:var(--accent-info);"></i>Lab Results — {{ $labInv->service_name }}
                @if($labInv->isWorkCompleted())
                    <span class="badge-glass ms-2" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);font-size:0.75rem;">Complete</span>
                @endif
            </div>
            <div class="card-body">
                @if($labInv->lab_results)
                    @php
                        $rawResults = $labInv->lab_results;
                        $isFlat = is_array($rawResults) && array_is_list($rawResults);
                        $grouped = $isFlat && count($rawResults) > 0 ? ['general' => $rawResults] : (array) $rawResults;
                        $labItemMap = $labInv->items->keyBy('id');
                    @endphp
                    @foreach($grouped as $key => $results)
                        @php
                            $sectionLabel = $key === 'general'
                                ? ($labInv->service_name ?? 'Results')
                                : ($labItemMap[$key]->description ?? $labItemMap[$key]->serviceCatalog?->name ?? 'Test');
                        @endphp
                        <h6 class="fw-semibold mb-2"><i class="bi bi-clipboard2-pulse me-1" style="color:var(--accent-info);"></i>{{ $sectionLabel }}</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Parameter</th><th>Result</th><th>Unit</th><th>Reference</th></tr></thead>
                                <tbody>
                                    @foreach((array) $results as $r)
                                    <tr>
                                        <td class="fw-medium">{{ $r['test_name'] ?? '' }}</td>
                                        <td class="fw-semibold" style="color:var(--accent-primary);">{{ $r['result'] ?? '' }}</td>
                                        <td style="color:var(--text-muted);">{{ $r['unit'] ?? '—' }}</td>
                                        <td style="color:var(--text-muted);">{{ $r['reference_range'] ?? '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                @endif
                @if($labInv->report_text)
                    <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                        <small class="fw-semibold d-block mb-1" style="color:var(--text-muted);">Lab Technician Report:</small>
                        {!! nl2br(e($labInv->report_text)) !!}
                    </div>
                @endif
            </div>
        </div>
        @endif
    @endforeach

    {{-- Radiology Results Detail --}}
    @foreach($radInvoices as $radInv)
        @if($radInv->report_text || ($radInv->radiology_images && count($radInv->radiology_images) > 0))
        <div class="card mb-4 fade-in delay-4">
            <div class="card-header">
                <i class="bi bi-broadcast me-2" style="color:var(--accent-warning);"></i>Radiology — {{ $radInv->service_name }}
                @if($radInv->isWorkCompleted())
                    <span class="badge-glass ms-2" style="background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);font-size:0.75rem;">Complete</span>
                @endif
            </div>
            <div class="card-body">
                @if($radInv->report_text)
                    <div class="p-3 rounded mb-3" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                        <small class="fw-semibold d-block mb-1" style="color:var(--text-muted);">Radiologist Report:</small>
                        {!! nl2br(e($radInv->report_text)) !!}
                    </div>
                @endif
                @if($radInv->radiology_images && count($radInv->radiology_images) > 0)
                    <div class="row g-3">
                        @foreach($radInv->radiology_images as $idx => $imagePath)
                        <div class="col-md-4 col-lg-3">
                            <div class="rounded overflow-hidden" style="border:1px solid var(--glass-border);">
                                @if(str_ends_with(strtolower($imagePath), '.pdf'))
                                    <a href="{{ Storage::url($imagePath) }}" target="_blank" class="d-flex flex-column align-items-center justify-content-center p-4 text-decoration-none" style="min-height:140px; background:var(--glass-bg);">
                                        <i class="bi bi-file-earmark-pdf" style="font-size:2.5rem; color:var(--accent-danger);"></i>
                                        <span class="small mt-1" style="color:var(--text-muted);">PDF</span>
                                    </a>
                                @else
                                    <a href="{{ Storage::url($imagePath) }}" target="_blank">
                                        <img src="{{ Storage::url($imagePath) }}" alt="Image {{ $idx + 1 }}" class="img-fluid w-100" style="min-height:140px; object-fit:cover;">
                                    </a>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @endif
    @endforeach

    {{-- Other Invoices --}}
    @if($otherInvoices->count() > 0)
    <div class="card mb-4 fade-in delay-4">
        <div class="card-header"><i class="bi bi-list-ul me-2" style="color:var(--accent-info);"></i>Other Invoices</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Department</th>
                            <th>Services</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($otherInvoices as $inv)
                            <tr>
                                <td style="color:var(--text-muted);">{{ $inv->id }}</td>
                                <td>{{ ucfirst($inv->department) }}</td>
                                <td>{{ $inv->service_name }}</td>
                                <td>
                                    @php
                                        $invStyle = match($inv->status) {
                                            'completed' => 'background:rgba(var(--accent-success-rgb),0.15);color:var(--accent-success);',
                                            'paid' => 'background:rgba(var(--accent-primary-rgb),0.15);color:var(--accent-primary);',
                                            'cancelled' => 'background:rgba(var(--accent-danger-rgb),0.15);color:var(--accent-danger);',
                                            default => 'background:rgba(var(--accent-warning-rgb),0.15);color:var(--accent-warning);',
                                        };
                                    @endphp
                                    <span class="badge-glass" style="{{ $invStyle }}">{{ ucfirst($inv->status) }}</span>
                                </td>
                                <td style="color:var(--text-muted);">{{ $inv->created_at->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    @endif

    {{-- Previous Visits --}}
    @if($previousVisits->count() > 0)
    <div class="card mb-4 fade-in delay-4">
        <div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#prevVisitsCollapse">
            <span><i class="bi bi-clock-history me-2" style="color:var(--accent-secondary);"></i>Previous Visits <span class="badge bg-secondary ms-1">{{ $previousVisits->count() }}</span></span>
            <i class="bi bi-chevron-down" id="prevVisitsChevron"></i>
        </div>
        <div id="prevVisitsCollapse" class="collapse">
            <div class="card-body p-0">
                <div class="accordion accordion-flush" id="prevVisitsAccordion">
                    @foreach($previousVisits as $vi => $visit)
                    <div class="accordion-item" style="background:var(--glass-bg); border-color:var(--glass-border);">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#visit-{{ $visit->id }}"
                                    style="background:var(--glass-bg); color:var(--text-primary); font-size:0.9rem;">
                                <span class="me-2"><i class="bi bi-calendar3 me-1" style="color:var(--accent-secondary);"></i>{{ $visit->completed_at?->format('d M Y') ?? $visit->created_at->format('d M Y') }}</span>
                                @if($visit->doctor)
                                    <span class="me-2" style="color:var(--text-muted); font-size:0.85rem;">Dr. {{ $visit->doctor->name }}</span>
                                @endif
                                @if($visit->prescriptions->count() > 0)
                                    <span class="badge-glass ms-auto me-2" style="font-size:0.75rem;">{{ $visit->prescriptions->count() }} Rx</span>
                                @endif
                            </button>
                        </h2>
                        <div id="visit-{{ $visit->id }}" class="accordion-collapse collapse" data-bs-parent="#prevVisitsAccordion">
                            <div class="accordion-body">
                                @if($visit->consultation_notes)
                                    <div class="mb-3">
                                        <small class="fw-semibold d-block mb-1" style="color:var(--text-muted);">Consultation Notes:</small>
                                        <div class="p-2 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border); white-space:pre-wrap; font-size:0.875rem;">{!! e($visit->consultation_notes) !!}</div>
                                    </div>
                                @endif

                                @if($visit->prescriptions->count() > 0)
                                    <div class="mb-3">
                                        <small class="fw-semibold d-block mb-1" style="color:var(--text-muted);">Prescriptions:</small>
                                        @foreach($visit->prescriptions as $rx)
                                            <div class="mb-1 p-2 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                                                @foreach($rx->items as $rxItem)
                                                    <span class="badge-glass me-1" style="font-size:0.8rem;">{{ $rxItem->medication_name }} {{ $rxItem->dosage }}</span>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if($visit->invoices->count() > 0)
                                    <div>
                                        <small class="fw-semibold d-block mb-1" style="color:var(--text-muted);">Services Ordered:</small>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($visit->invoices as $vinv)
                                                <span class="badge-glass" style="font-size:0.8rem;">
                                                    {{ ucfirst($vinv->department) }}: {{ $vinv->service_name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(!$visit->consultation_notes && $visit->prescriptions->count() === 0 && $visit->invoices->count() === 0)
                                    <p class="mb-0" style="color:var(--text-muted); font-size:0.875rem;">No detailed records for this visit.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- MedGemma AI Second Opinion --}}
    @php
        $hasVitals = !empty($latestVitals);
        $hasNotes = !empty($patient->consultation_notes);
        $hasLabResults = $invoices->where('department', 'lab')->filter(fn($i) => !empty($i->lab_results))->count() > 0;
        $hasRadResults = $invoices->where('department', 'radiology')->filter(fn($i) => !empty($i->report_text) || !empty($i->radiology_images))->count() > 0;
        $pendingWork = $invoices->whereIn('department', ['lab', 'radiology'])->filter(fn($i) => !$i->isWorkCompleted())->count();
        $totalWork = $invoices->whereIn('department', ['lab', 'radiology'])->count();

        $dataSummary = [];
        if ($hasVitals) $dataSummary[] = 'vitals';
        if ($hasNotes) $dataSummary[] = 'consultation notes';
        if ($hasLabResults) $dataSummary[] = 'lab results';
        if ($hasRadResults) $dataSummary[] = 'radiology findings';

        if ($totalWork > 0 && $pendingWork > 0) {
            $consultReadinessNote = '<strong>' . $pendingWork . ' investigation(s) still in progress.</strong> You can request analysis now with available data, or wait until all reports are complete for a comprehensive review.<br><small>Available: ' . implode(', ', $dataSummary) . '</small>';
        } elseif ($totalWork > 0 && $pendingWork === 0) {
            $consultReadinessNote = '<strong><i class="bi bi-check-circle me-1"></i>All investigations are complete.</strong> MedGemma will review vitals, notes, lab results, radiology reports' . ($hasRadResults ? ' and images' : '') . ' for a comprehensive second opinion.';
        } elseif (!$hasVitals && !$hasNotes) {
            $consultReadinessNote = '<strong>Tip:</strong> Record vitals and consultation notes first for a meaningful AI analysis.';
        } else {
            $consultReadinessNote = null;
        }
    @endphp
    @include('components.ai-analysis.card', [
        'analyses' => $aiAnalyses,
        'formAction' => route('ai-analysis.consultation', $patient),
        'contextLabel' => 'consultation',
        'readinessNote' => $consultReadinessNote,
        'quickChatAction' => route('ai-analysis.quick-chat', $patient),
    ])

    {{-- Complete / Back --}}
    <div class="d-flex gap-2 fade-in delay-5">
        @if($patient->status === 'with_doctor')
            <a href="{{ route('doctor.prescriptions.create', $patient) }}" class="btn btn-warning">
                <i class="bi bi-prescription2 me-1"></i>Create Prescription
            </a>
            <form action="{{ route('doctor.patients.complete', $patient) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('Complete this consultation?')"><i class="bi bi-check-circle me-1"></i>Complete Consultation</button>
            </form>
        @endif
        <a href="{{ route('doctor.patients.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // SOAP template toggle
    const soapToggle = document.getElementById('soapToggle');
    const soapPanel = document.getElementById('soapPanel');
    const notesTA = document.getElementById('consultationNotesTA');

    if (soapToggle && soapPanel) {
        soapToggle.addEventListener('click', function () {
            soapPanel.style.display = soapPanel.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (notesTA) {
        document.querySelectorAll('.soap-insert').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const text = btn.dataset.text;
                const start = notesTA.selectionStart;
                const end = notesTA.selectionEnd;
                const before = notesTA.value.substring(0, start);
                const after = notesTA.value.substring(end);
                notesTA.value = before + text + after;
                notesTA.selectionStart = notesTA.selectionEnd = start + text.length;
                notesTA.focus();
            });
        });
    }

    // Previous visits chevron rotation
    const prevCollapse = document.getElementById('prevVisitsCollapse');
    const prevChevron = document.getElementById('prevVisitsChevron');
    if (prevCollapse && prevChevron) {
        prevCollapse.addEventListener('show.bs.collapse', function () {
            prevChevron.classList.replace('bi-chevron-down', 'bi-chevron-up');
        });
        prevCollapse.addEventListener('hide.bs.collapse', function () {
            prevChevron.classList.replace('bi-chevron-up', 'bi-chevron-down');
        });
    }
});
</script>
@endpush
@endsection
