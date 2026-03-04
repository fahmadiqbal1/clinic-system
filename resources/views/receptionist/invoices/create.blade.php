@extends('layouts.app')
@section('title', 'Create Invoice — ' . config('app.name'))

@section('content')
<div class="container py-4" style="max-width:800px;">
    {{-- Page Header --}}
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-plus-circle me-2"></i>Create Invoice</h1>
            <p class="text-muted mb-0">Create a new invoice for patient services</p>
        </div>
        <a href="{{ route('receptionist.invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="glass-panel">
        <form action="{{ route('receptionist.invoices.store') }}" method="POST">
            @csrf

            {{-- Patient & Type --}}
            <div class="form-section">
                <h6 class="form-section-title"><i class="bi bi-person me-2"></i>Patient Information</h6>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                        <select name="patient_id" id="patient_id" class="form-select" required>
                            <option value="">Select a patient</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}" {{ old('patient_id') == $patient->id ? 'selected' : '' }}>
                                    {{ $patient->full_name ?? 'Unknown' }} - ID: {{ $patient->id }}
                                </option>
                            @endforeach
                        </select>
                        @error('patient_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Patient Type <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="patient_type" id="patient_type_clinic" value="clinic" {{ old('patient_type', 'clinic') === 'clinic' ? 'checked' : '' }} required>
                                <label class="form-check-label" for="patient_type_clinic">Clinic</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="patient_type" id="patient_type_walk_in" value="walk_in" {{ old('patient_type') === 'walk_in' ? 'checked' : '' }}>
                                <label class="form-check-label" for="patient_type_walk_in">Walk-in</label>
                            </div>
                        </div>
                        @error('patient_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Department & Service --}}
            <div class="form-section">
                <h6 class="form-section-title"><i class="bi bi-building me-2"></i>Service Details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                        <select name="department" id="department" class="form-select" required>
                            <option value="">Select department</option>
                            <option value="lab" {{ old('department') == 'lab' ? 'selected' : '' }}>🧪 Laboratory</option>
                            <option value="radiology" {{ old('department') == 'radiology' ? 'selected' : '' }}>📡 Radiology</option>
                            <option value="pharmacy" {{ old('department') == 'pharmacy' ? 'selected' : '' }}>💊 Pharmacy</option>
                            <option value="consultation" {{ old('department') == 'consultation' ? 'selected' : '' }}>🩺 Consultation</option>
                        </select>
                        @error('department')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="service_name" class="form-label">Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="service_name" id="service_name" value="{{ old('service_name') }}" class="form-control" placeholder="e.g., Blood Test, X-Ray, Medication" required>
                        @error('service_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Pricing & Doctor --}}
            <div class="form-section">
                <h6 class="form-section-title"><i class="bi bi-cash-coin me-2"></i>Pricing &amp; Assignment</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="total_amount" class="form-label">Total Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                            <input type="number" name="total_amount" id="total_amount" value="{{ old('total_amount') }}" step="0.01" class="form-control" placeholder="0.00" required>
                        </div>
                        @error('total_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="prescribing_doctor_id" class="form-label">Prescribing Doctor <span class="text-danger" id="doctor_required_star" style="display:none;">*</span></label>
                        <select name="prescribing_doctor_id" id="prescribing_doctor_id" class="form-select">
                            <option value="">Select doctor</option>
                            @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}" {{ old('prescribing_doctor_id') == $doctor->id ? 'selected' : '' }}>
                                    {{ $doctor->name ?? 'Unknown' }}
                                </option>
                            @endforeach
                        </select>
                        @error('prescribing_doctor_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="has_prescribed_items" id="has_prescribed_items" value="1" {{ old('has_prescribed_items') ? 'checked' : '' }}>
                            <label class="form-check-label" for="has_prescribed_items">Has Prescribed Items</label>
                        </div>
                        @error('has_prescribed_items')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Referrer (Optional) --}}
            <div class="form-section">
                <h6 class="form-section-title"><i class="bi bi-share me-2"></i>Referrer Information <span class="text-muted small fw-normal">(Optional)</span></h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="referrer_name" class="form-label">Referrer Name</label>
                        <input type="text" name="referrer_name" id="referrer_name" value="{{ old('referrer_name') }}" class="form-control" placeholder="Optional">
                        @error('referrer_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="referrer_percentage" class="form-label">Referrer Percentage</label>
                        <div class="input-group">
                            <input type="number" name="referrer_percentage" id="referrer_percentage" value="{{ old('referrer_percentage') }}" step="0.01" class="form-control" placeholder="0.00">
                            <span class="input-group-text">%</span>
                        </div>
                        @error('referrer_percentage')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,0.06);">
                <a href="{{ route('receptionist.invoices.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Create Invoice
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deptSelect = document.getElementById('department');
    const doctorStar = document.getElementById('doctor_required_star');
    const doctorSelect = document.getElementById('prescribing_doctor_id');

    function updateDoctorRequired() {
        const dept = deptSelect.value;
        const required = ['lab', 'radiology', 'pharmacy'].includes(dept);
        doctorStar.style.display = required ? 'inline' : 'none';
        doctorSelect.required = required;
    }

    deptSelect.addEventListener('change', updateDoctorRequired);
    updateDoctorRequired();
});
</script>
@endsection
