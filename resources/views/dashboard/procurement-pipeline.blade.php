@extends('layouts.app')
@section('title', 'Procurement Pipeline')

@section('content')
<div class="fade-in">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-kanban me-2"></i>Procurement Pipeline</h1>
            <p class="page-subtitle">Track procurement requests through workflow</p>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="glass-stat text-center">
                <div class="stat-value">{{ $summary['pending_inventory'] }}</div>
                <div class="stat-label">Pending Inventory</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="glass-stat text-center">
                <div class="stat-value">{{ $summary['pending_service'] }}</div>
                <div class="stat-label">Pending Service</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="glass-stat text-center">
                <div class="stat-value">{{ $summary['approved_inventory'] }}</div>
                <div class="stat-label">Approved Inventory</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="glass-stat text-center">
                <div class="stat-value">{{ $summary['approved_service'] }}</div>
                <div class="stat-label">Approved Service</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="glass-stat text-center">
                <div class="stat-value" style="color:var(--accent-success);">{{ $summary['received'] }}</div>
                <div class="stat-label">Received</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="glass-stat text-center">
                <div class="stat-value" style="color:var(--accent-danger);">{{ $summary['rejected'] }}</div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="glass-card fade-in delay-1">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="pending-inventory-tab" data-bs-toggle="tab" href="#pending-inventory" role="tab">
                    Pending Inventory ({{ count($pendingInventory) }})
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="pending-service-tab" data-bs-toggle="tab" href="#pending-service" role="tab">
                    Pending Service ({{ count($pendingService) }})
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="approved-inventory-tab" data-bs-toggle="tab" href="#approved-inventory" role="tab">
                    Approved Inventory ({{ count($approvedInventory) }})
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="approved-service-tab" data-bs-toggle="tab" href="#approved-service" role="tab">
                    Approved Service ({{ count($approvedService) }})
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="received-tab" data-bs-toggle="tab" href="#received" role="tab">
                    Received ({{ count($received) }})
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="pending-inventory" role="tabpanel">
                @include('dashboard.partials.procurement-list', ['requests' => $pendingInventory, 'title' => 'Pending Inventory Procurements'])
            </div>
            <div class="tab-pane fade" id="pending-service" role="tabpanel">
                @include('dashboard.partials.procurement-list', ['requests' => $pendingService, 'title' => 'Pending Service Procurements'])
            </div>
            <div class="tab-pane fade" id="approved-inventory" role="tabpanel">
                @include('dashboard.partials.procurement-list', ['requests' => $approvedInventory, 'title' => 'Approved Inventory Procurements'])
            </div>
            <div class="tab-pane fade" id="approved-service" role="tabpanel">
                @include('dashboard.partials.procurement-list', ['requests' => $approvedService, 'title' => 'Approved Service Procurements'])
            </div>
            <div class="tab-pane fade" id="received" role="tabpanel">
                @include('dashboard.partials.procurement-list', ['requests' => $received, 'title' => 'Received Procurements'])
            </div>
        </div>
    </div>
</div>
@endsection
