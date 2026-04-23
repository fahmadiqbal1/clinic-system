@extends('layouts.app')
@section('title', 'Property & Equipment Admin — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 fade-in">
        <div>
            <h2 class="mb-1"><i class="bi bi-building-gear me-2" style="color:var(--accent-primary);"></i>Property & Equipment Admin</h2>
            <p class="page-subtitle mb-0">Lease tracking, equipment warranties, service history, and vendor contacts</p>
        </div>
        <a href="{{ route('owner.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    @if(!$enabled)
    <div class="alert alert-warning fade-in mb-4">
        <i class="bi bi-toggle-off me-2"></i>
        <strong>admin.nocobase.enabled</strong> is <strong>OFF</strong>.
        Enable it in <a href="{{ route('owner.platform-settings.index') }}">Platform Settings</a>,
        then start NocoBase with:
        <code class="d-block mt-1">docker compose -f docker-compose.yml -f docker-compose.admin.yml up -d nocobase</code>
    </div>
    @endif

    <div class="row g-3 fade-in">
        <div class="col-lg-8">
            <div class="glass-card p-4">
                <h5 class="mb-3"><i class="bi bi-box-arrow-up-right me-2"></i>Open NocoBase</h5>
                <p class="text-muted mb-3">
                    NocoBase runs at <code>{{ $nocobaseUrl }}</code> (accessible from this machine only).
                    Log in with your NocoBase Owner credentials to manage properties, equipment, service history, and vendors.
                    All create / update / delete actions are automatically logged to the
                    <a href="{{ route('owner.activity-feed') }}">Activity Feed</a> via webhook.
                </p>
                <a href="{{ $nocobaseUrl }}" target="_blank" rel="noopener"
                   class="btn btn-primary {{ !$enabled ? 'disabled' : '' }}">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Open NocoBase Admin
                </a>
                @if(!$enabled)
                <p class="text-muted small mt-2 mb-0">Enable the flag above to activate this link.</p>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card p-4">
                <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Schema</h5>
                <ul class="list-unstyled mb-0 text-muted small">
                    <li class="mb-1"><i class="bi bi-building me-2"></i><strong>Properties</strong> — lease tracking</li>
                    <li class="mb-1"><i class="bi bi-tools me-2"></i><strong>Equipment</strong> — serial, warranty, vendor</li>
                    <li class="mb-1"><i class="bi bi-wrench me-2"></i><strong>Service History</strong> — per-equipment log</li>
                    <li class="mb-0"><i class="bi bi-people me-2"></i><strong>Vendors</strong> — contacts, contracts</li>
                </ul>
                <hr class="my-3">
                <p class="text-muted small mb-0">
                    Schema export: <code>nocobase/schema.json</code><br>
                    Re-import via NocoBase UI: Settings → Import/Export.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
