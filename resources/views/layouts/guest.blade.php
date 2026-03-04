<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

        <title>{{ config('app.name', 'Aviva HealthCare') }} — Sign In</title>

        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
        <!-- Glass Prism Design System -->
        <link href="{{ asset('css/clinic-glass.css') }}" rel="stylesheet">

        @vite(['resources/js/app.js'])
    </head>
    <body>
        <div class="login-wrapper">
            <div class="login-card">
                <div class="login-card-header">
                    <div class="login-logo">
                        <i class="bi bi-heart-pulse"></i>
                    </div>
                    <h4><span class="text-emerald">Aviva</span> HealthCare</h4>
                    <p>Sign in to your account</p>
                </div>
                <div class="login-card-body">
                    {{ $slot }}
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
