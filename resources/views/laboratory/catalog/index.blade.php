@extends('layouts.app')
@section('title', 'Lab Catalog — ' . config('app.name'))

@section('content')
<div class="container py-4">
    {{-- Page Header --}}
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-tags me-2"></i>Laboratory Test Pricing</h1>
            <p class="text-muted mb-0">Manage your test catalog &amp; pricing — changes apply to new invoices immediately</p>
        </div>
        <a href="{{ route('laboratory.catalog.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Add Test
        </a>
    </div>

    {{-- Pending Change Requests --}}
    @if(isset($pendingChanges) && $pendingChanges->count() > 0)
    <div class="glass-panel p-3 mb-4" style="border-left:3px solid var(--accent-warning);">
        <h6 class="fw-bold mb-2"><i class="bi bi-hourglass-split me-2" style="color:var(--accent-warning);"></i>Pending Catalog Changes ({{ $pendingChanges->count() }})</h6>
        @foreach($pendingChanges as $change)
            <div class="d-flex justify-content-between align-items-center mb-1 p-2 rounded" style="background:var(--glass-bg);">
                <div>
                    <span class="badge badge-glass-warning me-1">{{ ucfirst($change->change_action) }}</span>
                    <span class="fw-medium">{{ $change->change_payload['name'] ?? 'Test' }}</span>
                    <small class="ms-2" style="color:var(--text-muted);">by {{ $change->requester?->name }}</small>
                </div>
                <a href="{{ route('procurement.show', $change) }}" class="btn btn-sm btn-outline-info"><i class="bi bi-eye me-1"></i>View</a>
            </div>
        @endforeach
    </div>
    @endif

    @if($categories->count() > 0)
        {{-- Quick Stats --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="glass-stat">
                    <div class="glass-stat-value">{{ $categories->flatten()->count() }}</div>
                    <div class="glass-stat-label">Total Tests</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="glass-stat">
                    <div class="glass-stat-value">{{ $categories->count() }}</div>
                    <div class="glass-stat-label">Categories</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="glass-stat">
                    <div class="glass-stat-value text-success">{{ $categories->flatten()->where('is_active', true)->count() }}</div>
                    <div class="glass-stat-label">Active</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="glass-stat">
                    <div class="glass-stat-value text-warning">{{ $categories->flatten()->where('is_active', false)->count() }}</div>
                    <div class="glass-stat-label">Inactive</div>
                </div>
            </div>
        </div>

        {{-- Category Groups --}}
        @foreach($categories as $category => $tests)
            <div class="glass-panel mb-4">
                <div class="catalog-category-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-folder2-open" style="font-size:1.1rem;color:var(--accent-primary);"></i>
                        <strong>{{ $category ?: 'Uncategorized' }}</strong>
                        <span class="badge-glass ms-2">{{ $tests->count() }} {{ Str::plural('test', $tests->count()) }}</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width:110px;">Code</th>
                                <th>Test Name</th>
                                <th class="d-none d-lg-table-cell">Description</th>
                                <th style="width:120px;">Price</th>
                                <th style="width:120px;" class="d-none d-md-table-cell">Turnaround</th>
                                <th style="width:90px;">Status</th>
                                <th style="width:80px;" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tests as $test)
                                <tr class="{{ !$test->is_active ? 'opacity-50' : '' }}">
                                    <td><span class="code-tag">{{ $test->code }}</span></td>
                                    <td><strong>{{ $test->name }}</strong></td>
                                    <td class="d-none d-lg-table-cell">
                                        <span class="text-muted small">{{ Str::limit($test->description, 60) }}</span>
                                    </td>
                                    <td><span class="price-display">{{ currency($test->price) }}</span></td>
                                    <td class="d-none d-md-table-cell">
                                        @if($test->turnaround_time)
                                            <i class="bi bi-clock text-muted me-1"></i>{{ $test->turnaround_time }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($test->is_active)
                                            <span class="toggle-status active"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                                        @else
                                            <span class="toggle-status inactive"><i class="bi bi-x-circle me-1"></i>Off</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('laboratory.catalog.edit', $test) }}" class="btn btn-sm btn-outline-primary" title="Edit pricing">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @else
        <div class="glass-panel text-center py-5">
            <i class="bi bi-flask" style="font-size:3rem;opacity:0.3;"></i>
            <h5 class="mt-3">No Laboratory Tests Configured</h5>
            <p class="text-muted mb-3">Start by adding your first test to the catalog.</p>
            <a href="{{ route('laboratory.catalog.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Add Your First Test
            </a>
        </div>
    @endif
</div>
@endsection
