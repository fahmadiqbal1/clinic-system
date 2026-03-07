@extends('layouts.app')
@section('title', $patient->full_name . ' — Referral Patient')

@section('content')
<div class="container mt-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1">
                <i class="bi bi-person me-2" style="color:var(--accent-primary);"></i>
                {{ $patient->full_name }}
            </h1>
            <p class="page-subtitle mb-0">
                Referral Patient
                <span class="badge ms-1" style="background:rgba(255,193,7,0.15); color:#ffc107; font-size:0.7rem;">
                    <i class="bi bi-link-45deg me-1"></i>Referral
                </span>
            </p>
        </div>
        <a href="{{ route('independent-doctor.patients.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Patients
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">

        {{-- Patient Info Card --}}
        <div class="col-lg-4">
            <div class="glass-card p-4 fade-in delay-1">
                <h5 class="mb-3"><i class="bi bi-person-vcard me-2" style="color:var(--accent-info);"></i>Patient Info</h5>
                <dl class="row mb-0" style="font-size:0.9rem;">
                    <dt class="col-5" style="color:var(--text-muted);">Full Name</dt>
                    <dd class="col-7 fw-semibold">{{ $patient->full_name }}</dd>

                    <dt class="col-5" style="color:var(--text-muted);">Gender</dt>
                    <dd class="col-7">{{ $patient->gender }}</dd>

                    @if($patient->date_of_birth)
                    <dt class="col-5" style="color:var(--text-muted);">Date of Birth</dt>
                    <dd class="col-7">{{ $patient->date_of_birth->format('d M Y') }}</dd>
                    @endif

                    <dt class="col-5" style="color:var(--text-muted);">Phone</dt>
                    <dd class="col-7">{{ $patient->phone ?? '—' }}</dd>

                    <dt class="col-5" style="color:var(--text-muted);">Registered</dt>
                    <dd class="col-7">{{ $patient->created_at->format('d M Y, H:i') }}</dd>
                </dl>
            </div>

            {{-- Add More Services --}}
            <div class="glass-card p-4 mt-4 fade-in delay-2">
                <h5 class="mb-3"><i class="bi bi-plus-circle me-2" style="color:var(--accent-success);"></i>Add More Services</h5>
                <form action="{{ route('independent-doctor.patients.add-invoice', $patient) }}" method="POST" id="addServicesForm">
                    @csrf

                    {{-- Lab Services --}}
                    @if($labServices->isNotEmpty())
                    <div class="mb-3">
                        <div class="fw-semibold small mb-2" style="color:var(--accent-info);">
                            <i class="bi bi-eyedropper me-1"></i>Laboratory
                        </div>
                        @foreach($labServices as $service)
                        <div class="form-check mb-1">
                            <input type="checkbox" class="form-check-input add-service-cb"
                                   name="services[]" value="{{ $service->id }}"
                                   id="add_lab_{{ $service->id }}"
                                   data-price="{{ $service->price }}">
                            <label class="form-check-label small" for="add_lab_{{ $service->id }}">
                                {{ $service->name }}
                                <span style="color:var(--accent-success);">— {{ currency($service->price) }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Radiology Services --}}
                    @if($radiologyServices->isNotEmpty())
                    <div class="mb-3">
                        <div class="fw-semibold small mb-2" style="color:var(--accent-warning);">
                            <i class="bi bi-radioactive me-1"></i>Radiology
                        </div>
                        @foreach($radiologyServices as $service)
                        <div class="form-check mb-1">
                            <input type="checkbox" class="form-check-input add-service-cb"
                                   name="services[]" value="{{ $service->id }}"
                                   id="add_rad_{{ $service->id }}"
                                   data-price="{{ $service->price }}">
                            <label class="form-check-label small" for="add_rad_{{ $service->id }}">
                                {{ $service->name }}
                                <span style="color:var(--accent-success);">— {{ currency($service->price) }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Pharmacy Services --}}
                    @if($pharmacyServices->isNotEmpty())
                    <div class="mb-3">
                        <div class="fw-semibold small mb-2" style="color:var(--accent-success);">
                            <i class="bi bi-capsule me-1"></i>Pharmacy
                        </div>
                        @foreach($pharmacyServices as $service)
                        <div class="form-check mb-1">
                            <input type="checkbox" class="form-check-input add-service-cb"
                                   name="services[]" value="{{ $service->id }}"
                                   id="add_pha_{{ $service->id }}"
                                   data-price="{{ $service->price }}">
                            <label class="form-check-label small" for="add_pha_{{ $service->id }}">
                                {{ $service->name }}
                                <span style="color:var(--accent-success);">— {{ currency($service->price) }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    @if($labServices->isEmpty() && $radiologyServices->isEmpty() && $pharmacyServices->isEmpty())
                        <p class="text-muted small mb-3">No active services found.</p>
                    @endif

                    {{-- Payment Method --}}
                    <div class="mb-3">
                        <label class="form-label small">Payment Method</label>
                        <select name="payment_method" class="form-select form-select-sm">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-plus-circle me-1"></i>Add Selected Services
                    </button>
                </form>
            </div>
        </div>

        {{-- Invoices / Orders --}}
        <div class="col-lg-8">
            <div class="glass-card p-4 fade-in delay-2">
                <h5 class="mb-3"><i class="bi bi-receipt me-2" style="color:var(--accent-primary);"></i>Service Orders</h5>

                @if($invoices->isEmpty())
                    <div class="text-center py-4" style="color:var(--text-muted);">
                        <i class="bi bi-clipboard-x" style="font-size:2rem; opacity:0.3;"></i>
                        <p class="mt-2 mb-0">No service orders yet for this patient.</p>
                    </div>
                @else
                    @foreach($invoices as $invoice)
                    <div class="mb-3 p-3 rounded" style="background:rgba(255,255,255,0.04); border:1px solid var(--glass-border);">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge me-2
                                    @if($invoice->department === 'lab') bg-info
                                    @elseif($invoice->department === 'radiology') bg-warning text-dark
                                    @else bg-success
                                    @endif">
                                    <i class="bi bi-@if($invoice->department === 'lab')eyedropper @elseif($invoice->department === 'radiology')radioactive @else capsule @endif me-1"></i>
                                    {{ ucfirst($invoice->department) }}
                                </span>
                                <strong>{{ $invoice->service_name }}</strong>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold" style="color:var(--accent-success);">{{ currency($invoice->net_amount) }}</div>
                                <span class="badge
                                    @if($invoice->status === 'paid') bg-success
                                    @elseif($invoice->status === 'completed') bg-info
                                    @elseif($invoice->status === 'in_progress') bg-warning text-dark
                                    @elseif($invoice->status === 'cancelled') bg-danger
                                    @else bg-secondary
                                    @endif"
                                    style="font-size:0.7rem;">
                                    {{ str_replace('_', ' ', ucfirst($invoice->status)) }}
                                </span>
                            </div>
                        </div>

                        {{-- Line Items --}}
                        @if($invoice->items->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:0.82rem;">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th class="text-end">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoice->items as $item)
                                    <tr>
                                        <td>{{ $item->serviceCatalog?->name ?? $item->description }}</td>
                                        <td class="text-end">{{ currency($item->line_total) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif

                        {{-- Report / Results --}}
                        @if($invoice->report_text)
                        <div class="mt-2 p-2 rounded" style="background:rgba(var(--accent-info-rgb),0.08); font-size:0.82rem;">
                            <i class="bi bi-file-earmark-text me-1"></i><strong>Report:</strong>
                            {{ Str::limit($invoice->report_text, 200) }}
                        </div>
                        @endif

                        <div class="text-end mt-1">
                            <small style="color:var(--text-muted);">Ordered {{ $invoice->created_at->format('d M Y, H:i') }}</small>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('addServicesForm').addEventListener('submit', function (e) {
        const anyChecked = Array.from(document.querySelectorAll('.add-service-cb')).some(cb => cb.checked);
        if (!anyChecked) {
            e.preventDefault();
            alert('Please select at least one service to add.');
        }
    });
});
</script>
@endpush
@endsection
