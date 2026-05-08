<?php

/**
 * Core Web Routes
 *
 * Public pages, authentication redirects, and profile management.
 * Role-specific routes are split into separate files loaded via bootstrap/app.php:
 *   routes/owner.php, routes/doctor.php, routes/receptionist.php,
 *   routes/triage.php, routes/laboratory.php, routes/radiology.php,
 *   routes/pharmacy.php, routes/shared.php
 */

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ── Public ──
Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
    }
    return redirect()->route('login');
});

// ── Dashboard Redirect (role-based) ──
Route::get('/dashboard', function () {
    /** @var \App\Models\User $user */
    $user = Auth::user();
    if ($user->hasRole('Owner')) return redirect()->route('owner.dashboard');
    if ($user->hasRole('Doctor')) return redirect()->route('doctor.dashboard');
    if ($user->hasRole('Receptionist')) return redirect()->route('receptionist.dashboard');
    if ($user->hasRole('Triage')) return redirect()->route('triage.dashboard');
    if ($user->hasRole('Laboratory')) return redirect()->route('laboratory.dashboard');
    if ($user->hasRole('Radiology')) return redirect()->route('radiology.dashboard');
    if ($user->hasRole('Pharmacy')) return redirect()->route('pharmacy.dashboard');
    if ($user->hasRole('Patient')) return redirect()->route('patient.dashboard');
    if ($user->hasRole('Vendor')) return redirect()->route('vendor.dashboard');
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/home', function () {
    return view('home');
})->middleware(['auth', 'verified'])->name('home');

// ── Profile & Tour ──
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/user/complete-tour', [UserController::class, 'completeTour'])->name('user.complete-tour');
});

// ── Attendance (all staff roles) ──
Route::middleware(['auth', 'verified', 'role:Doctor|Receptionist|Triage|Laboratory|Radiology|Pharmacy'])
    ->group(function () {
        Route::post('/attendance/clock-in',  [\App\Http\Controllers\AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
        Route::post('/attendance/clock-out', [\App\Http\Controllers\AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
        Route::get('/attendance/status',     [\App\Http\Controllers\AttendanceController::class, 'status'])->name('attendance.status');
    });

require __DIR__.'/auth.php';

