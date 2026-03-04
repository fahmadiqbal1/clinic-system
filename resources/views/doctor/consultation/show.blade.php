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
        <div class="card-header"><i class="bi bi-pencil-square me-2" style="color:var(--accent-warning);"></i>Consultation Notes</div>
        <div class="card-body">
            @if($patient->status === 'with_doctor')
                <form action="{{ route('doctor.consultation.save-notes', $patient) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <textarea name="consultation_notes" class="form-control" rows="5" placeholder="Enter consultation notes..." required minlength="3">{{ old('consultation_notes', $patient->consultation_notes) }}</textarea>
                        @error('consultation_notes')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Notes</button>
                </form>
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
    <div class="card mb-4 fade-in delay-4">
        <div class="card-header"><i class="bi bi-list-ul me-2" style="color:var(--accent-info);"></i>Invoices for this Patient</div>
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
                        @foreach($invoices as $inv)
                            <tr>
                                <td style="color:var(--text-muted);">{{ $inv->id }}</td>
                                <td>{{ ucfirst($inv->department) }}</td>
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

    {{-- MedGemma AI Second Opinion --}}
    @include('components.ai-analysis.card', [
        'analyses' => $aiAnalyses,
        'formAction' => route('ai-analysis.consultation', $patient),
        'contextLabel' => 'consultation',
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
@endsection
