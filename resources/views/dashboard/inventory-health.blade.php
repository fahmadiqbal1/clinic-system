@extends('layouts.app')
@section('title', 'Inventory Health')

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-heart-pulse me-2"></i>Inventory Health Dashboard</h1>
            <p class="page-subtitle">Current stock levels (derived from ledger)</p>
        </div>
    </div>

    @if ($userDepartment)
        <div class="alert-banner-info mb-4">
            <i class="bi bi-info-circle me-2"></i>Showing inventory for <strong>{{ ucfirst($userDepartment) }}</strong> department only.
        </div>
    @endif

    @foreach ($itemsByDepartment as $item)
        <div class="glass-card fade-in delay-1 mb-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h6 class="mb-1 fw-medium">{{ $item['name'] }}</h6>
                    <p class="small mb-1" style="color:var(--text-muted);">SKU: {{ $item['sku'] }}</p>
                    <p class="small mb-1" style="color:var(--text-muted);">Department: <strong>{{ ucfirst($item['department']) }}</strong></p>
                    @if ($item['chemical_formula'])
                        <p class="small mb-0" style="color:var(--text-muted);">Formula: <code>{{ $item['chemical_formula'] }}</code></p>
                    @endif
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <span class="small me-2" style="color:var(--text-muted);">Current Stock:</span>
                                <strong class="h5 mb-0">{{ $item['current_stock'] }}</strong>
                                <span class="small ms-1" style="color:var(--text-muted);">{{ $item['unit'] }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <span class="small me-2" style="color:var(--text-muted);">Minimum:</span>
                                <strong class="h5 mb-0">{{ $item['minimum_stock_level'] }}</strong>
                                <span class="small ms-1" style="color:var(--text-muted);">{{ $item['unit'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        @if ($item['below_minimum'])
                            <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.18);color:var(--accent-danger);">Below Minimum</span>
                        @else
                            <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.18);color:var(--accent-success);">In Stock</span>
                        @endif
                        @if ($item['requires_prescription'])
                            <span class="badge-glass" style="background:rgba(var(--accent-warning-rgb),0.18);color:var(--accent-warning);">Requires Prescription</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @if (empty($itemsByDepartment) || count($itemsByDepartment) === 0)
        <div class="empty-state">
            <i class="bi bi-box-seam" style="font-size:2.5rem;opacity:0.3;"></i>
            <h6 class="mt-3 mb-1">No inventory items found</h6>
        </div>
    @endif
</div>
@endsection
