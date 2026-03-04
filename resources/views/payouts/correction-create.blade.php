@extends('layouts.app')
@section('title', 'Payout Correction — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header fade-in">
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-pencil-square me-2" style="color:var(--accent-warning);"></i>Create Payout Correction</h1>
        <p class="page-subtitle">Adjust a confirmed payout with a correction entry</p>
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
        <div class="col-lg-8">
            <div class="glass-card p-4 mb-4 fade-in delay-1">
                <h2 class="h5 fw-bold mb-3"><i class="bi bi-receipt me-2" style="color:var(--accent-info);"></i>Original Payout</h2>

                <div class="row g-3 p-3 rounded" style="background:rgba(var(--accent-info-rgb),0.08); border:1px solid rgba(var(--accent-info-rgb),0.2);">
                    <div class="col-6">
                        <p class="small mb-1" style="color:var(--text-muted);">Staff Member</p>
                        <p class="h5 fw-semibold mb-0">{{ $payout->doctor?->name ?? 'Unknown' }}</p>
                    </div>
                    <div class="col-6">
                        <p class="small mb-1" style="color:var(--text-muted);">Period</p>
                        <p class="h5 fw-semibold mb-0">
                            {{ $payout->period_start?->format('M d') ?? 'N/A' }} — {{ $payout->period_end?->format('M d, Y') ?? 'N/A' }}
                        </p>
                    </div>
                    <div class="col-6">
                        <p class="small mb-1" style="color:var(--text-muted);">Total Earnings</p>
                        <p class="h5 fw-semibold mb-0" style="color:var(--accent-info);">{{ currency($payout->total_amount) }}</p>
                    </div>
                    <div class="col-6">
                        <p class="small mb-1" style="color:var(--text-muted);">Paid Amount</p>
                        <p class="h5 fw-semibold mb-0" style="color:var(--accent-success);">{{ currency($payout->paid_amount) }}</p>
                    </div>
                </div>
            </div>

            <div class="glass-card p-4 fade-in delay-2">
                <h2 class="h5 fw-bold mb-3"><i class="bi bi-sliders me-2" style="color:var(--accent-warning);"></i>Correction Details</h2>

                <form action="{{ route('owner.payouts.correction-store', $payout) }}" method="POST">
                    @csrf

                    <!-- Correction Amount -->
                    <div class="mb-3">
                        <label for="amount" class="form-label">
                            <i class="bi bi-cash-coin me-1" style="color:var(--accent-success);"></i>Adjustment Amount
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            <input type="number" step="0.01" name="amount" id="amount" 
                                value="{{ old('amount') }}" 
                                placeholder="0.00"
                                class="form-control @error('amount') is-invalid @enderror" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text">
                            Use positive values for additions (e.g., bonus, back pay).
                            Use negative values for deductions (e.g., overpayment recovery, penalty).
                        </div>
                    </div>

                    <!-- Reason -->
                    <div class="mb-3">
                        <label for="reason" class="form-label">
                            <i class="bi bi-chat-text me-1" style="color:var(--accent-primary);"></i>Reason for Correction
                        </label>
                        <textarea name="reason" id="reason" rows="4" 
                            class="form-control @error('reason') is-invalid @enderror"
                            placeholder="Explain the reason for this correction adjustment..." required>{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3" style="border-top:1px solid var(--glass-border);">
                        <a href="{{ route('reception.payouts.show', $payout) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-pencil-square me-1"></i>Create Correction
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info Sidebar -->
        <div class="col-lg-4">
            <div class="glass-card p-4 fade-in delay-3" style="border-left:3px solid var(--accent-primary);">
                <h3 class="h5 fw-bold mb-3"><i class="bi bi-info-circle me-2" style="color:var(--accent-primary);"></i>How Corrections Work</h3>
                <ul class="list-unstyled small mb-0" style="color:var(--text-muted);">
                    <li class="d-flex gap-2 mb-2">
                        <span class="fw-bold" style="color:var(--accent-primary);">1.</span>
                        <span>Corrections create new payout records; original payouts are never modified.</span>
                    </li>
                    <li class="d-flex gap-2 mb-2">
                        <span class="fw-bold" style="color:var(--accent-primary);">2.</span>
                        <span>Corrections are automatically marked as confirmed and cannot be changed.</span>
                    </li>
                    <li class="d-flex gap-2 mb-2">
                        <span class="fw-bold" style="color:var(--accent-primary);">3.</span>
                        <span>Both positive and negative amounts are supported for flexibility.</span>
                    </li>
                    <li class="d-flex gap-2">
                        <span class="fw-bold" style="color:var(--accent-primary);">4.</span>
                        <span>A clear audit trail is maintained for all adjustments.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
