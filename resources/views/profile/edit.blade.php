@extends('layouts.app')
@section('title', 'Profile — ' . config('app.name'))

@section('content')
<div class="container mt-4" style="max-width:800px;">
    {{-- Profile Header --}}
    <div class="page-header mb-4">
        <div class="d-flex align-items-center gap-3 mb-2">
            <div class="stat-icon stat-icon-primary" style="width:56px;height:56px;font-size:1.4rem;">
                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
            </div>
            <div>
                <h2 class="mb-0">{{ auth()->user()->name }}</h2>
                <p class="page-subtitle mb-0">
                    <span class="badge bg-primary me-1">{{ ucfirst(auth()->user()->getRoleNames()->first() ?? 'User') }}</span>
                    {{ auth()->user()->email }}
                </p>
            </div>
        </div>
    </div>

    <div class="card mb-4 fade-in delay-1">
        <div class="card-header"><i class="bi bi-person me-2" style="color:var(--accent-primary);"></i>Profile Information</div>
        <div class="card-body">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    <div class="card mb-4 fade-in delay-2">
        <div class="card-header"><i class="bi bi-shield-lock me-2" style="color:var(--accent-warning);"></i>Update Password</div>
        <div class="card-body">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    <div class="card mb-4 fade-in delay-3">
        <div class="card-header"><i class="bi bi-trash3 me-2" style="color:var(--accent-danger);"></i>Delete Account</div>
        <div class="card-body">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</div>
@endsection
