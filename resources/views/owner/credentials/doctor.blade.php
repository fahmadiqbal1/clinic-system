@extends('layouts.app')
@section('title', $user->name . ' Credentials — ' . config('app.name'))

@section('content')
<div class="fade-in">
    <div class="page-header mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-person-badge me-2"></i>{{ $user->name }}</h1>
            <p class="page-subtitle">
                {{ $user->specialty ?: 'No specialty recorded' }}
                &mdash; Submitted: {{ $user->credentials_submitted_at ? $user->credentials_submitted_at->format('d M Y H:i') : 'Not yet' }}
            </p>
        </div>
        <a href="{{ route('owner.credentials.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($credentials->count() > 0)
        @foreach($credentials as $credential)
        <div class="glass-card mb-3 fade-in">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div>
                    <div class="fw-semibold text-capitalize mb-1">
                        <i class="bi bi-file-earmark me-2" style="color:var(--accent-primary);"></i>
                        {{ str_replace('_', ' ', $credential->type) }}
                    </div>
                    <div class="small text-muted">{{ $credential->original_filename }}</div>
                    <div class="small text-muted">Uploaded: {{ $credential->uploaded_at->format('d M Y H:i') }}</div>
                    @if($credential->is_verified)
                        <div class="small mt-1" style="color:var(--accent-success);">
                            <i class="bi bi-check-circle me-1"></i>
                            Verified {{ $credential->verified_at->format('d M Y') }}
                            @if($credential->verifiedBy) by {{ $credential->verifiedBy->name }} @endif
                        </div>
                    @elseif($credential->verification_notes)
                        <div class="small mt-1 text-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>Resubmission required
                        </div>
                        <div class="small text-muted fst-italic mt-1">{{ $credential->verification_notes }}</div>
                    @else
                        <div class="small mt-1 text-warning">
                            <i class="bi bi-hourglass-split me-1"></i>Awaiting verification
                        </div>
                    @endif
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-start">
                    <a href="{{ route('owner.credentials.verify', $credential) }}"
                       class="btn btn-sm btn-outline-success"
                       onclick="return confirm('Mark this credential as verified?')"
                       @if($credential->is_verified) disabled @endif>
                        <i class="bi bi-check-lg me-1"></i>Verify
                    </a>

                    {{-- Reject modal trigger --}}
                    <button type="button" class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#rejectModal{{ $credential->id }}">
                        <i class="bi bi-x-lg me-1"></i>Request Resubmission
                    </button>
                </div>
            </div>
        </div>

        {{-- Reject Modal --}}
        <div class="modal fade" id="rejectModal{{ $credential->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('owner.credentials.reject', $credential) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Request Resubmission</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">Reason for rejection <span class="text-danger">*</span></label>
                            <textarea name="verification_notes" class="form-control" rows="3"
                                placeholder="Explain what needs to be corrected or resubmitted..." required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Send Rejection</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    @else
        <div class="empty-state fade-in">
            <i class="bi bi-folder2" style="font-size:2rem;opacity:0.3;"></i>
            <h6 class="mt-3 mb-1">No credentials uploaded yet</h6>
            <p class="small mb-0" style="color:var(--text-muted);">The doctor has not submitted any documents.</p>
        </div>
    @endif

    {{-- Verify route uses GET redirect — add POST form wrapper --}}
    {{-- Note: verify() is POST so we need hidden form approach --}}
    @push('scripts')
    <script>
    document.querySelectorAll('a[href*="/verify/"]').forEach(function(link) {
        if (link.getAttribute('disabled') !== null) return;
        link.addEventListener('click', function(e) {
            if (!confirm('Mark this credential as verified?')) { e.preventDefault(); return; }
            e.preventDefault();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = link.href;
            const csrf = document.createElement('input');
            csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);
            document.body.appendChild(form);
            form.submit();
        });
    });
    </script>
    @endpush
</div>
@endsection
