@extends('layouts.app')
@section('title', 'No Patient Profile — ' . config('app.name'))

@section('content')
<div class="container mt-4">
    <div class="page-header fade-in">
        <h2 class="mb-1"><i class="bi bi-person-x me-2" style="color:var(--accent-warning);"></i>No Patient Profile Found</h2>
        <p class="page-subtitle">Your account is not linked to a patient record yet.</p>
    </div>
    <div class="card fade-in delay-1">
        <div class="card-body text-center py-5">
            <i class="bi bi-info-circle" style="font-size:3rem; color:var(--accent-info);"></i>
            <p class="mt-3" style="color:var(--text-secondary);">Please contact the clinic reception to link your account to your patient record.</p>
        </div>
    </div>
</div>
@endsection
