@extends('layouts.app')
@section('title', 'Home — ' . config('app.name'))

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="glass-card p-4">
                <h2 class="h4 fw-bold mb-3">Welcome to {{ config('app.name') }}</h2>
                @auth
                    <p class="fs-5">Hello, <strong>{{ Auth::user()->name }}</strong>!</p>
                    <p style="color:var(--text-secondary);">You are logged in. Use the navigation menu to access your dashboard.</p>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
                @else
                    <p style="color:var(--text-secondary);">Please log in to access the system.</p>
                    <a href="{{ route('login') }}" class="btn btn-primary">Login</a>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection
