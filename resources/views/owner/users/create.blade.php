@extends('layouts.app')
@section('title', 'Create User — ' . config('app.name'))

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            {{-- Page Header --}}
            <div class="page-header mb-4">
                <div>
                    <h1 class="page-title"><i class="bi bi-person-plus me-2"></i>Create New User</h1>
                    <p class="text-muted mb-0">Add a new staff member to the clinic</p>
                </div>
                <a href="{{ route('owner.users.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <div class="glass-panel">
                <form action="{{ route('owner.users.store') }}" method="POST">
                    @csrf

                    {{-- Account Info --}}
                    <div class="form-section">
                        <h6 class="form-section-title"><i class="bi bi-person-badge me-2"></i>Account Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-md-6">
                                <label for="role_id" class="form-label">Role *</label>
                                <select name="role_id" id="role_id" class="form-select @error('role_id') is-invalid @enderror" required>
                                    <option value="">Select a role</option>
                                    @foreach($roles as $role)
                                        @if($role->name !== 'Owner')
                                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Temporary Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="Min 8 characters" required>
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-text">User should change this on first login</div>
                            </div>
                        </div>

                        {{-- Independent Doctor flag (only shown when Doctor role selected) --}}
                        <div id="independent-doctor-field" class="mt-3" style="display:none;">
                            <div class="form-check">
                                <input type="checkbox" name="is_independent" id="is_independent" value="1"
                                    {{ old('is_independent') ? 'checked' : '' }}
                                    class="form-check-input">
                                <label class="form-check-label fw-semibold" for="is_independent">
                                    <i class="bi bi-person-workspace me-1 text-warning"></i>Independent Doctor
                                </label>
                            </div>
                            <div class="form-text">Independent doctors have their own clinic and refer patients for lab, radiology, or pharmacy services only. They <strong>cannot</strong> access OPD consultations, triage, or AI tools.</div>
                        </div>
                    </div>

                    {{-- Compensation --}}
                    <div class="form-section">
                        <h6 class="form-section-title"><i class="bi bi-cash-stack me-2"></i>Compensation</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="compensation_type" class="form-label">Compensation Type *</label>
                                <select name="compensation_type" id="compensation_type" class="form-select @error('compensation_type') is-invalid @enderror" required>
                                    <option value="commission" {{ old('compensation_type', 'commission') === 'commission' ? 'selected' : '' }}>Commission Only</option>
                                    <option value="salaried" {{ old('compensation_type') === 'salaried' ? 'selected' : '' }}>Salaried (no commission)</option>
                                    <option value="hybrid" {{ old('compensation_type') === 'hybrid' ? 'selected' : '' }}>Hybrid (salary + commission)</option>
                                </select>
                                <div class="form-text">Salaried = no commission. Hybrid = salary + commission.</div>
                                @error('compensation_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6" id="salary-field">
                                <label for="base_salary" class="form-label">Base Salary (monthly)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                                    <input type="number" step="0.01" min="0" name="base_salary" id="base_salary" value="{{ old('base_salary') }}" class="form-control @error('base_salary') is-invalid @enderror" placeholder="0.00">
                                    <span class="input-group-text">/mo</span>
                                </div>
                                <div class="form-text">Paid as separate expense, not deducted from invoices.</div>
                                @error('base_salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Commission Percentages (visible when commission or hybrid) --}}
                        <div id="commission-fields" class="mt-3">
                            <label class="form-label fw-semibold"><i class="bi bi-percent me-1"></i>Commission Rates (% of profit)</label>
                            <div class="form-text mb-2">Set the commission percentage for each department. Only applicable departments need a value &mdash; leave others at 0.</div>
                            <div class="row g-3">
                                <div class="col-md-3 col-6">
                                    <label for="commission_consultation" class="form-label">Consultation</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0" max="100" name="commission_consultation" id="commission_consultation" value="{{ old('commission_consultation', 0) }}" class="form-control @error('commission_consultation') is-invalid @enderror" placeholder="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    @error('commission_consultation')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3 col-6">
                                    <label for="commission_pharmacy" class="form-label">Pharmacy</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0" max="100" name="commission_pharmacy" id="commission_pharmacy" value="{{ old('commission_pharmacy', 0) }}" class="form-control @error('commission_pharmacy') is-invalid @enderror" placeholder="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    @error('commission_pharmacy')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3 col-6">
                                    <label for="commission_lab" class="form-label">Laboratory</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0" max="100" name="commission_lab" id="commission_lab" value="{{ old('commission_lab', 0) }}" class="form-control @error('commission_lab') is-invalid @enderror" placeholder="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    @error('commission_lab')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3 col-6">
                                    <label for="commission_radiology" class="form-label">Radiology</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0" max="100" name="commission_radiology" id="commission_radiology" value="{{ old('commission_radiology', 0) }}" class="form-control @error('commission_radiology') is-invalid @enderror" placeholder="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    @error('commission_radiology')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 pt-3" style="border-top:1px solid rgba(255,255,255,0.06);">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Create User
                        </button>
                        <a href="{{ route('owner.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const compType = document.getElementById('compensation_type');
    const salaryField = document.getElementById('salary-field');
    const commissionFields = document.getElementById('commission-fields');
    const roleSelect = document.getElementById('role_id');
    const independentDoctorField = document.getElementById('independent-doctor-field');

    function toggleCompensationFields() {
        const val = compType.value;
        salaryField.style.display = ['salaried','hybrid'].includes(val) ? 'block' : 'none';
        commissionFields.style.display = ['commission','hybrid'].includes(val) ? 'block' : 'none';
    }
    function toggleIndependentField() {
        const selectedText = roleSelect.options[roleSelect.selectedIndex]?.text || '';
        independentDoctorField.style.display = selectedText === 'Doctor' ? 'block' : 'none';
        if (selectedText !== 'Doctor') {
            document.getElementById('is_independent').checked = false;
        }
    }
    compType.addEventListener('change', toggleCompensationFields);
    roleSelect.addEventListener('change', toggleIndependentField);
    toggleCompensationFields();
    toggleIndependentField();
});
</script>
@endsection
