@extends('layouts.app')
@section('title', 'Activity Feed')

@section('content')
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="page-header"><i class="bi bi-clock-history me-2"></i>Activity Feed</h1>
            <p class="page-subtitle">System-wide audit trail and activity timeline</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="glass-card fade-in delay-1 mb-4">
        <form method="GET" action="{{ route('owner.activity-feed') }}" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted"><i class="bi bi-calendar-event me-1"></i>From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted"><i class="bi bi-calendar-event me-1"></i>To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted"><i class="bi bi-person me-1"></i>User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted"><i class="bi bi-funnel me-1"></i>Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $action)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('owner.activity-feed') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    {{-- Timeline --}}
    <div class="glass-card fade-in delay-2">
        @if($logs->count() > 0)
            <div class="activity-timeline">
                @php $lastDate = null; @endphp
                @foreach($logs as $log)
                    @php $logDate = $log->created_at->format('M d, Y'); @endphp
                    @if($logDate !== $lastDate)
                        <div class="d-flex align-items-center gap-2 mb-2 {{ !$loop->first ? 'mt-4' : '' }}">
                            <i class="bi bi-calendar3 text-muted"></i>
                            <span class="fw-semibold small" style="color:var(--text-tertiary);">{{ $logDate }}</span>
                            <hr class="flex-grow-1 m-0" style="border-color:var(--glass-border);">
                        </div>
                        @php $lastDate = $logDate; @endphp
                    @endif

                    <div class="d-flex gap-3 py-2 px-2" style="border-bottom:1px solid rgba(255,255,255,0.04);">
                        {{-- Icon --}}
                        <div class="flex-shrink-0">
                            <div class="stat-icon stat-icon-{{ $log->color }}" style="width:2rem;height:2rem;font-size:0.8rem;">
                                <i class="bi {{ $log->icon }}"></i>
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold small" style="color:var(--text-primary);">
                                    {{ $log->user?->name ?? 'System' }}
                                </span>
                                <span class="small" style="color:var(--text-secondary);">
                                    {{ $log->human_readable }}
                                </span>
                                @if($log->auditable_id)
                                    <span class="badge-glass" style="font-size:0.7rem;">
                                        #{{ $log->auditable_id }}
                                    </span>
                                @endif
                            </div>

                            {{-- Changes summary --}}
                            @if($log->after_state && is_array($log->after_state))
                                <div class="mt-1">
                                    @foreach(array_slice($log->after_state, 0, 3) as $key => $value)
                                        <span class="badge-glass me-1" style="font-size:0.68rem;">
                                            {{ str_replace('_', ' ', $key) }}: {{ is_string($value) ? Str::limit($value, 20) : json_encode($value) }}
                                        </span>
                                    @endforeach
                                    @if(count($log->after_state) > 3)
                                        <span class="text-muted" style="font-size:0.68rem;">+{{ count($log->after_state) - 3 }} more</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Time --}}
                        <div class="flex-shrink-0 text-end">
                            <small class="text-muted" style="font-size:0.75rem;">
                                {{ $log->created_at->format('H:i:s') }}
                            </small>
                            <br>
                            <small class="text-muted" style="font-size:0.68rem;">
                                {{ $log->created_at->diffForHumans() }}
                            </small>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $logs->links() }}
            </div>
        @else
            <div class="empty-state">
                <i class="bi bi-clock-history" style="font-size: 2.5rem;"></i>
                <h3 class="h6 fw-medium mt-2">No activity found</h3>
                <p class="small text-muted mb-0">Activity will appear here as users interact with the system.</p>
            </div>
        @endif
    </div>
</div>
@endsection
