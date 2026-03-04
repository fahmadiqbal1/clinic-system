@extends('layouts.app')
@section('title', 'Service Catalog — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="page-header mb-1"><i class="bi bi-journal-medical me-2" style="color:var(--accent-primary);"></i>Service Catalog</h1>
            <p class="page-subtitle">Manage services by department</p>
        </div>
        <a href="{{ route('owner.service-catalog.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Service</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success fade-in">{{ session('success') }}</div>
    @endif

    @php
        $selectedDept = $filters['department'] ?? null;
        $deptMeta = [
            'consultation' => ['label' => 'Consultation', 'icon' => 'bi-heart-pulse', 'color' => 'var(--accent-danger)', 'rgb' => 'var(--accent-danger-rgb)'],
            'lab'          => ['label' => 'Laboratory', 'icon' => 'bi-droplet', 'color' => 'var(--accent-info)', 'rgb' => 'var(--accent-info-rgb)'],
            'radiology'    => ['label' => 'Radiology', 'icon' => 'bi-radioactive', 'color' => 'var(--accent-warning)', 'rgb' => 'var(--accent-warning-rgb)'],
            'pharmacy'     => ['label' => 'Pharmacy', 'icon' => 'bi-capsule', 'color' => 'var(--accent-success)', 'rgb' => 'var(--accent-success-rgb)'],
        ];
    @endphp

    {{-- ==================== DEPARTMENT CARDS ==================== --}}
    <div class="row g-3 mb-4 fade-in delay-1">
        @foreach($deptMeta as $deptKey => $meta)
        @php
            $count = $departmentCounts[$deptKey] ?? 0;
            $active = $activeCounts[$deptKey] ?? 0;
            $isSelected = $selectedDept === $deptKey;
        @endphp
        <div class="col-6 col-md-3">
            <a href="{{ route('owner.service-catalog.index', ['department' => $deptKey]) }}"
               class="glass-card p-3 d-block text-decoration-none h-100 position-relative overflow-hidden"
               style="{{ $isSelected ? 'border: 2px solid ' . $meta['color'] . '; box-shadow: 0 0 20px rgba(' . $meta['rgb'] . ', 0.3);' : '' }}">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi {{ $meta['icon'] }} me-2" style="font-size:1.6rem; color:{{ $meta['color'] }};"></i>
                    <h6 class="mb-0 fw-semibold" style="color:var(--text-primary);">{{ $meta['label'] }}</h6>
                </div>
                <div class="d-flex justify-content-between align-items-end">
                    <div>
                        <span class="stat-value" style="font-size:1.8rem; color:{{ $meta['color'] }};">{{ $count }}</span>
                        <span class="d-block small" style="color:var(--text-muted);">services</span>
                    </div>
                    <span class="badge" style="background:rgba({{ $meta['rgb'] }},0.15); color:{{ $meta['color'] }};">{{ $active }} active</span>
                </div>
                @if($isSelected)
                    <div style="position:absolute; top:0; right:0; padding:6px 10px;">
                        <i class="bi bi-check-circle-fill" style="color:{{ $meta['color'] }}; font-size:1.2rem;"></i>
                    </div>
                @endif
            </a>
        </div>
        @endforeach
    </div>

    {{-- ==================== DEPARTMENT DETAIL VIEW ==================== --}}
    @if($selectedDept && isset($deptMeta[$selectedDept]))
    @php $dm = $deptMeta[$selectedDept]; @endphp

    {{-- Back + Search Bar --}}
    <div class="glass-card p-3 mb-3 fade-in delay-2">
        <div class="d-flex align-items-center gap-2 mb-2">
            <a href="{{ route('owner.service-catalog.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All Departments</a>
            <h5 class="mb-0 ms-1"><i class="bi {{ $dm['icon'] }} me-1" style="color:{{ $dm['color'] }};"></i>{{ $dm['label'] }} Services</h5>
        </div>
        <form method="GET" action="{{ route('owner.service-catalog.index') }}" class="row g-2 align-items-end">
            <input type="hidden" name="department" value="{{ $selectedDept }}">
            <div class="col-md-4">
                <label class="form-label small">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, code, category..." value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected(($filters['category'] ?? '') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('owner.service-catalog.index', ['department' => $selectedDept]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Reset</a>
            </div>
        </form>
    </div>

    {{-- Services grouped by category --}}
    @if($services && $services->count() > 0)
        @foreach($services as $category => $items)
        <div class="glass-card mb-3 fade-in delay-2">
            <div class="p-3 d-flex justify-content-between align-items-center" style="border-bottom:1px solid var(--glass-border);">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-tag me-1" style="color:{{ $dm['color'] }};"></i>{{ $category ?: 'Uncategorised' }}</h6>
                <span class="badge bg-secondary">{{ $items->count() }} services</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th style="width:140px;">Price</th>
                            <th style="width:90px;">Status</th>
                            <th style="width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $service)
                        <tr data-service-id="{{ $service->id }}">
                            <td>
                                <span class="fw-medium">{{ $service->name }}</span>
                                @if($service->description)
                                    <br><small style="color:var(--text-muted);">{{ Str::limit($service->description, 60) }}</small>
                                @endif
                            </td>
                            <td><span class="code-tag">{{ $service->code ?? '—' }}</span></td>
                            <td>
                                {{-- Inline-editable price --}}
                                <div class="price-display" data-id="{{ $service->id }}" style="cursor:pointer;" title="Click to edit">
                                    <span class="price-value fw-semibold" style="color:var(--accent-success);">{{ number_format($service->price, 2) }}</span>
                                    <i class="bi bi-pencil-fill ms-1" style="font-size:0.7rem; color:var(--text-muted); opacity:0.5;"></i>
                                </div>
                                <div class="price-edit d-none" data-id="{{ $service->id }}">
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control form-control-sm price-input" value="{{ $service->price }}" step="0.01" min="0" style="max-width:100px;">
                                        <button class="btn btn-sm btn-success price-save-btn" data-id="{{ $service->id }}" title="Save"><i class="bi bi-check"></i></button>
                                        <button class="btn btn-sm btn-outline-secondary price-cancel-btn" data-id="{{ $service->id }}" title="Cancel"><i class="bi bi-x"></i></button>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input status-toggle" type="checkbox" data-id="{{ $service->id }}" {{ $service->is_active ? 'checked' : '' }} title="Toggle active/inactive">
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('owner.service-catalog.edit', $service) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
                                <form action="{{ route('owner.service-catalog.destroy', $service) }}" method="POST" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete {{ addslashes($service->name) }}?')"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    @else
        <div class="glass-card p-5 text-center fade-in delay-2">
            <i class="bi bi-journal-x" style="font-size:2.5rem; color:var(--text-muted);"></i>
            <p class="mt-2" style="color:var(--text-muted);">No services found matching your filters.</p>
        </div>
    @endif

    @else
        {{-- No department selected — show only the cards above --}}
        <div class="glass-card p-4 text-center fade-in delay-2">
            <i class="bi bi-hand-index-thumb" style="font-size:2rem; color:var(--text-muted);"></i>
            <p class="mt-2 mb-0" style="color:var(--text-muted);">Select a department above to view and manage services.</p>
        </div>
    @endif
</div>

{{-- ==================== INLINE EDIT JAVASCRIPT ==================== --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function quickUpdate(serviceId, data) {
        return fetch(`/owner/service-catalog/${serviceId}/quick-update`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        }).then(r => r.json());
    }

    // Click price to edit
    document.querySelectorAll('.price-display').forEach(el => {
        el.addEventListener('click', function () {
            const id = this.dataset.id;
            this.classList.add('d-none');
            document.querySelector(`.price-edit[data-id="${id}"]`).classList.remove('d-none');
            document.querySelector(`.price-edit[data-id="${id}"] .price-input`).focus();
        });
    });

    // Save price
    document.querySelectorAll('.price-save-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const input = document.querySelector(`.price-edit[data-id="${id}"] .price-input`);
            const price = parseFloat(input.value);
            if (isNaN(price) || price < 0) return;

            quickUpdate(id, { price }).then(res => {
                if (res.success) {
                    const display = document.querySelector(`.price-display[data-id="${id}"] .price-value`);
                    display.textContent = res.price;
                    document.querySelector(`.price-edit[data-id="${id}"]`).classList.add('d-none');
                    document.querySelector(`.price-display[data-id="${id}"]`).classList.remove('d-none');
                }
            });
        });
    });

    // Cancel price edit
    document.querySelectorAll('.price-cancel-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            document.querySelector(`.price-edit[data-id="${id}"]`).classList.add('d-none');
            document.querySelector(`.price-display[data-id="${id}"]`).classList.remove('d-none');
        });
    });

    // Enter key saves, Escape cancels
    document.querySelectorAll('.price-input').forEach(input => {
        input.addEventListener('keydown', function (e) {
            const id = this.closest('.price-edit').dataset.id;
            if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector(`.price-save-btn[data-id="${id}"]`).click();
            } else if (e.key === 'Escape') {
                document.querySelector(`.price-cancel-btn[data-id="${id}"]`).click();
            }
        });
    });

    // Toggle active status
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function () {
            const id = this.dataset.id;
            quickUpdate(id, { is_active: this.checked ? 1 : 0 });
        });
    });
});
</script>
@endsection
