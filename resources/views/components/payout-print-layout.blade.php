{{--
    Print-only payout layout matching the PDF template.
    Usage: @include('components.payout-print-layout', ['payout' => $payout])
--}}
@php
    $clinicName = config('app.name');
    $salaryComponent = $payout->payout_type === 'monthly' ? ($payout->salary_amount ?? 0) : 0;
    $commissionComponent = $payout->total_amount - $salaryComponent;
@endphp

<div class="print-payout-layout">
    {{-- HEADER --}}
    <div class="pp-header">
        <div class="pp-header-left">
            <img src="{{ asset('images/logo JPEG.jpeg') }}" alt="Logo" class="pp-logo">
        </div>
        <div class="pp-header-center">
            <div class="pp-clinic-name">{{ $clinicName }}</div>
            <div class="pp-clinic-sub">Staff Commission & Salary Payout Statement</div>
        </div>
        <div class="pp-header-right">
            <div class="pp-tag">PAYOUT SLIP</div>
        </div>
    </div>
    <hr class="pp-divider">

    {{-- PAYEE / META --}}
    <div class="pp-meta-row">
        <div class="pp-meta-box">
            <div class="pp-meta-lbl">Payee</div>
            <div class="pp-meta-val">
                <strong>{{ $payout->doctor?->name ?? 'Unknown' }}</strong><br>
                @if($payout->doctor?->email) Email: {{ $payout->doctor->email }}<br> @endif
                @foreach($payout->doctor?->roles ?? [] as $role)
                    Role: {{ $role->name }}<br>
                @endforeach
            </div>
        </div>
        <div class="pp-meta-box pp-meta-box-right">
            <div class="pp-meta-lbl">Payout Details</div>
            <div class="pp-meta-val">
                <strong>Payout #:</strong> {{ $payout->id }}<br>
                <strong>Type:</strong> {{ $payout->payout_type === 'monthly' ? 'Monthly (Salary + Commission)' : 'Daily Commission' }}<br>
                <strong>Period:</strong> {{ $payout->period_start?->format('d M Y') }} &mdash; {{ $payout->period_end?->format('d M Y') }}<br>
                <strong>Created:</strong> {{ $payout->created_at?->format('d M Y, H:i') }}<br>
                <strong>Status:</strong>
                <span class="pp-badge-{{ $payout->status === 'confirmed' ? 'green' : 'amber' }}">{{ strtoupper($payout->status) }}</span>
                @if($payout->approval_status)
                    <span class="pp-badge-{{ $payout->approval_status === 'approved' ? 'blue' : ($payout->approval_status === 'rejected' ? 'red' : 'amber') }}">{{ strtoupper($payout->approval_status) }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- SUMMARY --}}
    <div class="pp-summary">
        <div class="pp-summary-item pp-accent-green">
            <div class="pp-summary-num">Rs. {{ number_format($payout->total_amount, 2) }}</div>
            <div class="pp-summary-lbl">Total Amount</div>
        </div>
        <div class="pp-summary-item pp-accent-blue">
            <div class="pp-summary-num">Rs. {{ number_format($payout->paid_amount, 2) }}</div>
            <div class="pp-summary-lbl">Paid Amount</div>
        </div>
        @if($payout->payout_type === 'monthly')
        <div class="pp-summary-item pp-accent-amber">
            <div class="pp-summary-num">Rs. {{ number_format($salaryComponent, 2) }}</div>
            <div class="pp-summary-lbl">Salary</div>
        </div>
        <div class="pp-summary-item pp-accent-green">
            <div class="pp-summary-num">Rs. {{ number_format($commissionComponent, 2) }}</div>
            <div class="pp-summary-lbl">Commission</div>
        </div>
        @else
        <div class="pp-summary-item">
            <div class="pp-summary-num">{{ $payout->revenueLedgers?->count() ?? 0 }}</div>
            <div class="pp-summary-lbl">Revenue Entries</div>
        </div>
        <div class="pp-summary-item {{ $payout->outstanding_balance > 0 ? 'pp-accent-red' : '' }}">
            <div class="pp-summary-num">Rs. {{ number_format($payout->outstanding_balance ?? 0, 2) }}</div>
            <div class="pp-summary-lbl">Outstanding</div>
        </div>
        @endif
    </div>

    {{-- REVENUE ENTRIES --}}
    @if($payout->revenueLedgers && $payout->revenueLedgers->count() > 0)
    <table class="pp-items">
        <thead>
            <tr>
                <th style="width:8%">#</th>
                <th style="width:18%">Invoice</th>
                <th style="width:24%">Role Type</th>
                <th style="width:22%" class="pp-tr">Amount</th>
                <th style="width:28%">Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payout->revenueLedgers as $i => $entry)
            <tr class="{{ $i % 2 === 1 ? 'pp-alt' : '' }}">
                <td>{{ $i + 1 }}</td>
                <td>#{{ $entry->invoice_id }}</td>
                <td>{{ $entry->role_type }}</td>
                <td class="pp-tr" style="font-weight:bold;">Rs. {{ number_format($entry->amount, 2) }}</td>
                <td>{{ $entry->created_at?->format('d M Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- TOTALS --}}
    <div class="pp-totals-row">
        <div class="pp-totals-spacer"></div>
        <div class="pp-totals-box">
            @if($payout->payout_type === 'monthly')
            <div class="pp-total-line"><span class="pp-total-lbl">Salary</span><span class="pp-total-val">Rs. {{ number_format($salaryComponent, 2) }}</span></div>
            <div class="pp-total-line"><span class="pp-total-lbl">Commission</span><span class="pp-total-val">Rs. {{ number_format($commissionComponent, 2) }}</span></div>
            @endif
            <div class="pp-total-line"><span class="pp-total-lbl">Total Amount</span><span class="pp-total-val">Rs. {{ number_format($payout->total_amount, 2) }}</span></div>
            @if($payout->outstanding_balance > 0)
            <div class="pp-total-line"><span class="pp-total-lbl">Outstanding</span><span class="pp-total-val" style="color:#dc2626;">Rs. {{ number_format($payout->outstanding_balance, 2) }}</span></div>
            @endif
            <div class="pp-total-line pp-grand"><span class="pp-total-lbl">Net Paid</span><span class="pp-total-val">Rs. {{ number_format($payout->paid_amount, 2) }}</span></div>
        </div>
    </div>

    {{-- AUDIT TRAIL --}}
    <div class="pp-audit">
        <div class="pp-meta-lbl">Audit Trail</div>
        <div class="pp-meta-val">
            <strong>Created by:</strong> {{ $payout->creator?->name ?? 'System' }} | {{ $payout->created_at?->format('d M Y, H:i') }}
            @if($payout->approver)<br><strong>{{ $payout->approval_status === 'approved' ? 'Approved' : 'Reviewed' }} by:</strong> {{ $payout->approver->name }}@if($payout->approved_at) | {{ $payout->approved_at->format('d M Y, H:i') }}@endif @endif
            @if($payout->confirmer)<br><strong>Confirmed by:</strong> {{ $payout->confirmer->name }}@if($payout->confirmed_at) | {{ $payout->confirmed_at->format('d M Y, H:i') }}@endif @endif
            @if($payout->correction_of_id)<br><strong>Correction of:</strong> Payout #{{ $payout->correction_of_id }} @endif
        </div>
    </div>

    {{-- SIGNATURE --}}
    <div class="pp-sig-row">
        <div class="pp-sig"><div class="pp-sig-line">Authorized Signature (Clinic)</div></div>
        <div class="pp-sig"><div class="pp-sig-line">Received by ({{ $payout->doctor?->name ?? 'Payee' }})</div></div>
    </div>

    {{-- FOOTER --}}
    <div class="pp-footer">
        This is a computer-generated payout statement. | {{ $clinicName }} | Generated: {{ now()->format('d M Y, H:i') }}
    </div>
</div>
