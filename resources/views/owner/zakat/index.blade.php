@extends('layouts.app')

@section('title', 'Zakat Calculator')

@section('content')
<div class="fade-in">
    <div class="mb-4">
        <h1 class="page-header"><i class="bi bi-heart me-2"></i>Zakat Calculator</h1>
        <p class="page-subtitle">Calculate zakat on owner's net profit for a given period. Zakat is period-based, not per-invoice.</p>
    </div>

    {{-- Period Selector --}}
    <div class="glass-card fade-in delay-1 mb-4">
        <h6 class="form-section-title"><i class="bi bi-calendar-range me-2"></i>Select Period</h6>
        <form method="GET" action="{{ route('owner.zakat.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="from" class="form-label small text-muted">Period Start</label>
                <input type="date" name="from" class="form-control" value="{{ $from }}">
            </div>
            <div class="col-md-3">
                <label for="to" class="form-label small text-muted">Period End</label>
                <input type="date" name="to" class="form-control" value="{{ $to }}">
            </div>
            <div class="col-md-2">
                <label for="zakat_percentage" class="form-label small text-muted">Zakat %</label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0.01" max="100" name="zakat_percentage" class="form-control" value="{{ $zakat_percentage }}">
                    <span class="input-group-text"><i class="bi bi-percent"></i></span>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-calculator me-1"></i> Preview
                </button>
            </div>
        </form>
    </div>

    {{-- Preview Breakdown Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="glass-stat hover-lift fade-in delay-1">
                <div class="stat-icon stat-icon-success"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <div class="stat-value" style="font-size: 1rem;">{{ currency($preview['total_revenue']) }}</div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="glass-stat hover-lift fade-in delay-2">
                <div class="stat-icon stat-icon-warning"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="stat-value" style="font-size: 1rem;">{{ currency($preview['total_cogs']) }}</div>
                    <div class="stat-label">COGS</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="glass-stat hover-lift fade-in delay-2">
                <div class="stat-icon stat-icon-info"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-value" style="font-size: 1rem;">{{ currency($preview['total_commissions']) }}</div>
                    <div class="stat-label">Staff Commissions</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="glass-stat hover-lift fade-in delay-3">
                <div class="stat-icon stat-icon-danger"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="stat-value" style="font-size: 1rem;">{{ currency($preview['total_expenses']) }}</div>
                    <div class="stat-label">Expenses</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="glass-stat hover-lift fade-in delay-3">
                <div class="stat-icon stat-icon-primary"><i class="bi bi-wallet2"></i></div>
                <div>
                    <div class="stat-value" style="font-size: 1rem;">{{ currency($preview['owner_net_profit']) }}</div>
                    <div class="stat-label">Owner Net Profit</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="glass-stat hover-lift fade-in delay-4" style="border: 1px solid rgba(25,135,84,0.3);">
                <div class="stat-icon stat-icon-success glow-success"><i class="bi bi-heart"></i></div>
                <div>
                    <div class="stat-value" style="font-size: 1rem;">{{ currency($preview['zakat_amount']) }}</div>
                    <div class="stat-label">Zakat ({{ $preview['zakat_percentage'] }}%)</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Formula --}}
    <div class="glass-card accent-left-info fade-in delay-2 mb-4">
        <div class="d-flex gap-2 align-items-start">
            <i class="bi bi-calculator text-info" style="font-size: 1.25rem; margin-top: 2px;"></i>
            <div>
                <strong>Formula:</strong><br>
                Owner Net Profit = Revenue ({{ currency($preview['total_revenue']) }})
                &minus; COGS ({{ currency($preview['total_cogs']) }})
                &minus; Staff Commissions ({{ currency($preview['total_commissions']) }})
                &minus; Expenses ({{ currency($preview['total_expenses']) }})
                = <strong>{{ currency($preview['owner_net_profit']) }}</strong>
                <br>
                Zakat = {{ $preview['zakat_percentage'] }}% &times; {{ currency(max(0, $preview['owner_net_profit'])) }}
                = <strong>{{ currency($preview['zakat_amount']) }}</strong>
                @if($preview['owner_net_profit'] <= 0)
                    <br><span class="text-muted">(No zakat when net profit is zero or negative)</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Record Zakat --}}
    <div class="glass-card fade-in delay-3 mb-4">
        <h6 class="form-section-title"><i class="bi bi-save me-2"></i>Record Zakat Transaction</h6>
        <form method="POST" action="{{ route('owner.zakat.calculate') }}">
            @csrf
            <input type="hidden" name="period_start" value="{{ $from }}">
            <input type="hidden" name="period_end" value="{{ $to }}">
            <input type="hidden" name="zakat_percentage" value="{{ $zakat_percentage }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label small text-muted"><i class="bi bi-sticky me-1"></i>Notes (optional)</label>
                    <input type="text" name="notes" class="form-control" placeholder="e.g., Monthly zakat calculation for January">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Record this zakat calculation of {{ currency($preview['zakat_amount']) }}?')">
                        <i class="bi bi-check-lg me-1"></i> Record Zakat Transaction
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Zakat History --}}
    <div class="glass-card fade-in delay-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Zakat History</h5>

        @if($history->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle table-sm mb-0">
                    <thead>
                        <tr>
                            <th class="text-uppercase small text-muted">Period</th>
                            <th class="text-uppercase small text-muted text-end">Revenue</th>
                            <th class="text-uppercase small text-muted text-end">COGS</th>
                            <th class="text-uppercase small text-muted text-end">Commissions</th>
                            <th class="text-uppercase small text-muted text-end">Expenses</th>
                            <th class="text-uppercase small text-muted text-end">Net Profit</th>
                            <th class="text-uppercase small text-muted text-end">Zakat %</th>
                            <th class="text-uppercase small text-muted text-end">Zakat Amount</th>
                            <th class="text-uppercase small text-muted">Calculated By</th>
                            <th class="text-uppercase small text-muted">Notes</th>
                            <th class="text-uppercase small text-muted">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $tx)
                            <tr>
                                <td class="text-nowrap"><i class="bi bi-calendar-range me-1 text-muted"></i>{{ $tx->period_start }} &mdash; {{ $tx->period_end }}</td>
                                <td class="text-end">{{ currency($tx->total_revenue) }}</td>
                                <td class="text-end">{{ currency($tx->total_cogs) }}</td>
                                <td class="text-end">{{ currency($tx->total_commissions) }}</td>
                                <td class="text-end">{{ currency($tx->total_expenses) }}</td>
                                <td class="text-end {{ $tx->owner_net_profit >= 0 ? 'text-success' : 'text-danger' }}">{{ currency($tx->owner_net_profit) }}</td>
                                <td class="text-end">{{ $tx->zakat_percentage }}%</td>
                                <td class="text-end fw-bold">{{ currency($tx->zakat_amount) }}</td>
                                <td><i class="bi bi-person me-1 text-muted"></i>{{ $tx->calculator?->name ?? 'System' }}</td>
                                <td class="text-muted">{{ $tx->notes ?? '-' }}</td>
                                <td class="text-muted text-nowrap">{{ $tx->created_at->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $history->links() }}
            </div>
        @else
            <div class="empty-state">
                <i class="bi bi-heart" style="font-size: 2.5rem;"></i>
                <p class="mt-2 mb-0">No zakat transactions recorded yet.</p>
            </div>
        @endif
    </div>
</div>
@endsection