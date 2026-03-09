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
                    <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="cnic" class="form-label"><i class="bi bi-credit-card me-1" style="color:var(--accent-info);"></i>CNIC</label>
                    <input type="text" class="form-control @error('cnic') is-invalid @enderror" id="cnic" name="cnic"
                           value="{{ old('cnic') }}" placeholder="e.g. 12345-1234567-1">
                    @error('cnic')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Required for FBR digital invoicing. Format: XXXXX-XXXXXXX-X</div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="gender" class="form-label"><i class="bi bi-gender-ambiguous me-1" style="color:var(--accent-warning);"></i>Gender <span class="text-danger">*</span></label>
                    <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" {{ old('gender') === 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other" {{ old('gender') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('gender')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
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
                    <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select class="form-select @error('payment_method') is-invalid @enderror" id="payment_method" name="payment_method" required>
                        <option value="cash" {{ old('payment_method', 'cash') === 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="card" {{ old('payment_method') === 'card' ? 'selected' : '' }}>Card</option>
                        <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Bank Transfer</option>
                    </select>
                    @error('payment_method')
                        <div class="invalid-feedback">{{ $message }}</div>
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

@push('scripts')
<script>
    // Auto-populate consultation fee when service is selected
    document.getElementById('service_catalog_id').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const price = selected.dataset.price || '';
        document.getElementById('consultation_fee').value = price;
    });

    // Trigger on page load if service was pre-selected (old value)
    (function() {
        const svcSelect = document.getElementById('service_catalog_id');
        if (svcSelect.value) {
            const selected = svcSelect.options[svcSelect.selectedIndex];
            const oldFee = document.getElementById('consultation_fee').value;
            if (!oldFee) {
                document.getElementById('consultation_fee').value = selected.dataset.price || '';
            }
        }
    })();
</script>
@endpush
