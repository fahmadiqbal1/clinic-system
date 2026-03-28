@extends('layouts.app')
@section('title', 'Notifications — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header mb-4 fade-in d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-bell me-2"></i>Notifications</h1>
            <p class="page-subtitle">Your recent alerts and updates</p>
        </div>
        @if(auth()->user()->unreadNotifications->isNotEmpty())
            <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-check2-all me-1"></i>Mark All as Read
                </button>
            </form>
        @endif
    </div>

    <div class="card fade-in">
        <div class="card-body p-0">
            @if($notifications->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash" style="font-size:3rem;"></i>
                    <p class="mt-3">No notifications yet.</p>
                </div>
            @else
                <div class="list-group list-group-flush">
                    @foreach($notifications as $notif)
                        @php
                            $data  = $notif->data;
                            $isRead = $notif->read_at !== null;
                            $color = $data['color'] ?? 'primary';
                            $icon  = $data['icon']  ?? 'bi-bell';
                            $url   = $data['url']   ?? '#';
                        @endphp
                        <a href="{{ $url }}"
                           class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3 {{ $isRead ? 'opacity-75' : '' }}"
                           onclick="markNotifRead('{{ $notif->id }}')">
                            <div class="flex-shrink-0 pt-1">
                                <i class="bi {{ $icon }}" style="font-size:1.3rem; color:var(--accent-{{ $color }});"></i>
                            </div>
                            <div class="flex-fill">
                                <div class="d-flex justify-content-between align-items-start">
                                    <strong style="font-size:0.9rem;">{{ $data['title'] ?? 'Notification' }}</strong>
                                    <div class="d-flex align-items-center gap-2 ms-3 flex-shrink-0">
                                        @if(!$isRead)
                                            <span class="badge bg-primary rounded-pill" style="font-size:0.65rem;">New</span>
                                        @endif
                                        <small class="text-muted" style="font-size:0.75rem;">{{ $notif->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                                <p class="mb-0 mt-1 text-muted" style="font-size:0.85rem;">{{ $data['message'] ?? '' }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="px-4 py-3">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function markNotifRead(id) {
    fetch(`/notifications/${id}/read`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    });
}
</script>
@endpush
