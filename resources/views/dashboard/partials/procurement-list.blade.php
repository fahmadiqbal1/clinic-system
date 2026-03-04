@if (count($requests) > 0)
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Department</th>
                    <th>Requested By</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Date</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requests as $request)
                    <tr>
                        <td class="fw-medium">#{{ $request['id'] }}</td>
                        <td><span class="badge-glass">{{ ucfirst($request['department']) }}</span></td>
                        <td>{{ $request['requested_by'] }}</td>
                        <td>
                            @if($request['status'] === 'pending')
                                <span class="badge-glass" style="background:rgba(var(--accent-info-rgb),0.18);color:var(--accent-info);">Pending</span>
                            @elseif($request['status'] === 'approved')
                                <span class="badge-glass" style="background:rgba(var(--accent-primary-rgb),0.18);color:var(--accent-primary);">Approved</span>
                            @else
                                <span class="badge-glass" style="background:rgba(var(--accent-success-rgb),0.18);color:var(--accent-success);">{{ ucfirst($request['status']) }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge-glass">{{ $request['item_count'] }}</span>
                        </td>
                        <td><small style="color:var(--text-muted);">{{ $request['requested_at'] }}</small></td>
                        <td><small style="color:var(--text-muted);">{{ $request['notes'] ?? '—' }}</small></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="empty-state py-3">
        <p class="small mb-0">No {{ $title ?? 'procurement requests' }} found.</p>
    </div>
@endif
