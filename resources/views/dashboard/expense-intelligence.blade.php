@extends('layouts.app')
@section('title', 'Expense Intelligence')

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-bar-chart-line me-2"></i>Expense Intelligence</h1>
            <p class="page-subtitle">Financial expense analysis and reporting</p>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" id="from" class="form-control form-control-sm" value="{{ $fromDate }}">
            </div>
            <div>
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" id="to" class="form-control form-control-sm" value="{{ $toDate }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
        </form>
    </div>

    <div class="alert-banner-info mb-4">
        <i class="bi bi-calendar-range me-2"></i><strong>Period:</strong> {{ \Carbon\Carbon::createFromFormat('Y-m-d', $fromDate)->format('M d, Y') }} 
        to {{ \Carbon\Carbon::createFromFormat('Y-m-d', $toDate)->format('M d, Y') }}
    </div>

    {{-- Summary Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="glass-stat">
                <div class="stat-label">Total Expenses</div>
                <div class="stat-value">{{ currency($summary['total']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-stat">
                <div class="stat-label">Average Daily</div>
                <div class="stat-value">{{ currency($summary['average_daily']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-stat">
                <div class="stat-label">Procurement</div>
                <div class="stat-value">{{ currency($summary['by_type']['procurement']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="glass-stat">
                <div class="stat-label">Other Expenses</div>
                <div class="stat-value">{{ currency($summary['by_type']['other']) }}</div>
            </div>
        </div>
    </div>

    {{-- Breakdowns --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="glass-card fade-in delay-1">
                <h6 class="form-section-title"><i class="bi bi-building me-2"></i>Expenses by Department</h6>
                <div class="info-grid">
                    <div class="info-grid-item">
                        <span class="info-label">Pharmacy</span>
                        <span class="info-value">{{ currency($summary['by_department']['pharmacy']) }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Laboratory</span>
                        <span class="info-value">{{ currency($summary['by_department']['laboratory']) }}</span>
                    </div>
                    <div class="info-grid-item">
                        <span class="info-label">Radiology</span>
                        <span class="info-value">{{ currency($summary['by_department']['radiology']) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="glass-card fade-in delay-1">
                <h6 class="form-section-title"><i class="bi bi-list-ol me-2"></i>Top Expense Categories</h6>
                @forelse ($topDescriptions as $description => $cost)
                    <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--glass-border);">
                        <span class="small" style="color:var(--text-secondary);">{{ substr($description, 0, 50) }}...</span>
                        <span class="fw-medium">{{ currency($cost) }}</span>
                    </div>
                @empty
                    <div class="empty-state py-3">
                        <p class="small mb-0">No expenses recorded</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
