@extends('layouts.app')
@section('title', 'Quick Referral Registration — ' . config('app.name'))

@section('content')
<div class="container mt-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1">
                <i class="bi bi-person-plus me-2" style="color:var(--accent-success);"></i>Quick Referral Registration
            </h1>
            <p class="page-subtitle">Register a patient referred from your clinic and order the required services</p>
        </div>
        <a href="{{ route('independent-doctor.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <div class="glass-card p-4 fade-in delay-1">
        <form action="{{ route('independent-doctor.patients.store') }}" method="POST" id="referralForm">
            @csrf

            {{-- ── Patient Details ── --}}
            <h5 class="mb-3"><i class="bi bi-person me-1" style="color:var(--accent-primary);"></i>Patient Details</h5>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">
                        <i class="bi bi-person me-1" style="color:var(--accent-primary);"></i>First Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                           id="first_name" name="first_name" value="{{ old('first_name') }}" required autofocus>
                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">
                        <i class="bi bi-person me-1" style="color:var(--accent-primary);"></i>Last Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                           id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="phone" class="form-label">
                        <i class="bi bi-telephone me-1" style="color:var(--accent-info);"></i>Phone
                    </label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror"
                           id="phone" name="phone" value="{{ old('phone') }}">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="gender" class="form-label">
                        <i class="bi bi-gender-ambiguous me-1" style="color:var(--accent-warning);"></i>Gender <span class="text-danger">*</span>
                    </label>
                    <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male"   {{ old('gender') === 'Male'   ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other"  {{ old('gender') === 'Other'  ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="date_of_birth" class="form-label">
                        <i class="bi bi-calendar-event me-1" style="color:var(--accent-info);"></i>Date of Birth
                    </label>
                    <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror"
                           id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}">
                    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <hr class="my-4" style="border-color:var(--glass-border);">

            {{-- ── Services Selection ── --}}
            <h5 class="mb-1"><i class="bi bi-clipboard2-pulse me-1" style="color:var(--accent-info);"></i>Order Services</h5>
            <p class="text-muted small mb-3">Select one or more services across lab, radiology, or pharmacy. One invoice will be created per department.</p>

            @error('services')
                <div class="alert alert-danger py-2">{{ $message }}</div>
            @enderror

            {{-- Tabs for department selection --}}
            <ul class="nav nav-tabs mb-3" id="serviceTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="lab-tab" data-bs-toggle="tab" data-bs-target="#lab-pane" type="button">
                        <i class="bi bi-eyedropper me-1"></i>Laboratory
                        <span class="badge ms-1 lab-count" style="background:rgba(var(--accent-info-rgb),0.2); color:var(--accent-info);">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="radiology-tab" data-bs-toggle="tab" data-bs-target="#radiology-pane" type="button">
                        <i class="bi bi-radioactive me-1"></i>Radiology
                        <span class="badge ms-1 radiology-count" style="background:rgba(var(--accent-warning-rgb),0.2); color:var(--accent-warning);">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pharmacy-tab" data-bs-toggle="tab" data-bs-target="#pharmacy-pane" type="button">
                        <i class="bi bi-capsule me-1"></i>Pharmacy
                        <span class="badge ms-1 pharmacy-count" style="background:rgba(var(--accent-success-rgb),0.2); color:var(--accent-success);">0</span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="serviceTabsContent">

                {{-- Laboratory --}}
                <div class="tab-pane fade show active" id="lab-pane" role="tabpanel">
                    @if($labServices->isEmpty())
                        <p class="text-muted text-center py-3">No active lab services found.</p>
                    @else
                        @foreach($labServices->groupBy('category') as $category => $services)
                            <div class="mb-3">
                                <div class="fw-semibold small mb-2" style="color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">
                                    {{ $category ?: 'General' }}
                                </div>
                                <div class="row g-2">
                                    @foreach($services as $service)
                                    <div class="col-md-6 col-lg-4">
                                        <label class="service-card d-flex align-items-start gap-2 p-3 rounded cursor-pointer"
                                               style="border:1px solid var(--glass-border); cursor:pointer; transition:all 0.2s;">
                                            <input type="checkbox"
                                                   name="services[]"
                                                   value="{{ $service->id }}"
                                                   class="form-check-input service-checkbox lab-checkbox mt-0"
                                                   data-dept="lab"
                                                   data-price="{{ $service->price }}"
                                                   {{ is_array(old('services')) && in_array($service->id, old('services')) ? 'checked' : '' }}>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold small">{{ $service->name }}</div>
                                                <div class="small" style="color:var(--accent-success);">{{ currency($service->price) }}</div>
                                            </div>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                {{-- Radiology --}}
                <div class="tab-pane fade" id="radiology-pane" role="tabpanel">
                    @if($radiologyServices->isEmpty())
                        <p class="text-muted text-center py-3">No active radiology services found.</p>
                    @else
                        @foreach($radiologyServices->groupBy('category') as $category => $services)
                            <div class="mb-3">
                                <div class="fw-semibold small mb-2" style="color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">
                                    {{ $category ?: 'General' }}
                                </div>
                                <div class="row g-2">
                                    @foreach($services as $service)
                                    <div class="col-md-6 col-lg-4">
                                        <label class="service-card d-flex align-items-start gap-2 p-3 rounded cursor-pointer"
                                               style="border:1px solid var(--glass-border); cursor:pointer; transition:all 0.2s;">
                                            <input type="checkbox"
                                                   name="services[]"
                                                   value="{{ $service->id }}"
                                                   class="form-check-input service-checkbox radiology-checkbox mt-0"
                                                   data-dept="radiology"
                                                   data-price="{{ $service->price }}"
                                                   {{ is_array(old('services')) && in_array($service->id, old('services')) ? 'checked' : '' }}>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold small">{{ $service->name }}</div>
                                                <div class="small" style="color:var(--accent-success);">{{ currency($service->price) }}</div>
                                            </div>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                {{-- Pharmacy --}}
                <div class="tab-pane fade" id="pharmacy-pane" role="tabpanel">
                    @if($pharmacyServices->isEmpty())
                        <p class="text-muted text-center py-3">No active pharmacy services found.</p>
                    @else
                        @foreach($pharmacyServices->groupBy('category') as $category => $services)
                            <div class="mb-3">
                                <div class="fw-semibold small mb-2" style="color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">
                                    {{ $category ?: 'General' }}
                                </div>
                                <div class="row g-2">
                                    @foreach($services as $service)
                                    <div class="col-md-6 col-lg-4">
                                        <label class="service-card d-flex align-items-start gap-2 p-3 rounded cursor-pointer"
                                               style="border:1px solid var(--glass-border); cursor:pointer; transition:all 0.2s;">
                                            <input type="checkbox"
                                                   name="services[]"
                                                   value="{{ $service->id }}"
                                                   class="form-check-input service-checkbox pharmacy-checkbox mt-0"
                                                   data-dept="pharmacy"
                                                   data-price="{{ $service->price }}"
                                                   {{ is_array(old('services')) && in_array($service->id, old('services')) ? 'checked' : '' }}>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold small">{{ $service->name }}</div>
                                                <div class="small" style="color:var(--accent-success);">{{ currency($service->price) }}</div>
                                            </div>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

            </div>

            {{-- Order Summary --}}
            <div id="orderSummary" class="mt-3 p-3 rounded" style="background:rgba(var(--accent-primary-rgb),0.06); border:1px solid rgba(var(--accent-primary-rgb),0.2); display:none;">
                <h6 class="mb-2"><i class="bi bi-cart-check me-1"></i>Order Summary</h6>
                <div id="summaryItems" class="small"></div>
                <div class="d-flex justify-content-between align-items-center mt-2 pt-2" style="border-top:1px solid rgba(255,255,255,0.1);">
                    <strong>Total Estimate</strong>
                    <strong id="summaryTotal" style="color:var(--accent-success);">{{ currency(0) }}</strong>
                </div>
            </div>

            <hr class="my-4" style="border-color:var(--glass-border);">

            {{-- Payment Method --}}
            <h5 class="mb-3"><i class="bi bi-credit-card me-1" style="color:var(--accent-warning);"></i>Payment Method</h5>
            <p class="text-muted small mb-3">Payment will be collected by the respective department at the time of service.</p>
            <div class="row g-3 mb-0">
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" id="pm_cash" value="cash"
                               {{ old('payment_method', 'cash') === 'cash' ? 'checked' : '' }}>
                        <label class="form-check-label" for="pm_cash"><i class="bi bi-cash me-1"></i>Cash</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" id="pm_card" value="card"
                               {{ old('payment_method') === 'card' ? 'checked' : '' }}>
                        <label class="form-check-label" for="pm_card"><i class="bi bi-credit-card me-1"></i>Card</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" id="pm_transfer" value="transfer"
                               {{ old('payment_method') === 'transfer' ? 'checked' : '' }}>
                        <label class="form-check-label" for="pm_transfer"><i class="bi bi-bank me-1"></i>Bank Transfer</label>
                    </div>
                </div>
            </div>
            @error('payment_method')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <div class="d-flex gap-2 mt-4 pt-3" style="border-top:1px solid var(--glass-border);">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="bi bi-check-circle me-1"></i>Register &amp; Order Services
                </button>
                <a href="{{ route('independent-doctor.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </a>
            </div>

        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.service-checkbox');
    const summaryDiv = document.getElementById('orderSummary');
    const summaryItems = document.getElementById('summaryItems');
    const summaryTotal = document.getElementById('summaryTotal');

    function formatCurrency(amount) {
        return '{{ config('app.currency', '$') }}' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function updateSummary() {
        const selected = Array.from(checkboxes).filter(cb => cb.checked);
        const labCount = document.querySelectorAll('.lab-checkbox:checked').length;
        const radCount = document.querySelectorAll('.radiology-checkbox:checked').length;
        const pharCount = document.querySelectorAll('.pharmacy-checkbox:checked').length;

        document.querySelectorAll('.lab-count').forEach(el => el.textContent = labCount);
        document.querySelectorAll('.radiology-count').forEach(el => el.textContent = radCount);
        document.querySelectorAll('.pharmacy-count').forEach(el => el.textContent = pharCount);

        if (selected.length === 0) {
            summaryDiv.style.display = 'none';
            return;
        }

        summaryDiv.style.display = 'block';
        let total = 0;
        const deptTotals = {};

        selected.forEach(cb => {
            const price = parseFloat(cb.dataset.price) || 0;
            const dept = cb.dataset.dept;
            const label = cb.closest('label');
            const name = label.querySelector('.fw-semibold').textContent.trim();
            total += price;
            if (!deptTotals[dept]) deptTotals[dept] = { items: [], total: 0 };
            deptTotals[dept].items.push(name);
            deptTotals[dept].total += price;
        });

        let html = '';
        for (const [dept, data] of Object.entries(deptTotals)) {
            html += `<div class="mb-1"><strong class="text-capitalize">${dept}:</strong> ${data.items.join(', ')} — ${formatCurrency(data.total)}</div>`;
        }
        summaryItems.innerHTML = html;
        summaryTotal.textContent = formatCurrency(total);
    }

    // Highlight selected service cards
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            const card = this.closest('.service-card');
            if (this.checked) {
                card.style.borderColor = 'var(--accent-primary)';
                card.style.background = 'rgba(var(--accent-primary-rgb),0.08)';
            } else {
                card.style.borderColor = 'var(--glass-border)';
                card.style.background = '';
            }
            updateSummary();
        });

        // Restore highlight on page load (old values)
        if (cb.checked) {
            const card = cb.closest('.service-card');
            card.style.borderColor = 'var(--accent-primary)';
            card.style.background = 'rgba(var(--accent-primary-rgb),0.08)';
        }
    });

    updateSummary();

    // Prevent submit if no services selected
    document.getElementById('referralForm').addEventListener('submit', function (e) {
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        if (!anyChecked) {
            e.preventDefault();
            alert('Please select at least one service to order.');
        }
    });
});
</script>
@endpush
@endsection
