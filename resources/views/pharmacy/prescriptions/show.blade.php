@extends('layouts.app')
@section('title', 'Prescription Detail')
@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-prescription2 me-2"></i>Prescription Detail</h1>
            <p class="page-subtitle">View prescription and dispense medications</p>
        </div>
        <a href="{{ route('pharmacy.prescriptions.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Queue</a>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            {{-- Patient & Doctor Info --}}
            <div class="glass-card fade-in delay-1 mb-4">
                <h6 class="form-section-title"><i class="bi bi-file-medical me-2"></i>Prescription Info</h6>
                <div class="info-grid">
                    <div class="info-grid-item">
                        <span class="info-label">Patient</span>
                        <span class="info-value fw-medium">{{ $prescription->patient?->first_name ?? '' }} {{ $prescription->patient?->last_name ?? '' }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Doctor</span>
                        <span class="info-value">{{ $prescription->doctor?->name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Diagnosis</span>
                        <span class="info-value">{{ $prescription->diagnosis }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Date</span>
                        <span class="info-value">{{ $prescription->created_at->format('M d, Y H:i') }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            @if($prescription->status === 'dispensed')
                                <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.18);color:var(--accent-success);">Dispensed</span>
                            @elseif($prescription->status === 'cancelled')
                                <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.18);color:var(--accent-danger);">Cancelled</span>
                            @else
                                <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.18);color:var(--accent-warning);">{{ ucfirst($prescription->status) }}</span>
                            @endif
                        </span>
                    </div>
                </div>
                @if($prescription->notes)
                    <div class="mt-3 pt-3" style="border-top:1px solid var(--glass-border);">
                        <small style="color:var(--text-muted);">Doctor Notes:</small>
                        <p class="mb-0" style="color:var(--text-secondary);">{{ $prescription->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Associated Invoices --}}
            @if($prescription->invoices->count() > 0)
                <div class="glass-card fade-in delay-1">
                    <h6 class="form-section-title"><i class="bi bi-receipt me-2"></i>Associated Invoices</h6>
                    @foreach($prescription->invoices as $inv)
                        <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--glass-border);">
                            <span>Invoice #{{ $inv->id }} &middot; {{ currency($inv->total_amount) }}</span>
                            @if($inv->status === 'paid')
                                <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.18);color:var(--accent-success);">Paid</span>
                            @else
                                <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.18);color:var(--accent-warning);">{{ ucfirst($inv->status) }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="col-md-7">
            {{-- Medication Items --}}
            <div class="glass-card fade-in delay-2 mb-4">
                <h6 class="form-section-title"><i class="bi bi-capsule me-2"></i>Prescribed Medications</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Duration</th>
                                <th>Qty</th>
                                <th>Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($prescription->items as $item)
                                <tr>
                                    <td class="fw-medium">{{ $item->medication_name }}</td>
                                    <td>{{ $item->dosage ?? '-' }}</td>
                                    <td>{{ $item->frequency ?? '-' }}</td>
                                    <td>{{ $item->duration ?? '-' }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td class="small" style="color:var(--text-secondary);">{{ $item->instructions ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Dispense Action --}}
            @if($prescription->status === 'active')
                <div class="glass-card fade-in delay-2" style="border:1px solid rgba(var(--accent-success-rgb),0.3);">
                    <h6 class="form-section-title"><i class="bi bi-capsule me-2" style="color:var(--accent-success);"></i>Dispense Medications</h6>
                    <p class="mb-3" style="color:var(--text-secondary);">Complete the checklist before marking as dispensed.</p>

                    {{-- Inline checklist --}}
                    <div class="mb-3" id="dispenseChecklist">
                        @foreach([
                            'chk1' => 'Patient identity verified (name + ID)',
                            'chk2' => 'All medications available in stock',
                            'chk3' => 'Drug interactions reviewed',
                            'chk4' => 'Correct dosage and quantity confirmed',
                            'chk5' => 'Patient counselled on administration',
                        ] as $id => $label)
                        <div class="form-check mb-2">
                            <input class="form-check-input dispense-check" type="checkbox" id="{{ $id }}" value="1">
                            <label class="form-check-label" for="{{ $id }}" style="color:var(--text-secondary);">{{ $label }}</label>
                        </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('pharmacy.prescriptions.dispense', $prescription) }}" id="dispenseForm">
                        @csrf
                        <div class="mb-3">
                            <label for="dispenseNotes" class="form-label" style="font-size:0.85rem; color:var(--text-muted);">Dispensing Notes (optional)</label>
                            <textarea id="dispenseNotes" name="dispense_notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Take with food, avoid alcohol…"></textarea>
                        </div>
                        <button type="button" id="dispenseBtn" class="btn btn-success btn-lg w-100" disabled
                                data-bs-toggle="modal" data-bs-target="#dispenseConfirmModal">
                            <i class="bi bi-check-circle me-1"></i>Mark as Dispensed
                        </button>
                    </form>
                </div>

                {{-- Dispense confirm modal --}}
                <div class="modal fade" id="dispenseConfirmModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content" style="border-color:rgba(var(--accent-success-rgb),0.5);">
                            <div class="modal-header" style="background:rgba(var(--accent-success-rgb),0.1);">
                                <h5 class="modal-title"><i class="bi bi-check-circle me-2" style="color:var(--accent-success);"></i>Confirm Dispensing</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2 fw-semibold">
                                    {{ $prescription->patient?->first_name }} {{ $prescription->patient?->last_name }} —
                                    {{ $prescription->items->count() }} medication(s)
                                </p>
                                <ul class="mb-0 small" style="color:var(--text-secondary);">
                                    @foreach($prescription->items as $item)
                                    <li>{{ $item->medication_name }} {{ $item->dosage ?? '' }} × {{ $item->quantity }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Go Back</button>
                                <button type="button" class="btn btn-success" onclick="document.getElementById('dispenseForm').submit()">
                                    <i class="bi bi-check-circle me-1"></i>Confirm Dispensed
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            {{-- Supplement Upsell --}}
            @if($supplements->count() > 0)
            <div class="glass-card fade-in delay-3 mt-4" style="border:1px solid rgba(var(--accent-info-rgb),0.3);">
                <h6 class="form-section-title"><i class="bi bi-bag-plus me-2" style="color:var(--accent-info);"></i>Suggest OTC Supplements</h6>
                <p class="small mb-3" style="color:var(--text-muted);">Complementary over-the-counter products available in stock. Offer these to the patient as relevant add-ons.</p>
                <div class="row g-2">
                    @foreach($supplements as $supp)
                    <div class="col-6">
                        <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background:rgba(var(--accent-info-rgb),0.07); border:1px solid rgba(var(--accent-info-rgb),0.15);">
                            <div class="overflow-hidden me-2">
                                <div class="fw-medium small text-truncate">{{ $supp->name }}</div>
                                @if($supp->unit)
                                <div class="small" style="color:var(--text-muted); font-size:0.75rem;">{{ $supp->unit }}</div>
                                @endif
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="fw-semibold small">{{ currency($supp->selling_price) }}</div>
                                <div class="small" style="color:var(--text-muted); font-size:0.7rem;">{{ (int) $supp->current_stock }} in stock</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var checks = document.querySelectorAll('.dispense-check');
    var btn    = document.getElementById('dispenseBtn');
    if (!checks.length || !btn) return;

    function refreshBtn() {
        var allChecked = Array.from(checks).every(function (c) { return c.checked; });
        btn.disabled = !allChecked;
        btn.classList.toggle('opacity-50', !allChecked);
    }

    checks.forEach(function (c) { c.addEventListener('change', refreshBtn); });
    refreshBtn();
});
</script>
@endpush

@endsection
