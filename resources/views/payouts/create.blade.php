@extends('layouts.app')
@section('title', 'Generate Payout — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header fade-in">
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-wallet2 me-2" style="color:var(--accent-warning);"></i>Generate Staff Payout</h1>
        <p class="page-subtitle">Pay out all unpaid commissions for a staff member</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger fade-in">
            <ul class="list-unstyled mb-0">
                @foreach ($errors->all() as $error)
                    <li><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        {{-- Left: Form --}}
        <div class="col-lg-7">
            <div class="glass-card p-4 fade-in delay-1">
                <form action="{{ route('reception.payouts.store') }}" method="POST">
                    @csrf

                    {{-- Staff Member Selection --}}
                    <div class="mb-4">
                        <label for="staff_id" class="form-label fw-semibold text-white">
                            <i class="bi bi-person-badge me-1" style="color:var(--accent-primary);"></i>Select Staff Member
                        </label>
                        <select name="staff_id" id="staff_id" class="form-select @error('staff_id') is-invalid @enderror" onchange="updateUnpaidInfo()">
                            <option value="">— Choose a staff member —</option>
                            @foreach ($staffMembers as $member)
                                <option value="{{ $member->id }}"
                                    data-unpaid="{{ $member->unpaid_total }}"
                                    data-is-doctor="{{ $member->hasRole('Doctor') ? '1' : '0' }}"
                                    data-salary="{{ $member->base_salary ?? 0 }}"
                                    @selected(old('staff_id', $preselectedStaffId ?? '') == $member->id)>
                                    {{ $member->name }} — {{ currency($member->unpaid_total) }} unpaid
                                </option>
                            @endforeach
                        </select>
                        @error('staff_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Unpaid Summary (dynamic) --}}
                    <div id="unpaid-summary" class="glass-card accent-left-info mb-4 d-none">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-info"></i>
                            <span class="text-white-50">Total unpaid commissions: <strong id="unpaid-amount" class="text-white">{{ currency_symbol() }}0.00</strong></span>
                        </div>
                    </div>

                    {{-- Paid Amount --}}
                    <div class="mb-4">
                        <label for="paid_amount" class="form-label fw-semibold text-white">
                            <i class="bi bi-cash-coin me-1" style="color:var(--accent-success);"></i>Amount to Pay
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            <input type="number" step="0.01" min="0.01" name="paid_amount" id="paid_amount"
                                value="{{ old('paid_amount') }}"
                                placeholder="0.00"
                                class="form-control @error('paid_amount') is-invalid @enderror">
                            @error('paid_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text text-white-50">
                            <i class="bi bi-info-circle me-1"></i>
                            Enter the amount to pay now. Can be partial — the remainder stays as unpaid balance.
                        </div>
                    </div>

                    {{-- Salary Amount (shown only for non-doctor staff) --}}
                    <div class="mb-4 d-none" id="salary-field">
                        <label for="salary_amount" class="form-label fw-semibold text-white">
                            <i class="bi bi-briefcase me-1" style="color:var(--accent-primary);"></i>Salary Amount
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            <input type="number" step="0.01" min="0" name="salary_amount" id="salary_amount"
                                value="{{ old('salary_amount') }}"
                                placeholder="0.00"
                                class="form-control @error('salary_amount') is-invalid @enderror">
                            @error('salary_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text text-white-50">
                            <i class="bi bi-info-circle me-1"></i>
                            Base salary included with this monthly payout. Pre-filled from staff profile.
                        </div>
                    </div>

                    {{-- Monthly payout notice --}}
                    <div class="alert d-none" id="monthly-notice" style="background:rgba(var(--accent-info-rgb),0.15); border:1px solid rgba(var(--accent-info-rgb),0.3); color:var(--text-primary);">
                        <i class="bi bi-info-circle me-1"></i> This is a <strong>monthly payout</strong> (salary + commission). It requires <strong>owner approval</strong> before the staff member can confirm receipt.
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3" style="border-top:1px solid var(--glass-border);">
                        <a href="{{ route('reception.payouts.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-check-circle me-1"></i>Generate Payout
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right: Staff Unpaid Summary --}}
        <div class="col-lg-5">
            <div class="glass-card fade-in delay-2">
                <h3 class="h5 fw-bold text-white mb-3">
                    <i class="bi bi-people me-2" style="color:var(--accent-warning);"></i>Unpaid Balances
                </h3>

                @php $hasUnpaid = $staffMembers->where('unpaid_total', '>', 0); @endphp

                @if ($hasUnpaid->count() > 0)
                    <div class="list-group list-group-flush">
                        @foreach ($hasUnpaid as $member)
                            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="stat-icon stat-icon-primary" style="width:2rem;height:2rem;font-size:0.75rem;">
                                        <i class="bi bi-person"></i>
                                    </div>
                                    <span class="text-white small fw-semibold">{{ $member->name }}</span>
                                </div>
                                <span class="badge-glass badge-glass-warning">{{ currency($member->unpaid_total) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle text-success" style="font-size:1.5rem;"></i>
                        <p class="small text-white-50 mt-2 mb-0">All commissions have been paid out.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function updateUnpaidInfo() {
    const select = document.getElementById('staff_id');
    const summary = document.getElementById('unpaid-summary');
    const amountEl = document.getElementById('unpaid-amount');
    const paidInput = document.getElementById('paid_amount');
    const salaryField = document.getElementById('salary-field');
    const salaryInput = document.getElementById('salary_amount');
    const monthlyNotice = document.getElementById('monthly-notice');
    const opt = select.options[select.selectedIndex];

    if (opt && opt.value) {
        const unpaid = parseFloat(opt.dataset.unpaid || 0);
        const isDoctor = opt.dataset.isDoctor === '1';
        const salary = parseFloat(opt.dataset.salary || 0);

        amountEl.textContent = '{{ currency_symbol() }}' + unpaid.toFixed(2);
        summary.classList.remove('d-none');

        if (isDoctor) {
            // Doctor: daily commission payout
            salaryField.classList.add('d-none');
            monthlyNotice.classList.add('d-none');
            if (!paidInput.value) {
                paidInput.value = unpaid.toFixed(2);
            }
        } else {
            // Non-doctor: monthly payout with salary
            salaryField.classList.remove('d-none');
            monthlyNotice.classList.remove('d-none');
            salaryInput.value = salary.toFixed(2);
            if (!paidInput.value) {
                paidInput.value = (unpaid + salary).toFixed(2);
            }
        }
    } else {
        summary.classList.add('d-none');
        salaryField.classList.add('d-none');
        monthlyNotice.classList.add('d-none');
    }
}
document.addEventListener('DOMContentLoaded', updateUnpaidInfo);
</script>
@endpush
@endsection
