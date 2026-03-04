@extends('layouts.app')
@section('title', 'Low Stock Alerts')

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-exclamation-triangle me-2"></i>Low-Stock Alerts</h1>
            <p class="page-subtitle">Items below minimum stock level</p>
        </div>
    </div>

    @if ($userDepartment)
        <div class="alert-banner-info mb-4">
            <i class="bi bi-info-circle me-2"></i>Showing low-stock items for <strong>{{ ucfirst($userDepartment) }}</strong> department only.
        </div>
    @endif

    @if (count($items) > 0)
        <div class="glass-card fade-in delay-1">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>SKU</th>
                            <th>Department</th>
                            <th>Current Stock</th>
                            <th>Minimum Level</th>
                            <th>Shortage</th>
                            <th>Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td class="fw-medium">{{ $item['name'] }}</td>
                                <td><code>{{ $item['sku'] }}</code></td>
                                <td><span class="badge-glass">{{ ucfirst($item['department']) }}</span></td>
                                <td>
                                    <span class="badge-glass" style="background:rgba(var(--accent-danger-rgb),0.18);color:var(--accent-danger);">{{ $item['current_stock'] }}</span>
                                </td>
                                <td>{{ $item['minimum_stock_level'] }}</td>
                                <td>
                                    <strong style="color:var(--accent-danger);">{{ $item['shortage'] }}</strong>
                                </td>
                                <td>{{ $item['unit'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="glass-card fade-in delay-1 text-center">
            <div class="alert-banner-success mb-0">
                <i class="bi bi-check-circle me-2"></i><strong>All clear!</strong> No items are currently below minimum stock level.
            </div>
        </div>
    @endif
</div>
@endsection
