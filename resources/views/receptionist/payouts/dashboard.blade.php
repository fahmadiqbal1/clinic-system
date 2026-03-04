@extends('layouts.app')
@section('title', 'Payout Dashboard — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-cash-coin me-2" style="color:var(--accent-success);"></i>Payout Dashboard</h1>
            <p class="page-subtitle">One-click payouts for commission-earning staff</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('reception.payouts.create') }}" class="btn btn-outline-primary"><i class="bi bi-sliders me-1"></i>Custom Payout</a>
            <a href="{{ route('reception.payouts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-list me-1"></i>All Payouts</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-banner-success fade-in delay-1"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-banner-danger fade-in delay-1"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    @php
        $deptMeta = [
            'consultation' => ['label' => 'Consult', 'icon' => 'bi-heart-pulse', 'color' => 'danger'],
            'lab'          => ['label' => 'Lab',     'icon' => 'bi-droplet',     'color' => 'info'],
            'radiology'    => ['label' => 'Rad',     'icon' => 'bi-radioactive', 'color' => 'warning'],
            'pharmacy'     => ['label' => 'Pharm',   'icon' => 'bi-capsule',     'color' => 'success'],
        ];
    @endphp

    {{-- ═══════════════════════════════════════════════════════════════════
         SECTION 1: DOCTOR PAYOUTS (Daily Commission — No Approval Needed)
    ═══════════════════════════════════════════════════════════════════ --}}
    <h4 class="fw-bold mb-3 fade-in delay-1"><i class="bi bi-heart-pulse me-2" style="color:var(--accent-danger);"></i>Doctor Payouts <small class="text-muted fw-normal">(Daily — No Approval Needed)</small></h4>
    @if(empty($doctorCards))
        <div class="glass-card p-4 text-center mb-4 fade-in delay-1">
            <i class="bi bi-check-circle" style="font-size:2rem; color:var(--accent-success);"></i>
            <p class="mt-2 mb-0" style="color:var(--text-muted);">All doctors' commissions are paid up.</p>
        </div>
    @else
        <div class="row g-3 mb-4">
            @foreach($doctorCards as $i => $card)
            <div class="col-md-6 col-lg-4 fade-in delay-{{ ($i % 6) + 1 }}">
                <div class="glass-card p-3 h-100 hover-lift">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon stat-icon-primary"><i class="bi bi-person-badge"></i></div>
                        <div>
                            <h6 class="fw-semibold mb-0" style="color:var(--text-primary);">{{ $card['name'] }}</h6>
                            <small style="color:var(--text-muted);">{{ $card['roles'] }}</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        @foreach($deptMeta as $dept => $meta)
                            @php $amt = $card['deptBreakdown'][$dept]['total'] ?? 0; @endphp
                            @if($amt > 0)
                            <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid var(--glass-border);">
                                <span><i class="bi {{ $meta['icon'] }} me-1" style="color:var(--accent-{{ $meta['color'] }});"></i><small style="color:var(--text-primary);">{{ $meta['label'] }}</small></span>
                                <span class="fw-semibold small" style="color:var(--accent-{{ $meta['color'] }});">{{ currency($amt) }}</span>
                            </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small style="color:var(--text-muted);">Total Unpaid</small>
                            <div class="stat-value glow-success" style="font-size:1.3rem;">{{ currency($card['totalUnpaid']) }}</div>
                        </div>
                        <form method="POST" action="{{ route('receptionist.payouts.quick-pay', $card['id']) }}"
                              onsubmit="return confirm('Generate full payout of {{ currency($card['totalUnpaid']) }} for {{ $card['name'] }}?');">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm fw-semibold"><i class="bi bi-cash me-1"></i>Pay Now</button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════
         SECTION 2: OTHER STAFF PAYOUTS (Monthly — Requires Owner Approval)
    ═══════════════════════════════════════════════════════════════════ --}}
    <h4 class="fw-bold mb-3 fade-in delay-2"><i class="bi bi-people me-2" style="color:var(--accent-info);"></i>Staff Monthly Payouts <small class="text-muted fw-normal">(Salary + Commission — Requires Owner Approval)</small></h4>
    @if(empty($staffCards))
        <div class="glass-card p-4 text-center fade-in delay-2">
            <i class="bi bi-check-circle" style="font-size:2rem; color:var(--accent-success);"></i>
            <p class="mt-2 mb-0" style="color:var(--text-muted);">No staff with pending monthly payouts.</p>
        </div>
    @else
        <div class="row g-3">
            @foreach($staffCards as $i => $card)
            <div class="col-md-6 col-lg-4 fade-in delay-{{ ($i % 6) + 1 }}">
                <div class="glass-card p-3 h-100 hover-lift" style="border-left: 3px solid var(--accent-info);">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon stat-icon-info"><i class="bi bi-person-badge"></i></div>
                        <div>
                            <h6 class="fw-semibold mb-0" style="color:var(--text-primary);">{{ $card['name'] }}</h6>
                            <small style="color:var(--text-muted);">{{ $card['roles'] }}</small>
                        </div>
                    </div>
                    {{-- Salary info --}}
                    @if($card['baseSalary'] > 0)
                    <div class="d-flex justify-content-between align-items-center py-1 mb-2" style="border-bottom:1px solid var(--glass-border);">
                        <span><i class="bi bi-briefcase me-1" style="color:var(--accent-primary);"></i><small style="color:var(--text-primary);">Base Salary</small></span>
                        <span class="fw-semibold small" style="color:var(--accent-primary);">{{ currency($card['baseSalary']) }}</span>
                    </div>
                    @endif
                    {{-- Department breakdown --}}
                    <div class="mb-3">
                        @foreach($deptMeta as $dept => $meta)
                            @php $amt = $card['deptBreakdown'][$dept]['total'] ?? 0; @endphp
                            @if($amt > 0)
                            <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid var(--glass-border);">
                                <span><i class="bi {{ $meta['icon'] }} me-1" style="color:var(--accent-{{ $meta['color'] }});"></i><small style="color:var(--text-primary);">{{ $meta['label'] }}</small></span>
                                <span class="fw-semibold small" style="color:var(--accent-{{ $meta['color'] }});">{{ currency($amt) }}</span>
                            </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small style="color:var(--text-muted);">Total (Salary + Commission)</small>
                            <div class="stat-value glow-info" style="font-size:1.3rem;">{{ currency($card['totalUnpaid'] + $card['baseSalary']) }}</div>
                        </div>
                        <form method="POST" action="{{ route('receptionist.payouts.quick-pay', $card['id']) }}"
                              onsubmit="return confirm('Generate monthly payout for {{ $card['name'] }}?\nSalary: {{ currency($card['baseSalary']) }}\nCommission: {{ currency($card['totalUnpaid']) }}\nTotal: {{ currency($card['totalUnpaid'] + $card['baseSalary']) }}\n\nThis requires owner approval.');">
                            @csrf
                            <button type="submit" class="btn btn-info btn-sm fw-semibold text-white"><i class="bi bi-send me-1"></i>Generate</button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
